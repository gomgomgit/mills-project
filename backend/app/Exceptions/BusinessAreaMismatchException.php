<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * BusinessAreaMismatchException — thrown by AuthService::login() when the
 * business_unit_id submitted at login does not match the user's own
 * business_unit_id (screen-001--login-web, usecase-001--login-web,
 * business_logic step 5 → 403 BUSINESS_AREA_MISMATCH).
 *
 * Interpretation note: the entity catalog has no many-to-many "business
 * units a user can access" table — User has a single business_unit_id FK —
 * so "akses user" is treated as an exact match against that field.
 *
 * Extends Illuminate\Auth\Access\AuthorizationException, which
 * ApiExceptionHandler already renders as 403 using $e->getMessage() — no
 * handler changes needed.
 */
class BusinessAreaMismatchException extends AuthorizationException
{
    public function __construct(string $message = 'Business area yang dipilih tidak sesuai dengan akses Anda.')
    {
        parent::__construct($message);
    }
}
