<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\TransactionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TransactionService $service,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'from', 'to', 'search', 'provider_id', 'category_id']);

        return view('admin.transactions.index', [
            'transactions' => $this->transactions->paginate($filters, 25, ['user', 'product']),
            'summary' => $this->transactions->summary($filters['from'] ?? null, $filters['to'] ?? null),
            'providers' => Provider::orderBy('name')->get(),
            'categories' => Category::active()->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Transaction $transaction): View
    {
        return view('admin.transactions.show', [
            'transaction' => $transaction->load(['user', 'product.category', 'provider', 'apiLogs', 'mutations']),
        ]);
    }

    public function refund(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if (! $transaction->isRefundable()) {
            return back()->with('error', 'Transaksi ini tidak dapat direfund.');
        }

        $this->service->refund($transaction, $request->string('reason')->toString(), $request->user()->id);

        return back()->with('success', "Refund {$transaction->invoice_no} berhasil diproses.");
    }

    public function resend(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->service->resend($transaction, $request->user()->id);

        return back()->with('success', "Transaksi {$transaction->invoice_no} dikirim ulang ke provider.");
    }

    public function sync(Transaction $transaction): RedirectResponse
    {
        $this->service->syncStatus($transaction);

        return back()->with('success', 'Status transaksi disinkronkan dengan provider.');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['status', 'from', 'to', 'search', 'provider_id', 'category_id']);

        return Excel::download(
            new TransactionsExport($filters),
            'transaksi-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function exportPdf(Request $request): Response
    {
        $filters = $request->only(['status', 'from', 'to', 'search']);

        $pdf = Pdf::loadView('admin.transactions.export-pdf', [
            'transactions' => $this->transactions->all($filters, ['user', 'product']),
            'summary' => $this->transactions->summary($filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('transaksi-'.now()->format('Ymd-His').'.pdf');
    }
}
