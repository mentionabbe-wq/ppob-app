<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrasi_membuat_dompet_dan_mengirim_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $user = User::where('email', 'budi@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
        $this->assertEquals(0.0, (float) $user->wallet->balance);
        Mail::assertQueued(OtpMail::class);
    }

    public function test_login_mengembalikan_token_jwt(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $user->assignRole('user');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_in', 'user' => ['id', 'email', 'balance']]]);
    }

    public function test_akun_suspended_tidak_dapat_login(): void
    {
        $user = User::factory()->suspended()->create(['email' => 'blokir@example.com']);
        $user->assignRole('user');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'blokir@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_endpoint_terproteksi_menolak_tanpa_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_me_mengembalikan_profil_pengguna(): void
    {
        $user = User::factory()->withBalance(25_000)->create();
        $user->assignRole('user');

        $this->actingAsApi($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.balance', 25000);
    }

    public function test_forgot_password_tidak_membocorkan_email_terdaftar(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'tidakada@example.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingQueued();
    }
}
