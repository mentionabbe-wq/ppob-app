<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit log panel admin: mencatat setiap aksi yang mengubah data.
 */
class LogActivity
{
    private const IGNORED_PATHS = ['admin/login', 'admin/logout', 'horizon', 'livewire'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request, $response)) {
            return $response;
        }

        ActivityLog::record(
            action: strtolower($request->method()).':'.$request->path(),
            properties: ['input' => $request->except([
                'password', 'password_confirmation', 'current_password', 'pin', '_token',
            ])],
        );

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->user() || $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        foreach (self::IGNORED_PATHS as $path) {
            if ($request->is($path.'*')) {
                return false;
            }
        }

        return $request->is('admin/*');
    }
}
