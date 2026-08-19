<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * AccountInactiveException — thrown by AuthService::login() when the user
 * account is found but `is_active` is false (screen-001--login-web,
 * usecase-001--login-web, business_logic step 4 → 403 ACCOUNT_INACTIVE).
 *
 * Extends Illuminate\Auth\Access\AuthorizationException, which
 * ApiExceptionHandler already renders as 403 using $e->getMessage() — no
 * handler changes needed.
 */
class AccountInactiveException extends AuthorizationException
{
    public function __construct(string $message = 'Akun tidak aktif, hubungi Admin.')
    {
        parent::__construct($message);
    }
}
