<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureRole — role-based route guard (auth-middleware, shared-modules).
 *
 * Every screen tech-spec's `actor_permissions` are role-scoped
 * (operator / supervisor / mill_management / admin — see App\Enums\UserRole),
 * so this middleware is the single shared enforcement point for role-gated
 * routes, on both the API (Sanctum token guard) and web (session guard).
 *
 * Must run after an auth guard (`auth:sanctum` for API, `auth` / the "web"
 * guard for Livewire) so that $request->user() is already resolved.
 *
 * Usage (registered per-screen in impl-2-screen):
 *   Route::get('/weighbridge-records', ...)->middleware('auth:sanctum', 'role:operator,supervisor');
 *   Route::get('/admin/users', ...)->middleware('auth', 'role:admin');
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthenticated($request);
        }

        $userRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        if (! in_array($userRole, $roles, true)) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    protected function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        abort(401);
    }

    protected function forbidden(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk aksi ini.'], 403);
        }

        abort(403);
    }
}
