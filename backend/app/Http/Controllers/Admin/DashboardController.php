<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Providers\ProviderManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly UserRepositoryInterface $users,
        private readonly DepositRepositoryInterface $deposits,
        private readonly WalletRepositoryInterface $wallets,
        private readonly ProviderManager $providers,
    ) {}

    public function index(Request $request): View
    {
        $from = $request->input('from');
        $to = $request->input('to');

        return view('admin.dashboard', [
            'summary' => $this->transactions->summary($from, $to),
            'today' => $this->transactions->summary(now()->toDateString(), now()->toDateString()),
            'userStats' => $this->users->stats(),
            'depositStats' => $this->deposits->summary($from, $to),
            'totalUserBalance' => $this->wallets->totalBalance(),
            'dailySeries' => $this->transactions->dailySeries(30),
            'monthlySeries' => $this->transactions->monthlySeries(12),
            'bestProducts' => $this->transactions->bestSellingProducts(5, $from, $to),
            'activeUsers' => $this->transactions->mostActiveUsers(5, $from, $to),
            'providerBalances' => $this->providerBalances(),
            'filters' => compact('from', 'to'),
        ]);
    }

    /** Saldo provider dibaca dari DB (hasil sinkronisasi terjadwal), bukan API langsung. */
    private function providerBalances(): array
    {
        return \App\Models\Provider::active()
            ->get(['name', 'code', 'balance', 'balance_synced_at'])
            ->toArray();
    }
}
