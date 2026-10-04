<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * ApiExceptionHandler — centralised JSON error formatting (error-handler,
 * shared-modules).
 *
 * Implements shared_decisions.error_format:
 *   { "message": "Error message", "code": "ERROR_CODE", "errors": { "field": ["detail error"] } }
 * `errors` is only present for 422 validation failures; every other error
 * (401 / 403 / 404 / 500 / ...) returns `message` (plus `code` where one is
 * known).
 *
 * `code` — ADDED 2026-09-22 with screen-128--kelola-periode-pelaporan, and
 * deliberately ADDITIVE: no existing key changes shape or value, and no
 * response that had no code before gains one unless it falls into one of
 * the two cases below.
 *
 *   1. The exception opts in by implementing App\Exceptions\HasErrorCode
 *      (PeriodOverlapException, PeriodClosedImmutableException,
 *      PeriodAlreadyClosedException, PeriodNotClosedException) — its
 *      errorCode() is emitted verbatim. Every pre-existing exception in
 *      this namespace (ExportFailedException,
 *      ProductionLineHasStationsException, ...) does NOT implement it and
 *      therefore renders exactly as it did before.
 *   2. Four framework-level codes this handler derives itself from the
 *      condition it is already branching on: VALIDATION_ERROR (422
 *      ValidationException), UNAUTHENTICATED (401), FORBIDDEN (403),
 *      NOT_FOUND (404).
 *
 * Unhandled 500s stay code-less on purpose — there is no meaningful
 * machine-readable code for "something broke", and inventing one would
 * invite clients to branch on it.
 *
 * NOT COVERED HERE: App\Http\Middleware\EnsureRole builds its own 401/403
 * JSON responses directly and never reaches this handler, so a role-gated
 * route's rejection carries `message` only. See the screen-128 4-implement
 * known_issues.
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
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Anda tidak memiliki akses untuk aksi ini.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        // Laravel 11's Handler::render() runs prepareException() before any
        // custom render callback sees the exception, which converts
        // ModelNotFoundException -> NotFoundHttpException (with the original
        // ModelNotFoundException as getPrevious()). Detect that wrapping here
        // and prefer the default 404 message over the raw Eloquent message
        // (e.g. "No query results for model [...] <id>"), instead of falling
        // through to the generic HttpExceptionInterface branch below, which
        // would otherwise leak $e->getMessage(). Any other NotFoundHttpException
        // (e.g. an explicit abort(404, 'custom message')) still falls through
        // to the generic HttpExceptionInterface branch below unchanged.
        if ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException) {
            return response()->json([
                'message' => static::defaultMessageFor(404),
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            $payload = ['message' => $e->getMessage() ?: static::defaultMessageFor($status)];

            $code = static::codeFor($e, $status);

            if ($code !== null) {
                $payload['code'] = $code;
            }

            return response()->json($payload, $status);
        }

        // QueryException — JARING PENGAMAN, TIDAK PERNAH membocorkan teks SQL,
        // juga saat APP_DEBUG=true (2026-10-04: POST /api/grading-records
        // dengan UUID tidak valid menampilkan SQLSTATE lengkap ke pengguna).
        // Detailnya tetap tercatat di log lewat report() bawaan Laravel.
        //
        // SQLSTATE 22P02 (invalid_text_representation) dan 22007/22008
        // (format tanggal/waktu) di PostgreSQL berarti NILAI KIRIMAN klien
        // berformat salah (mis. id bukan UUID) — kesalahan input, jadi 422,
        // bukan 500. Validasi eksplisit di service adalah garis depannya;
        // ini hanya menangkap jalur yang terlewat.
        if ($e instanceof QueryException) {
            if (in_array((string) $e->getCode(), ['22P02', '22007', '22008'], true)) {
                return response()->json([
                    'message' => 'Data yang dikirim tidak valid (format ID atau nilai tidak dikenali).',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => (object) [],
                ], 422);
            }

            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }

        // Generic/unhandled throwable — never leak internals in production.
        $debug = (bool) config('app.debug');

        return response()->json([
            'message' => $debug ? $e->getMessage() : 'Terjadi kesalahan pada server.',
        ], 500);
    }

    /**
     * The machine-readable `code` for an HTTP exception, or null when
     * there is none — in which case the response keeps its historical
     * message-only shape.
     *
     * Opt-in (HasErrorCode) wins over the status-derived fallback, so an
     * exception that carries its own 403/404-status code still emits that
     * code rather than the generic FORBIDDEN/NOT_FOUND.
     */
    protected static function codeFor(Throwable $e, int $status): ?string
    {
        if ($e instanceof HasErrorCode) {
            return $e->errorCode();
        }

        return match ($status) {
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            default => null,
        };
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
