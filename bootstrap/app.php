<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Modular Route Registration
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api/v1/inventory')
                ->name('api.inventory.')
                ->group(base_path('app/Modules/Inventory/Routes/api.php'));

            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api/v1/recognition')
                ->name('api.recognition.')
                ->group(base_path('app/Modules/Recognition/Routes/api.php'));

            Route::middleware('api')
                ->prefix('api/v1/auth')
                ->name('api.auth.')
                ->group(base_path('app/Modules/Auth/Routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Locale resolution middleware
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Production exception mapping
    })->create();
