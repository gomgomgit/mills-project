<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuthController — screen-001--login-web / usecase-001--login-web AND
 * screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Both screens share the single POST /api/login endpoint (auth_required:
 * false). Which branch runs is decided purely by whether the request body
 * includes a `device_name` key:
 *   - absent → screen-001 web/session branch (unchanged from before
 *     screen-002 existed).
 *   - present (even '') → screen-002 mobile/token branch.
 *
 * Request shape validation happens in AuthService::login() (surfaced as 422
 * VALIDATION_ERROR by the shared ApiExceptionHandler); business rule
 * validation and the actual login flow live there too, so the web Livewire
 * component (App\Livewire\Auth\LoginForm, which never passes device_name)
 * reuses the exact same step 1-5 logic as this controller.
 */
class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function login(Request $request): JsonResponse
    {
        $username = (string) $request->input('username');
        $password = (string) $request->input('password');
        // Optional as of this session: a user with their own business_unit_id
        // assigned (operator/supervisor/mill_management) no longer needs to
        // pick one — AuthService::login() auto-derives it from the account
        // when omitted. Admin (no assigned business_unit_id) must still send
        // one explicitly. $request->filled() (not ->has()) so an
        // empty-string value is also treated as "omitted", not as a literal
        // business_unit_id to match against.
        $businessUnitId = $request->filled('business_unit_id') ? (string) $request->input('business_unit_id') : null;

        // Presence (not truthiness) of `device_name` in the payload selects
        // the mobile/token branch — see AuthService::login()'s $deviceName
        // param and AuthService::validateRequired().
        $isMobile = $request->has('device_name');
        $deviceName = $isMobile ? (string) $request->input('device_name') : null;

        $result = $this->authService->login($username, $password, $businessUnitId, $deviceName);

        if (! $isMobile) {
            // Web/session path only (screen-001) — regenerate the session
            // id post-auth as a session-fixation safeguard. The
            // mobile/token path (screen-002) never establishes a session,
            // so there is nothing to regenerate.
            $request->session()->regenerate();
        }

        return response()->json($result);
    }

    /**
     * changePassword() — screen-003--ganti-password-web /
     * usecase-003--ganti-password-web AND screen-004--ganti-password-mobile
     * / usecase-004--ganti-password-mobile. Self-service password change:
     * the requester IS the target user, so this uses $request->user()
     * directly rather than looking up a user by id. Delegates to
     * AuthService::changePassword() so the exact same rules are enforced
     * here and in the Livewire settings form (App\Livewire\Settings\ChangePasswordForm).
     *
     * $request->user() is called with NO explicit guard name (unlike the
     * original screen-003-only implementation, which passed 'web') — the
     * route's 'auth:web,sanctum' middleware (see routes/api.php's merge
     * decision comment) authenticates via whichever of the two guards
     * succeeds and pins it as the request's default guard
     * (Auth::shouldUse()), so an unqualified $request->user() correctly
     * resolves the session-authenticated web user (screen-003) or the
     * Sanctum-token-authenticated mobile user (screen-004).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $oldPassword = (string) $request->input('old_password');
        $newPassword = (string) $request->input('new_password');
        $newPasswordConfirmation = (string) $request->input('new_password_confirmation');

        $this->authService->changePassword(
            $request->user(),
            $oldPassword,
            $newPassword,
            $newPasswordConfirmation,
        );

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }
}
