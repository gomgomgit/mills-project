<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * OldPasswordIncorrectException — thrown by AuthService::changePassword()
 * when `old_password` does not match the authenticated user's current
 * password hash (screen-003--ganti-password-web, business_logic step 4 →
 * 422 OLD_PASSWORD_INCORRECT).
 *
 * Deliberately a plain HttpException (same shape as InvalidCredentialsException)
 * rather than Illuminate\Validation\ValidationException — this is a single,
 * non-field-keyed condition, not a Laravel validation ruleset failure, and
 * ApiExceptionHandler's HttpExceptionInterface branch already renders it
 * correctly as { "message": ... } with no `errors` key.
 */
class OldPasswordIncorrectException extends HttpException
{
    public function __construct(string $message = 'Password lama salah.')
    {
        parent::__construct(422, $message);
    }
}
