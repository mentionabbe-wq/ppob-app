<?php

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\ProviderException;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminOnly::class,
            'active' => EnsureUserIsActive::class,
            'webhook.signature' => VerifyWebhookSignature::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->web(append: [
            LogActivity::class,
        ]);

        // Webhook provider tidak boleh kena CSRF (request server-to-server).
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'api/v1/webhooks/*',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // biarkan Blade error page menangani panel admin
            }

            [$status, $code, $message] = match (true) {
                $e instanceof ValidationException => [422, 'VALIDATION_ERROR', 'Data yang dikirim tidak valid.'],
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED', 'Sesi berakhir, silakan login kembali.'],
                $e instanceof ModelNotFoundException => [404, 'NOT_FOUND', 'Data tidak ditemukan.'],
                $e instanceof InsufficientBalanceException => [422, 'INSUFFICIENT_BALANCE', $e->getMessage()],
                $e instanceof ProviderException => [502, 'PROVIDER_ERROR', $e->getMessage()],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'HTTP_ERROR', $e->getMessage()],
                default => [500, 'SERVER_ERROR', 'Terjadi kesalahan pada server.'],
            };

            $payload = [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            if (config('app.debug') && $status === 500) {
                $payload['debug'] = [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ];
            }

            return response()->json($payload, $status);
        });
    })->create();
