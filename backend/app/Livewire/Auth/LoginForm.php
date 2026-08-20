<?php

namespace App\Livewire\Auth;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\BusinessAreaMismatchException;
use App\Exceptions\InvalidCredentialsException;
use App\Services\AuthService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * LoginForm — screen-001--login-web / usecase-001--login-web (Livewire web
 * login). Reuses AuthService::login() — the exact same business logic the
 * API controller uses — so validation and error handling stay identical
 * between the web and API entry points.
 *
 * Registered as a full-page component (`Route::get('/login', LoginForm::class)`
 * in routes/web.php), so it renders inside the `auth.login` layout below
 * (resources/views/auth/login.blade.php, via {{ $slot }}) rather than being
 * embedded with a `<livewire:auth.login-form />` tag from a separate page —
 * that's the correct Livewire 3 pattern for a component that *is* the route
 * target. See implementation_notes for details.
 */
#[Layout('auth.login')]
class LoginForm extends Component
{
    public string $username = '';

    public string $password = '';

    public ?string $errorMessage = null;

    /**
     * Mirrors AuthService's business_logic step 1 (required fields) and
     * step 2 (password format: min 6 chars, alphanumeric + symbol) so the
     * component_test "Format Password Tidak Valid" scenario gets an inline
     * validation error client-side, without a round trip to the server.
     */
    protected function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'regex:/[A-Za-z0-9]/', 'regex:/[^A-Za-z0-9]/'],
        ];
    }

    protected function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.regex' => 'Password harus mengandung kombinasi huruf/angka serta simbol.',
        ];
    }

    public function login(): void
    {
        $this->errorMessage = null;

        // Step 1 + 2 (client-side mirror of AuthService's own checks).
        $this->validate();

        // Resolved via the container rather than constructor/method
        // injection — Livewire action methods invoked from wire:submit are
        // called directly with only the front-end's arguments, so they are
        // not container-resolved the way controller methods are.
        $authService = app(AuthService::class);

        try {
            $result = $authService->login($this->username, $this->password);
        } catch (InvalidCredentialsException) {
            $this->password = '';
            $this->errorMessage = 'Username atau password salah.';

            return;
        } catch (AccountInactiveException) {
            $this->password = '';
            $this->errorMessage = 'Akun tidak aktif, hubungi Admin.';

            return;
        } catch (BusinessAreaMismatchException) {
            $this->password = '';
            $this->errorMessage = 'Business area yang dipilih tidak sesuai dengan akses Anda.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation caught something the client-side
            // rules() missed (defense in depth) — surface it the same way
            // Livewire surfaces its own validation errors.
            $this->password = '';
            throw $e;
        }

        // Step 6 already ran inside AuthService::login() (Auth::login()).
        // Regenerate the session id post-authentication as an extra
        // session-fixation safeguard.
        session()->regenerate();

        $this->redirect($result['redirect_to'], navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
