<?php

namespace App\Livewire\Settings;

use App\Exceptions\OldPasswordIncorrectException;
use App\Exceptions\PasswordConfirmationMismatchException;
use App\Services\AuthService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ChangePasswordForm — screen-003--ganti-password-web /
 * usecase-003--ganti-password-web (Livewire web "Ganti Password").
 *
 * Reuses AuthService::changePassword() — the exact same business logic the
 * API controller (App\Http\Controllers\Api\AuthController::changePassword())
 * uses — so validation and error handling stay identical between the web
 * and API entry points (mirrors the App\Livewire\Auth\LoginForm pattern).
 *
 * Registered as a full-page component (`Route::get('/settings/password',
 * ChangePasswordForm::class)` in routes/web.php, guarded by 'auth' +
 * 'role:admin,supervisor,mill_management'), so it renders inside the
 * `settings.password` layout below (resources/views/settings/password.blade.php,
 * via {{ $slot }}).
 *
 * The requester IS the target user (self-service password change) — no
 * user id is ever passed in; auth()->user() is used directly.
 */
#[Layout('settings.password')]
class ChangePasswordForm extends Component
{
    public string $old_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /**
     * Mirrors AuthService::changePassword()'s business_logic step 1
     * (required fields) and step 2 (new_password format: min 6 chars,
     * alphanumeric + symbol) plus a client-side 'same' check for step 3
     * (confirmation match), so inline validation errors surface without a
     * round trip to the server. The server-side hash_equals() check in
     * AuthService remains the source of truth (defense in depth).
     */
    protected function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'regex:/[A-Za-z0-9]/', 'regex:/[^A-Za-z0-9]/'],
            'new_password_confirmation' => ['required', 'string', 'same:new_password'],
        ];
    }

    protected function messages(): array
    {
        return [
            'old_password.required' => 'Password lama wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal 6 karakter.',
            'new_password.regex' => 'Password baru harus mengandung kombinasi huruf/angka serta simbol.',
            'new_password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'new_password_confirmation.same' => 'Konfirmasi password tidak cocok dengan password baru.',
        ];
    }

    public function save(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        // Step 1 + 2 + 3 (client-side mirror of AuthService's own checks).
        $this->validate();

        // Resolved via the container rather than constructor/method
        // injection — Livewire action methods invoked from wire:submit are
        // called directly with only the front-end's arguments, so they are
        // not container-resolved the way controller methods are (mirrors
        // App\Livewire\Auth\LoginForm::login()).
        $authService = app(AuthService::class);

        try {
            $authService->changePassword(
                auth()->user(),
                $this->old_password,
                $this->new_password,
                $this->new_password_confirmation,
            );
        } catch (OldPasswordIncorrectException) {
            $this->old_password = '';
            $this->errorMessage = 'Password lama salah.';

            return;
        } catch (PasswordConfirmationMismatchException) {
            $this->new_password = '';
            $this->new_password_confirmation = '';
            $this->errorMessage = 'Konfirmasi password tidak cocok dengan password baru.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation caught something the client-side
            // rules() missed (defense in depth) — surface it the same way
            // Livewire surfaces its own validation errors.
            $this->new_password = '';
            $this->new_password_confirmation = '';

            throw $e;
        }

        // Step 6: success — reset the form and show a confirmation toast.
        $this->old_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->successMessage = 'Password berhasil diubah.';
    }

    public function render()
    {
        return view('livewire.settings.change-password-form');
    }
}
