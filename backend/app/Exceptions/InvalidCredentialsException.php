<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * InvalidCredentialsException — thrown by AuthService::login() when the
 * username is not found or the password does not match (screen-001--login-web,
 * usecase-001--login-web, business_logic step 3 → 401 INVALID_CREDENTIALS).
 *
 * Deliberately a plain HttpException (not Illuminate\Auth\AuthenticationException)
 * because ApiExceptionHandler hardcodes the message "Unauthenticated." for
 * AuthenticationException, which cannot carry our custom message. HttpException
 * is already rendered correctly via the handler's HttpExceptionInterface branch
 * (uses $e->getMessage()), so no ApiExceptionHandler changes were needed.
 */
class InvalidCredentialsException extends HttpException
{
    public function __construct(string $message = 'Username atau password salah.')
    {
        parent::__construct(401, $message);
    }
}
