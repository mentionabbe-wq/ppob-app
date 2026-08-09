<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends BaseApiController
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProviderManager $providers,
    ) {}

    /**
     * @OA\Get(
     *   path="/categories", tags={"Katalog"}, summary="Daftar kategori produk",
     *   @OA\Response(response=200, description="Kategori aktif beserta subkategori")
     * )
     */
    public function categories(): JsonResponse
    {
        // Katalog jarang berubah — cache 10 menit agar dashboard app ringan.
        $categories = Cache::remember('catalog.categories', now()->addMinutes(10), fn () => Category::active()
            ->root()
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->get());

        return $this->ok(CategoryResource::collection($categories));
    }

    /**
     * @OA\Get(
     *   path="/products", tags={"Katalog"}, summary="Daftar produk siap jual",
     *   @OA\Parameter(name="category_slug", in="query", @OA\Schema(type="string"), example="pulsa"),
     *   @OA\Parameter(name="brand", in="query", @OA\Schema(type="string"), example="TELKOMSEL"),
     *   @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Daftar produk")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'category_slug', 'brand', 'search']);

        return $this->ok(ProductResource::collection($this->products->sellable($filters)));
    }

    /** @OA\Get(path="/products/{id}", tags={"Katalog"}, summary="Detail produk") */
    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404, 'Produk tidak ditemukan.');

        return $this->ok(new ProductResource($product->load('category')));
    }

    /**
     * @OA\Get(
     *   path="/categories/{slug}/brands", tags={"Katalog"},
     *   summary="Daftar operator/brand dalam satu kategori"
     * )
     */
    public function brands(Category $category): JsonResponse
    {
        return $this->ok($this->products->brands($category->id));
    }

    /**
     * Deteksi operator dari prefiks nomor HP — agar app langsung
     * menampilkan produk yang relevan tanpa user memilih operator.
     *
     * @OA\Get(path="/products/detect-operator", tags={"Katalog"}, summary="Deteksi operator dari nomor HP")
     */
    public function detectOperator(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'min:4', 'max:20']]);

        $number = preg_replace('/\D/', '', $request->string('phone')->toString());
        $number = str_starts_with($number, '62') ? '0'.substr($number, 2) : $number;
        $prefix = substr($number, 0, 4);

        $operator = match (true) {
            in_array($prefix, ['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853'], true) => 'TELKOMSEL',
            in_array($prefix, ['0814', '0815', '0816', '0855', '0856', '0857', '0858'], true) => 'INDOSAT',
            in_array($prefix, ['0817', '0818', '0819', '0859', '0877', '0878'], true) => 'XL',
            in_array($prefix, ['0831', '0832', '0833', '0838'], true) => 'AXIS',
            in_array($prefix, ['0895', '0896', '0897', '0898', '0899'], true) => 'TRI',
            in_array($prefix, ['0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889'], true) => 'SMARTFREN',
            default => null,
        };

        return $this->ok([
            'phone' => $number,
            'prefix' => $prefix,
            'operator' => $operator,
        ], $operator ? "Operator terdeteksi: {$operator}." : 'Operator tidak dikenali.');
    }

    /**
     * Cek tagihan produk pascabayar (PLN, BPJS, PDAM, Telkom, dll).
     *
     * @OA\Post(
     *   path="/products/inquiry", tags={"Katalog"}, security={{"bearerAuth":{}}},
     *   summary="Cek tagihan pascabayar",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"product_id","customer_no"},
     *     @OA\Property(property="product_id", type="integer", example=12),
     *     @OA\Property(property="customer_no", type="string", example="530000000001")
     *   ))
     * )
     */
    public function inquiry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'customer_no' => ['required', 'string', 'min:4', 'max:40'],
        ]);

        $product = Product::with('provider')->findOrFail($validated['product_id']);
        $result = $this->providers->make($product->provider)->inquiry(
            $product->provider_sku,
            $validated['customer_no'],
        );

        if (! $result->found) {
            return $this->fail($result->message ?? 'Tagihan tidak ditemukan.', 'BILL_NOT_FOUND', 404);
        }

        return $this->ok([
            'customer_no' => $result->customerNo,
            'customer_name' => $result->customerName,
            'bill_amount' => $result->billAmount,
            'admin_fee' => $result->adminFee,
            'total' => $result->total(),
            'period' => $result->period,
            'detail' => $result->detail,
        ], 'Tagihan ditemukan.');
    }
}
