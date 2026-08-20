<?php

namespace App\Services;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\BusinessAreaMismatchException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\OldPasswordIncorrectException;
use App\Exceptions\PasswordConfirmationMismatchException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * AuthService — screen-001--login-web / usecase-001--login-web AND
 * screen-002--login-mobile / usecase-002--login-mobile business logic.
 *
 * login() implements business_logic steps 1-5 identically for both screens
 * (required-field validation, password format, credential lookup, active
 * check, business-area check), then branches on the optional $deviceName
 * argument for steps 6-7:
 *   - $deviceName === null  → screen-001 (web): establish a Laravel session
 *     (Auth::login()), return { user, business_unit, redirect_to }.
 *   - $deviceName !== null  → screen-002 (mobile): issue a Sanctum personal
 *     access token named after $deviceName, return { user, business_unit,
 *     token }. No session is established for this path.
 *
 * Shared by the web Livewire login (App\Livewire\Auth\LoginForm, which
 * never passes $deviceName — always the web/session path) and the API
 * controller (App\Http\Controllers\Api\AuthController, which passes
 * $deviceName whenever the request body includes it), so the rules are
 * enforced identically regardless of entry point.
 */
class AuthService
{
    /**
     * Redirect path per role after a successful login (business_logic step 7).
     * Exact dashboard routes are not specified elsewhere in the tech-spec —
     * this mapping is a documented implementation decision. Originally
     * pointed at per-role placeholder routes (/admin/dashboard, etc.) that
     * were never registered, since the dashboard screens didn't exist yet —
     * now that screen-025--dashboard-web is implemented as the single
     * '/dashboard' route (role:admin,supervisor,mill_management), every web
     * login role redirects there. 'operator' is kept for completeness even
     * though screen-001 (web login) only accepts admin/supervisor/
     * mill_management — operator logs in via screen-002 (mobile), which
     * never reads redirect_to.
     */
    protected const ROLE_REDIRECTS = [
        'admin' => '/dashboard',
        'supervisor' => '/dashboard',
        'mill_management' => '/dashboard',
        'operator' => '/dashboard',
    ];

    /**
     * @return array{
     *     user: array{id: string, username: string, name: string, role: string, business_unit_id: ?string},
     *     business_unit: array{id: string, name: string},
     *     redirect_to?: string,
     *     token?: string,
     * }
     *
     * @throws ValidationException              (422 VALIDATION_ERROR — required fields / password format)
     * @throws InvalidCredentialsException       (401 INVALID_CREDENTIALS)
     * @throws AccountInactiveException          (403 ACCOUNT_INACTIVE)
     * @throws BusinessAreaMismatchException     (403 BUSINESS_AREA_MISMATCH)
     */
    public function login(string $username, string $password, string $businessUnitId, ?string $deviceName = null): array
    {
        // Step 1: required fields (+ device_name, only when the mobile
        // branch was selected by the caller passing a non-null $deviceName —
        // screen-001's callers never pass it, so their validation is
        // unaffected).
        $this->validateRequired($username, $password, $businessUnitId, $deviceName);

        // Step 2: password format — min 6 characters, alphanumeric + symbol.
        $this->validatePasswordFormat($password);

        // Step 3: lookup user by username + verify password.
        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
            throw new InvalidCredentialsException();
        }

        // Step 4: account must be active.
        if (! $user->is_active) {
            throw new AccountInactiveException();
        }

        // Step 5: business area access check. No many-to-many "accessible
        // business units" table exists in the entity catalog for this
        // screen, so "akses user" is interpreted as an exact match against
        // the user's own business_unit_id (nullable — a user with no
        // assigned business unit never matches).
        if (! $user->business_unit_id || (string) $user->business_unit_id !== (string) $businessUnitId) {
            throw new BusinessAreaMismatchException();
        }

        $businessUnit = $user->businessUnit;

        $userPayload = [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role->value,
            // Mobile's AuthUser type (mobile/src/stores/auth.ts) declares
            // this field and screen-006--station-list's StationListView.vue
            // reads it directly (authStore.currentUser?.business_unit_id)
            // to scope its local station query — omitting it here silently
            // broke that screen for every real login (empty grid, no error
            // shown, since the view treats a missing id as "nothing to load
            // yet" rather than a failure). Found via real E2E browser
            // testing, not by inspection.
            'business_unit_id' => $user->business_unit_id,
        ];

        $businessUnitPayload = [
            'id' => $businessUnit->id,
            'name' => $businessUnit->name,
        ];

        if ($deviceName !== null) {
            // Step 6 (mobile, screen-002): issue a Sanctum personal access
            // token named after the device — no session is established for
            // this stateless/offline-capable client.
            $token = $user->createToken($deviceName)->plainTextToken;

            // Step 7 (mobile): success response with token.
            return [
                'user' => $userPayload,
                'business_unit' => $businessUnitPayload,
                'token' => $token,
            ];
        }

        // Step 6 (web, screen-001): establish the web session.
        Auth::login($user);

        // Step 7 (web): success response, redirect_to per role.
        return [
            'user' => $userPayload,
            'business_unit' => $businessUnitPayload,
            'redirect_to' => $this->redirectFor($user->role->value),
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function validateRequired(string $username, string $password, string $businessUnitId, ?string $deviceName = null): void
    {
        $data = [
            'username' => $username,
            'password' => $password,
            'business_unit_id' => $businessUnitId,
        ];

        $rules = [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'business_unit_id' => ['required', 'string'],
        ];

        // device_name is only required when the caller selected the mobile
        // branch (i.e. passed a non-null value, even '' — an explicitly
        // empty device_name is still a validation failure, not a silent
        // fallback to the web/session branch). screen-001 callers never
        // pass $deviceName, so this rule never applies to them.
        if ($deviceName !== null) {
            $data['device_name'] = $deviceName;
            $rules['device_name'] = ['required', 'string'];
        }

        Validator::make(
            $data,
            $rules,
            [
                'required' => 'Field :attribute wajib diisi.',
            ]
        )->validate();
    }

    /**
     * Min 6 characters, must contain at least one alphanumeric character and
     * at least one symbol (any non-alphanumeric character).
     *
     * $field selects which key the ValidationException is keyed under —
     * 'password' for login() (unchanged), 'new_password' for
     * changePassword() (screen-003--ganti-password-web business_logic step 2).
     *
     * @throws ValidationException
     */
    protected function validatePasswordFormat(string $password, string $field = 'password'): void
    {
        $hasMinLength = mb_strlen($password) >= 6;
        $hasAlphanumeric = (bool) preg_match('/[A-Za-z0-9]/', $password);
        $hasSymbol = (bool) preg_match('/[^A-Za-z0-9]/', $password);

        if (! $hasMinLength || ! $hasAlphanumeric || ! $hasSymbol) {
            throw ValidationException::withMessages([
                $field => ['Password minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.'],
            ]);
        }
    }

    protected function redirectFor(string $role): string
    {
        return self::ROLE_REDIRECTS[$role] ?? '/dashboard';
    }

    /**
     * changePassword() — screen-003--ganti-password-web /
     * usecase-003--ganti-password-web business_logic steps 1-6.
     *
     * $user is the already-authenticated requester (Auth::user() /
     * $request->user()) — this is a self-service password change, so there
     * is deliberately no separate user lookup by id (auth_requirement:
     * authenticated, actor_permissions: admin/supervisor/mill_management,
     * all allowed with no extra conditions beyond being authenticated —
     * enforced at the route layer via the 'role' middleware, not here).
     *
     * Shared by the API controller (App\Http\Controllers\Api\AuthController::changePassword())
     * and the web Livewire component (App\Livewire\Settings\ChangePasswordForm),
     * so both entry points enforce the exact same rules.
     *
     * implementation_notes: whether to invalidate other active sessions
     * after a password change is an open question in the tech-spec — this
     * implementation assumes existing sessions remain valid (no forced
     * re-login), per the tech-spec's documented assumption.
     *
     * @throws ValidationException                  (422 VALIDATION_ERROR — required fields / new_password format)
     * @throws PasswordConfirmationMismatchException (422 PASSWORD_CONFIRMATION_MISMATCH)
     * @throws OldPasswordIncorrectException         (422 OLD_PASSWORD_INCORRECT)
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword, string $newPasswordConfirmation): void
    {
        // Step 1: required fields.
        $this->validateChangePasswordRequired($oldPassword, $newPassword, $newPasswordConfirmation);

        // Step 2: new_password format — min 6 characters, alphanumeric + symbol.
        $this->validatePasswordFormat($newPassword, 'new_password');

        // Step 3: new_password_confirmation must match new_password.
        if (! hash_equals($newPassword, $newPasswordConfirmation)) {
            throw new PasswordConfirmationMismatchException();
        }

        // Step 4: old_password must match the user's current password hash.
        if (! Hash::check($oldPassword, $user->getAuthPassword())) {
            throw new OldPasswordIncorrectException();
        }

        // Step 5: persist the new password hash.
        $user->password_hash = Hash::make($newPassword);
        $user->save();

        // Step 6 (success response) is built by the caller (controller /
        // Livewire component) — this method returns void on success.
    }

    /**
     * @throws ValidationException
     */
    protected function validateChangePasswordRequired(string $oldPassword, string $newPassword, string $newPasswordConfirmation): void
    {
        Validator::make(
            [
                'old_password' => $oldPassword,
                'new_password' => $newPassword,
                'new_password_confirmation' => $newPasswordConfirmation,
            ],
            [
                'old_password' => ['required', 'string'],
                'new_password' => ['required', 'string'],
                'new_password_confirmation' => ['required', 'string'],
            ],
            [
                'required' => 'Field :attribute wajib diisi.',
            ]
        )->validate();
    }
}
