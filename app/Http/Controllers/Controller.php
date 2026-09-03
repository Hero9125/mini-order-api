<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Mini Order Management API",
 *     version="1.0.0",
 *     description="A production-quality REST API for order management built with Laravel 10 + Sanctum.",
 *     @OA\Contact(email="admin@miniorderapi.com")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Sanctum bearer token obtained from /api/v1/auth/login"
 * )
 *
 * @OA\Server(url="http://localhost:8000", description="Local development server")
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
