<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\TopupRequestData;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\TransactionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends BaseApiController
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TransactionService $service,
    ) {}

    /**
     * @OA\Get(
     *   path="/transactions", tags={"Transaksi"}, security={{"bearerAuth":{}}},
     *   summary="Riwayat transaksi pengguna",
     *   @OA\Parameter(name="status", in="query", @OA\Schema(type="string"), example="success"),
     *   @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="Daftar transaksi (paginated)")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'from', 'to', 'search']) + ['user_id' => $request->user()->id];

        $transactions = $this->transactions->paginate(
            $filters,
            (int) $request->integer('per_page', 20),
            ['product.category'],
        );

        return $this->ok(TransactionResource::collection($transactions));
    }

    /**
     * @OA\Post(
     *   path="/transactions", tags={"Transaksi"}, security={{"bearerAuth":{}}},
     *   summary="Buat transaksi pembelian",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"product_id","customer_no"},
     *     @OA\Property(property="product_id", type="integer", example=5),
     *     @OA\Property(property="customer_no", type="string", example="081234567890"),
     *     @OA\Property(property="ref_id", type="string", description="Kunci idempotency dari klien"),
     *     @OA\Property(property="promo_code", type="string", example="HEMAT10"),
     *     @OA\Property(property="pin", type="string", example="123456")
     *   )),
     *   @OA\Response(response=202, description="Transaksi diterima dan sedang diproses"),
     *   @OA\Response(response=422, description="Saldo tidak cukup / produk tidak tersedia")
     * )
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $product = Product::with(['provider', 'category'])->findOrFail($request->integer('product_id'));

        $meta = [];
        if ($product->category->type === 'postpaid') {
            // Nominal tagihan pascabayar ditentukan hasil inquiry, bukan input user.
            $meta['bill_amount'] = null;
        }

        $transaction = $this->service->purchase(TopupRequestData::make(
            user: $request->user(),
            product: $product,
            customerNo: $request->string('customer_no')->toString(),
            refId: $request->string('ref_id')->toString(),
            promoCode: $request->input('promo_code'),
            pin: $request->input('pin'),
            meta: $meta,
        ));

        return $this->accepted(
            new TransactionResource($transaction->load('product.category')),
            'Transaksi sedang diproses. Status akan diperbarui otomatis.',
        );
    }

    /** @OA\Get(path="/transactions/{id}", tags={"Transaksi"}, security={{"bearerAuth":{}}}, summary="Detail transaksi") */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwner($request, $transaction);

        return $this->ok(new TransactionResource($transaction->load('product.category')));
    }

    /**
     * Polling status realtime dari aplikasi mobile.
     *
     * @OA\Get(path="/transactions/{id}/status", tags={"Transaksi"}, security={{"bearerAuth":{}}}, summary="Status terkini")
     */
    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwner($request, $transaction);

        // Bila masih menggantung, tanyakan langsung ke provider.
        if (! $transaction->status->isFinal() && $transaction->updated_at->lt(now()->subSeconds(20))) {
            $transaction = $this->service->syncStatus($transaction);
        }

        return $this->ok([
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'serial_number' => $transaction->serial_number,
            'message' => $transaction->provider_message,
            'updated_at' => $transaction->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Unduh invoice PDF — dipakai fitur "Cetak" dan "Bagikan" di aplikasi.
     *
     * @OA\Get(path="/transactions/{id}/invoice", tags={"Transaksi"}, security={{"bearerAuth":{}}}, summary="Invoice PDF")
     */
    public function invoice(Request $request, Transaction $transaction): Response
    {
        $this->authorizeOwner($request, $transaction);

        $pdf = Pdf::loadView('pdf.invoice', [
            'transaction' => $transaction->load(['product.category', 'user']),
        ])->setPaper('a5');

        return $pdf->download("invoice-{$transaction->invoice_no}.pdf");
    }

    private function authorizeOwner(Request $request, Transaction $transaction): void
    {
        $user = $request->user();

        abort_unless(
            $transaction->user_id === $user->id || $user->hasAnyRole(['super-admin', 'admin', 'finance', 'operator']),
            403,
            'Anda tidak berhak mengakses transaksi ini.',
        );
    }
}
