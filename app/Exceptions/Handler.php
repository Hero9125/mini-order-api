<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        // ── InsufficientStockException → 422 ────────────────────────────────
        $this->renderable(function (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => [
                    'stock' => [
                        'product'   => $e->productName,
                        'available' => $e->available,
                        'requested' => $e->requested,
                    ],
                ],
            ], 422);
        });

        // ── Model not found → 404 JSON (API requests) ───────────────────────
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Route not found.',
                ], 404);
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
