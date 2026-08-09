<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_transfer_bank_mendapat_kode_unik(): void
    {
        $user = User::factory()->create();

        $deposit = app(DepositService::class)->request($user, 100_000, 'bank_transfer', 'bca');

        $this->assertSame(DepositStatus::WaitingPayment, $deposit->status);
        $this->assertGreaterThan(100, $deposit->unique_code);
        $this->assertEquals(100_000 + $deposit->unique_code, (float) $deposit->total_amount);
    }

    public function test_approve_menambah_saldo_tepat_sekali(): void
    {
        $user = User::factory()->withBalance(10_000)->create();
        $admin = User::factory()->create();
        $service = app(DepositService::class);

        $deposit = $service->request($user, 50_000, 'bank_transfer', 'bca');

        $service->approve($deposit, $admin->id);
        $service->approve($deposit->fresh(), $admin->id); // pemanggilan ulang

        $this->assertEquals(60_000, (float) $user->fresh()->wallet->balance);
        $this->assertSame(1, $deposit->mutations()->count());
        $this->assertSame(DepositStatus::Approved, $deposit->fresh()->status);
    }

    public function test_deposit_di_luar_batas_ditolak(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(DepositService::class)->request($user, 1_000, 'bank_transfer', 'bca');
    }

    public function test_deposit_yang_ditolak_tidak_menambah_saldo(): void
    {
        $user = User::factory()->withBalance(10_000)->create();
        $admin = User::factory()->create();
        $service = app(DepositService::class);

        $deposit = $service->request($user, 50_000, 'bank_transfer', 'bca');
        $service->reject($deposit, 'Bukti transfer tidak sesuai', $admin->id);

        $this->assertEquals(10_000, (float) $user->fresh()->wallet->balance);
        $this->assertSame(DepositStatus::Rejected, $deposit->fresh()->status);
    }
}
