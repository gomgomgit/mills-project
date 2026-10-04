<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * RouteAccess — apakah peran user saat ini boleh membuka sebuah rute
 * bernama, dibaca LANGSUNG dari middleware 'role:...' rute itu sendiri
 * (routes/web.php). Dipakai sidebar (components/layouts/app.blade.php)
 * supaya menu yang ditampilkan selalu sama dengan yang diizinkan
 * EnsureRole — daftar peran tidak ditulis dua kali, jadi tidak bisa
 * menyimpang ketika guard sebuah rute diubah.
 *
 * Rute tanpa middleware 'role:' dianggap terbuka untuk siapa pun yang
 * sudah login. Nama rute yang tidak terdaftar → false (menu disembunyikan,
 * bukan RouteNotFoundException di tengah render shell).
 */
class RouteAccess
{
    /** @var array<string, list<string>|null> cache per proses: nama rute → daftar peran (null = tanpa batas peran) */
    protected static array $rolesByRoute = [];

    public static function allows(string $routeName, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (! array_key_exists($routeName, static::$rolesByRoute)) {
            static::$rolesByRoute[$routeName] = static::resolveRoles($routeName);
        }

        $roles = static::$rolesByRoute[$routeName];

        if ($roles === false) {
            return false;
        }

        if ($roles === null) {
            return true;
        }

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return in_array($role, $roles, true);
    }

    /**
     * Salah satu dari beberapa rute boleh dibuka (dipakai grup menu).
     */
    public static function allowsAny(array $routeNames, ?Authenticatable $user = null): bool
    {
        foreach ($routeNames as $name) {
            if (static::allows($name, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>|null|false daftar peran; null = tanpa 'role:'; false = rute tidak ada
     */
    protected static function resolveRoles(string $routeName): array|null|false
    {
        $route = Route::getRoutes()->getByName($routeName);

        if (! $route) {
            return false;
        }

        $roles = null;

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                $listed = array_values(array_filter(array_map('trim', explode(',', substr($middleware, 5)))));
                // Beberapa 'role:' bertumpuk = semuanya harus lolos → irisan.
                $roles = $roles === null ? $listed : array_values(array_intersect($roles, $listed));
            }
        }

        return $roles;
    }

    /**
     * Untuk test — rute bisa didaftarkan ulang di antara test.
     */
    public static function flush(): void
    {
        static::$rolesByRoute = [];
    }
}
