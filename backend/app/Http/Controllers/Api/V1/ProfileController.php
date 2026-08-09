<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\BankAccount;
use App\Services\AuthService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly WalletService $wallet,
    ) {}

    /** @OA\Put(path="/profile", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Perbarui profil") */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/',
                Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            unset($validated['avatar']);
        }

        $user->update($validated);

        return $this->ok(new UserResource($user->fresh('wallet')), 'Profil diperbarui.');
    }

    /** @OA\Put(path="/profile/password", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Ganti kata sandi") */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $this->auth->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return $this->ok(null, 'Kata sandi berhasil diubah.');
    }

    /** @OA\Put(path="/profile/pin", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Atur PIN transaksi") */
    public function setPin(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
            'password' => ['required', 'string'],
            'current_pin' => [filled($user->pin_hash) ? 'required' : 'nullable', 'digits:6'],
        ]);

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->fail('Kata sandi salah.', 'INVALID_PASSWORD', 422);
        }

        if (filled($user->pin_hash) && ! password_verify($request->string('current_pin')->toString(), $user->pin_hash)) {
            return $this->fail('PIN lama salah.', 'INVALID_PIN', 422);
        }

        $user->update([
            'pin_hash' => password_hash($request->string('pin')->toString(), PASSWORD_BCRYPT),
        ]);

        return $this->ok(null, 'PIN transaksi berhasil disimpan.');
    }

    /** @OA\Get(path="/profile/mutations", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Mutasi saldo") */
    public function mutations(Request $request): JsonResponse
    {
        $mutations = $request->user()->mutations()
            ->between($request->input('from'), $request->input('to'))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok([
            'balance' => $this->wallet->balance($request->user()),
            'items' => $mutations->through(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'amount' => (float) $m->amount,
                'balance_after' => (float) $m->balance_after,
                'description' => $m->description,
                'created_at' => $m->created_at->toIso8601String(),
            ])->items(),
            'meta' => [
                'current_page' => $mutations->currentPage(),
                'last_page' => $mutations->lastPage(),
                'total' => $mutations->total(),
            ],
        ]);
    }

    /** @OA\Get(path="/profile/bank-accounts", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Daftar rekening") */
    public function bankAccounts(Request $request): JsonResponse
    {
        return $this->ok($request->user()->bankAccounts()->orderByDesc('is_primary')->get());
    }

    /** @OA\Post(path="/profile/bank-accounts", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Tambah rekening") */
    public function storeBankAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'account_name' => ['required', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ]);

        $account = $request->user()->bankAccounts()->create($validated);

        if ($request->boolean('is_primary')) {
            $request->user()->bankAccounts()->whereKeyNot($account->id)->update(['is_primary' => false]);
        }

        return $this->created($account, 'Rekening ditambahkan.');
    }

    /** @OA\Delete(path="/profile/bank-accounts/{id}", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Hapus rekening") */
    public function destroyBankAccount(Request $request, BankAccount $bankAccount): JsonResponse
    {
        abort_unless($bankAccount->user_id === $request->user()->id, 403);

        $bankAccount->delete();

        return $this->ok(null, 'Rekening dihapus.');
    }

    /** @OA\Put(path="/profile/fcm-token", tags={"Profil"}, security={{"bearerAuth":{}}}, summary="Perbarui token FCM") */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $request->user()->update(['fcm_token' => $request->string('fcm_token')->toString()]);

        return $this->ok(null, 'Token notifikasi diperbarui.');
    }
}
