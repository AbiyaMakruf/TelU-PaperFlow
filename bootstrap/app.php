<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SkipNgrokWarning;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SkipNgrokWarning::class);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->alias([
            'password.changed' => ForcePasswordChange::class,
            'superadmin' => RequireSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::error('[Paperflow 404 Not Found] Route missing', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
            ]);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The route ('.$request->path().') could not be found.',
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::warning('[Paperflow 403 Forbidden] Authorization check failed', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'message' => $e->getMessage(),
            ]);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This action is unauthorized: '.$e->getMessage(),
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::warning('[Paperflow 419 Token Mismatch] CSRF token invalid or missing', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
            ]);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired or invalid CSRF token. Please refresh the page and try again.',
                ], 419);
            }
        });
    })->create();
