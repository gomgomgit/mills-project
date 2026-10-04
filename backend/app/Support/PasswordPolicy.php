<?php

namespace App\Support;

/**
 * PasswordPolicy — SATU-SATUNYA definisi format password (entity-catalog
 * `user.password_hash`: "minimal 6 karakter, case-sensitive,
 * alfanumerik+simbol"). Dipakai login, Ganti Password (AuthService) dan
 * Kelola User (UserService: buat user + reset password) supaya tidak ada
 * lagi user yang dibuat dengan password yang lolos di Kelola User (dulu
 * cuma `min:6`) tetapi ditolak di login — akun yang tidak akan pernah bisa
 * masuk.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 6;

    public const MESSAGE = 'Password minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.';

    public static function isValid(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH
            && (bool) preg_match('/[A-Za-z0-9]/', $password)
            && (bool) preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * Aturan validator Laravel (closure) dengan pesan Indonesia di atas.
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! self::isValid($value)) {
                $fail(self::MESSAGE);
            }
        };
    }
}
