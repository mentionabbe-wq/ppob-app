<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WalletService $wallet,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'role', 'search']);

        return view('admin.users.index', [
            'users' => $this->users->paginate($filters, 25, ['wallet', 'roles']),
            'stats' => $this->users->stats(),
            'roles' => Role::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load(['wallet', 'roles', 'bankAccounts']),
            'transactions' => $user->transactions()->with('product')->latest('id')->limit(20)->get(),
            'deposits' => $user->deposits()->latest('id')->limit(10)->get(),
            'mutations' => $user->mutations()->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user->update(collect($validated)->except('roles')->all());
        $user->syncRoles($validated['roles'] ?? []);

        return back()->with('success', "Data {$user->name} diperbarui.");
    }

    /** Penyesuaian saldo manual — selalu tercatat di ledger & audit log. */
    public function adjustBalance(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->wallet->adjust(
            $user,
            (float) $validated['amount'],
            $validated['reason'],
            $request->user()->id,
        );

        return back()->with('success', sprintf(
            'Saldo %s disesuaikan sebesar Rp%s.',
            $user->name,
            number_format((float) $validated['amount'], 0, ',', '.'),
        ));
    }
}
