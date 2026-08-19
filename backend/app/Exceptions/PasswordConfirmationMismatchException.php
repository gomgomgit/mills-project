<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PasswordConfirmationMismatchException — thrown by
 * AuthService::changePassword() when `new_password_confirmation` does not
 * match `new_password` (screen-003--ganti-password-web,
 * business_logic step 3 → 422 PASSWORD_CONFIRMATION_MISMATCH).
 *
 * Deliberately a plain HttpException (same shape as InvalidCredentialsException)
 * rather than Illuminate\Validation\ValidationException — this is a single,
 * non-field-keyed condition, not a Laravel validation ruleset failure, and
 * ApiExceptionHandler's HttpExceptionInterface branch already renders it
 * correctly as { "message": ... } with no `errors` key.
 */
class PasswordConfirmationMismatchException extends HttpException
{
    public function __construct(string $message = 'Konfirmasi password tidak cocok dengan password baru.')
    {
        parent::__construct(422, $message);
    }
}
