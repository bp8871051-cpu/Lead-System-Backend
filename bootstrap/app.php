<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\PDOException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $isConnectionRefused = $e->getCode() == 2002 || str_contains($e->getMessage(), '2002') || str_contains(strtolower($e->getMessage()), 'refused');
                if ($isConnectionRefused) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Database connection error: Unable to connect to MySQL database server.',
                        'error' => config('app.debug') ? $e->getMessage() : 'Database connection refused.',
                    ], 500);
                }
            }
        });

        $exceptions->render(function (QueryException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $isConnectionRefused = $e->getCode() == 2002 || str_contains($e->getMessage(), '2002') || str_contains(strtolower($e->getMessage()), 'refused');
                if ($isConnectionRefused) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Database connection error: Unable to connect to MySQL database server.',
                        'error' => config('app.debug') ? $e->getMessage() : 'Database connection refused.',
                    ], 500);
                }
            }
        });
    })->create();

