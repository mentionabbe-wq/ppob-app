<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly OtpService $otp,
    ) {}

    /**
     * @OA\Post(
     *   path="/auth/register", tags={"Auth"}, summary="Registrasi akun baru",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"name","email","password","password_confirmation"},
     *     @OA\Property(property="name", type="string", example="Budi Santoso"),
     *     @OA\Property(property="email", type="string", example="budi@mail.com"),
     *     @OA\Property(property="phone", type="string", example="081234567890"),
     *     @OA\Property(property="password", type="string", example="rahasia123"),
     *     @OA\Property(property="password_confirmation", type="string", example="rahasia123"),
     *     @OA\Property(property="referral_code", type="string", example="AB12CD34")
     *   )),
     *   @OA\Response(response=201, description="Akun dibuat, OTP dikirim ke email")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        return $this->created(
            new UserResource($user->load('wallet')),
            'Registrasi berhasil. Kode OTP telah dikirim ke email Anda.',
        );
    }

    /**
     * @OA\Post(
     *   path="/auth/login", tags={"Auth"}, summary="Login dan dapatkan token JWT",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"email","password"},
     *     @OA\Property(property="email", type="string", example="user@ppob.test"),
     *     @OA\Property(property="password", type="string", example="password"),
     *     @OA\Property(property="fcm_token", type="string")
     *   )),
     *   @OA\Response(response=200, description="Berhasil login"),
     *   @OA\Response(response=422, description="Kredensial salah / akun nonaktif")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('fcm_token'),
        );

        return $this->ok([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
        ], 'Login berhasil.');
    }

    /**
     * @OA\Post(path="/auth/google", tags={"Auth"}, summary="Login dengan Google ID token")
     */
    public function google(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ]);

        $result = $this->auth->loginWithGoogle(
            $request->string('id_token')->toString(),
            $request->input('fcm_token'),
        );

        return $this->ok([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
        ], 'Login berhasil.');
    }

    /** @OA\Post(path="/auth/otp/send", tags={"Auth"}, summary="Kirim ulang kode OTP") */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'purpose' => ['required', 'in:register,reset_password,login'],
        ]);

        $this->otp->send(
            strtolower($request->string('email')->toString()),
            $request->string('purpose')->toString(),
        );

        return $this->ok(null, 'Kode OTP telah dikirim ke email Anda.');
    }

    /** @OA\Post(path="/auth/otp/verify", tags={"Auth"}, summary="Verifikasi email dengan OTP") */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->auth->verifyEmail(
            strtolower($request->string('email')->toString()),
            $request->string('otp')->toString(),
        );

        return $this->ok(new UserResource($user), 'Email berhasil diverifikasi.');
    }

    /** @OA\Post(path="/auth/password/forgot", tags={"Auth"}, summary="Minta OTP reset kata sandi") */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->auth->sendPasswordResetOtp(strtolower($request->string('email')->toString()));

        // Respons selalu sama agar tidak membocorkan email terdaftar.
        return $this->ok(null, 'Bila email terdaftar, kode OTP telah dikirim.');
    }

    /** @OA\Post(path="/auth/password/reset", tags={"Auth"}, summary="Reset kata sandi dengan OTP") */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword(
            $request->string('email')->toString(),
            $request->string('otp')->toString(),
            $request->string('password')->toString(),
        );

        return $this->ok(null, 'Kata sandi berhasil diubah. Silakan login kembali.');
    }

    /** @OA\Get(path="/auth/me", tags={"Auth"}, security={{"bearerAuth":{}}}, summary="Profil pengguna aktif") */
    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()->load(['wallet', 'roles'])));
    }

    /** @OA\Post(path="/auth/refresh", tags={"Auth"}, security={{"bearerAuth":{}}}, summary="Perbarui token JWT") */
    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        return $this->ok([
            'token' => $result['token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
        ], 'Token diperbarui.');
    }

    /** @OA\Post(path="/auth/logout", tags={"Auth"}, security={{"bearerAuth":{}}}, summary="Logout") */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->ok(null, 'Berhasil keluar.');
    }
}
