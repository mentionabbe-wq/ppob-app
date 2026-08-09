<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_INACTIVE',
                'message' => $user->status === 'suspended'
                    ? 'Akun Anda diblokir. Silakan hubungi dukungan.'
                    : 'Akun Anda belum aktif.',
            ], 403);
        }

        return $next($request);
    }
}
