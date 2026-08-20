<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CannotDeactivateSelfException — thrown by UserService::setStatus() when
 * the acting Admin tries to deactivate (is_active=false) their own user
 * account (screen-032--kelola-user-role, usecase-032--kelola-user-role,
 * business_logic step "status" → 409 CANNOT_DEACTIVATE_SELF guard).
 *
 * Mirrors App\Exceptions\BusinessUnitHasStationsException exactly: a
 * plain HttpException (not Illuminate\Validation\ValidationException)
 * since this is a single, non-field-keyed condition (a safety guard, not
 * a form validation failure) — ApiExceptionHandler's HttpExceptionInterface
 * fallback branch already renders any status code generically as
 * { "message": ... } with no `errors` key, which covers 409 here.
 */
class CannotDeactivateSelfException extends HttpException
{
    public function __construct(string $message = 'Anda tidak dapat menonaktifkan akun Anda sendiri.')
    {
        parent::__construct(409, $message);
    }
}
