<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Deposit\StoreDepositRequest;
use App\Http\Resources\DepositResource;
use App\Models\Deposit;
use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends BaseApiController
{
    public function __construct(
        private readonly DepositRepositoryInterface $deposits,
        private readonly DepositService $service,
    ) {}

    /** @OA\Get(path="/deposits", tags={"Deposit"}, security={{"bearerAuth":{}}}, summary="Riwayat deposit") */
    public function index(Request $request): JsonResponse
    {
        $deposits = $this->deposits->paginate(
            $request->only(['status', 'method', 'from', 'to']) + ['user_id' => $request->user()->id],
            (int) $request->integer('per_page', 20),
        );

        return $this->ok(DepositResource::collection($deposits));
    }

    /** @OA\Get(path="/deposits/methods", tags={"Deposit"}, summary="Daftar metode & kanal pembayaran") */
    public function methods(): JsonResponse
    {
        return $this->ok([
            'min_amount' => (float) config('ppob.deposit.min'),
            'max_amount' => (float) config('ppob.deposit.max'),
            'methods' => [
                [
                    'code' => 'bank_transfer',
                    'name' => 'Transfer Bank',
                    'description' => 'Transfer manual, verifikasi 1x24 jam.',
                    'channels' => config('ppob.deposit.banks'),
                ],
                [
                    'code' => 'virtual_account',
                    'name' => 'Virtual Account',
                    'description' => 'Otomatis masuk setelah pembayaran.',
                    'channels' => [
                        ['code' => 'bca', 'name' => 'BCA Virtual Account'],
                        ['code' => 'bni', 'name' => 'BNI Virtual Account'],
                        ['code' => 'bri', 'name' => 'BRI Virtual Account'],
                        ['code' => 'permata', 'name' => 'Permata Virtual Account'],
                    ],
                ],
                ['code' => 'qris', 'name' => 'QRIS', 'description' => 'Scan dari aplikasi apa pun.', 'channels' => []],
                [
                    'code' => 'ewallet',
                    'name' => 'E-Wallet',
                    'description' => 'GoPay, OVO, DANA, ShopeePay.',
                    'channels' => [
                        ['code' => 'gopay', 'name' => 'GoPay'],
                        ['code' => 'ovo', 'name' => 'OVO'],
                        ['code' => 'dana', 'name' => 'DANA'],
                        ['code' => 'shopeepay', 'name' => 'ShopeePay'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *   path="/deposits", tags={"Deposit"}, security={{"bearerAuth":{}}},
     *   summary="Ajukan deposit saldo",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"amount","method"},
     *     @OA\Property(property="amount", type="number", example=100000),
     *     @OA\Property(property="method", type="string", enum={"bank_transfer","virtual_account","qris","ewallet"}),
     *     @OA\Property(property="channel", type="string", example="bca")
     *   )),
     *   @OA\Response(response=201, description="Instruksi pembayaran")
     * )
     */
    public function store(StoreDepositRequest $request): JsonResponse
    {
        $deposit = $this->service->request(
            $request->user(),
            (float) $request->input('amount'),
            $request->string('method')->toString(),
            $request->input('channel'),
        );

        return $this->created(new DepositResource($deposit), 'Deposit dibuat. Silakan selesaikan pembayaran.');
    }

    /** @OA\Get(path="/deposits/{id}", tags={"Deposit"}, security={{"bearerAuth":{}}}, summary="Detail deposit") */
    public function show(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorizeOwner($request, $deposit);

        return $this->ok(new DepositResource($deposit));
    }

    /**
     * @OA\Post(
     *   path="/deposits/{id}/proof", tags={"Deposit"}, security={{"bearerAuth":{}}},
     *   summary="Unggah bukti transfer (opsional)"
     * )
     */
    public function uploadProof(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorizeOwner($request, $deposit);

        $request->validate([
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $deposit = $this->service->attachProof($deposit, $request->file('proof'));

        return $this->ok(new DepositResource($deposit), 'Bukti transfer terkirim, menunggu verifikasi admin.');
    }

    private function authorizeOwner(Request $request, Deposit $deposit): void
    {
        abort_unless(
            $deposit->user_id === $request->user()->id
                || $request->user()->hasAnyRole(['super-admin', 'admin', 'finance']),
            403,
            'Anda tidak berhak mengakses deposit ini.',
        );
    }
}
