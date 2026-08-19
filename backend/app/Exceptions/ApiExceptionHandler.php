<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * ApiExceptionHandler — centralised JSON error formatting (error-handler,
 * shared-modules).
 *
 * Implements shared_decisions.error_format exactly:
 *   { "message": "Error message", "errors": { "field": ["detail error"] } }
 * `errors` is only present for 422 validation failures; every other error
 * (401 / 403 / 404 / 500 / ...) returns `message` only.
 *
 * Wired into bootstrap/app.php's withExceptions() — only renders a JSON
 * response when the request expects JSON (API routes, or an explicit
 * `Accept: application/json`); Livewire/web requests fall through to
 * Laravel's normal HTML error handling.
 */
class ApiExceptionHandler
{
    public static function shouldHandle(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    public static function render(Request $request, Throwable $e): ?JsonResponse
    {
        if (! static::shouldHandle($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Anda tidak memiliki akses untuk aksi ini.',
            ], 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage() ?: static::defaultMessageFor($status),
            ], $status);
        }

        // Generic/unhandled throwable — never leak internals in production.
        $debug = (bool) config('app.debug');

        return response()->json([
            'message' => $debug ? $e->getMessage() : 'Terjadi kesalahan pada server.',
        ], 500);
    }

    protected static function defaultMessageFor(int $status): string
    {
        return match ($status) {
            401 => 'Unauthenticated.',
            403 => 'Anda tidak memiliki akses untuk aksi ini.',
            404 => 'Data tidak ditemukan.',
            default => 'Terjadi kesalahan.',
        };
    }
}
