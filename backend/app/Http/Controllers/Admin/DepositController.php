<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Services\DepositService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositRepositoryInterface $deposits,
        private readonly DepositService $service,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'method', 'from', 'to', 'search']);

        return view('admin.deposits.index', [
            'deposits' => $this->deposits->paginate($filters, 25, ['user', 'approver']),
            'summary' => $this->deposits->summary($filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
        ]);
    }

    public function show(Deposit $deposit): View
    {
        return view('admin.deposits.show', [
            'deposit' => $deposit->load(['user.wallet', 'approver', 'mutations']),
        ]);
    }

    public function approve(Request $request, Deposit $deposit): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $this->service->approve($deposit, $request->user()->id, $request->input('note'));

        return back()->with('success', "Deposit {$deposit->code} disetujui, saldo user bertambah.");
    }

    public function reject(Request $request, Deposit $deposit): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->service->reject($deposit, $request->string('reason')->toString(), $request->user()->id);

        return back()->with('success', "Deposit {$deposit->code} ditolak.");
    }
}
