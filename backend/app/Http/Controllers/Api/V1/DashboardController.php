<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BannerResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Promo;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletService $wallet,
    ) {}

    /**
     * Satu panggilan untuk seluruh isi layar beranda aplikasi:
     * saldo, ringkasan transaksi, banner, promo, menu, riwayat terakhir.
     *
     * @OA\Get(
     *   path="/dashboard", tags={"Dashboard"}, security={{"bearerAuth":{}}},
     *   summary="Data beranda aplikasi",
     *   @OA\Response(response=200, description="Ringkasan beranda")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->transactions->summary(null, null, $user->id);

        // Banner, promo, dan menu sama untuk semua user → aman di-cache.
        $shared = Cache::remember('dashboard.shared', now()->addMinutes(5), fn () => [
            'banners' => BannerResource::collection(Banner::visible()->get()),
            'promos' => Promo::active()->orderByDesc('id')->limit(10)->get()->map(fn (Promo $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'title' => $p->title,
                'description' => $p->description,
                'discount_type' => $p->discount_type,
                'discount_value' => (float) $p->discount_value,
                'min_transaction' => (float) $p->min_transaction,
                'end_date' => $p->end_date->toDateString(),
            ]),
            'menus' => CategoryResource::collection(
                Category::active()->root()->limit(12)->get()
            ),
        ]);

        return $this->ok([
            'balance' => $this->wallet->balance($user),
            'summary' => [
                'total_transaction' => $summary['total_count'],
                'success_transaction' => $summary['success_count'],
                'pending_transaction' => $summary['pending_count'],
                'total_spent' => $summary['omzet'],
            ],
            'banners' => $shared['banners'],
            'promos' => $shared['promos'],
            'menus' => $shared['menus'],
            'favorites' => $this->favoriteMenus($user->id),
            'recent_transactions' => TransactionResource::collection(
                Transaction::with('product.category')
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->limit(5)
                    ->get()
            ),
        ]);
    }

    /** Menu favorit = kategori yang paling sering dibeli user. */
    private function favoriteMenus(int $userId): array
    {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->join('products', 'products.id', '=', 'transactions.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.id, categories.name, categories.slug, categories.icon, COUNT(*) as usage_count')
            ->groupBy('categories.id', 'categories.name', 'categories.slug', 'categories.icon')
            ->orderByDesc('usage_count')
            ->limit(6)
            ->get()
            ->toArray();
    }
}
