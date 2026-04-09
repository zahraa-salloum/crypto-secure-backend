<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Notifications\ResetPassword;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Don't use EnsureFrontendRequestsAreStateful for pure API token auth
        // Only use it if you need cookie-based SPA authentication
        
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin'    => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        
        // Exclude API routes from CSRF verification (token-based auth)
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function () {
        // Point password reset emails to the Angular frontend instead of
        // a Laravel web route (this is an API-only backend).
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:4200');
            return "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($user->email);
        });
    })
    ->create();
