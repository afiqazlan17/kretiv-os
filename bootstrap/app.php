<?php

use App\Http\Middleware\DemoGuard;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['module' => EnsureModuleAccess::class]);
        $middleware->appendToGroup('web', ForcePasswordChange::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('web', DemoGuard::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
