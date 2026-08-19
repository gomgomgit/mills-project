<?php

namespace App\Exceptions;

/**
 * ErrorCodes — named constants for common API error conditions
 * (error-handler, shared-modules).
 *
 * Not transmitted in the response body itself (shared_decisions.error_format
 * only defines `message` / `errors`), but intended for internal use — e.g.
 * logging, matching in tests, or services/controllers deciding which
 * exception to throw.
 */
final class ErrorCodes
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const SERVER_ERROR = 'SERVER_ERROR';
}
