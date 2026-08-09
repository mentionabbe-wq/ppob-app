<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly DepositRepositoryInterface $deposits,
    ) {}

    public function index(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return view('admin.reports.index', [
            'summary' => $this->transactions->summary($from, $to),
            'depositSummary' => $this->deposits->summary($from, $to),
            'dailySeries' => $this->transactions->dailySeries(30),
            'monthlySeries' => $this->transactions->monthlySeries(12),
            'bestProducts' => $this->transactions->bestSellingProducts(20, $from, $to),
            'activeUsers' => $this->transactions->mostActiveUsers(20, $from, $to),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new TransactionsExport($request->only(['from', 'to', 'status'])),
            'laporan-penjualan-'.now()->format('Ymd').'.xlsx',
        );
    }
}
