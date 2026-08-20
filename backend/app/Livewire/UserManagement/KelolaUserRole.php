<?php

namespace App\Livewire\UserManagement;

use App\Enums\UserRole;
use App\Exceptions\CannotDeactivateSelfException;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * KelolaUserRole — screen-032--kelola-user-role / usecase-032--kelola-user-role
 * (Livewire web "Kelola User & Role", route name `users.index`, /users).
 *
 * Reuses UserService — the exact same service the API controller
 * (App\Http\Controllers\Api\UserController) uses — so validation and
 * business rules stay identical between the web and API entry points.
 * Mirrors App\Livewire\MasterData\KelolaBusinessUnit's structure, with
 * two divergences:
 *
 *  - No file upload (no WithFileUploads trait) — this screen has no logo.
 *  - No delete/confirmingDeleteId flow — replaced by toggleStatus(), a
 *    single-click action (no inline confirmation needed since it is not
 *    a destructive/permanent action, unlike delete on the master-data
 *    screens).
 *
 * Access control: route-level only. routes/web.php guards /users with
 * 'auth' + 'role:admin' — EnsureRole::forbidden() aborts(403) before this
 * component ever mounts for a non-admin session, same as every other
 * master-data screen.
 */
#[Layout('user-management.users')]
class KelolaUserRole extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    public string $filterRole = '';

    public string $filterBusinessUnitId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    /** @var array<string, string> */
    public array $form = [
        'username' => '',
        'name' => '',
        'role' => '',
        'business_unit_id' => '',
        'password' => '',
    ];

    public ?string $formErrorMessage = null;

    public ?string $statusErrorMessage = null;

    public function updatedFilterRole(): void
    {
        $this->page = 1;
    }

    public function updatedFilterBusinessUnitId(): void
    {
        $this->page = 1;
    }

    /**
     * Client-side mirror of UserService::validate() (defense in depth —
     * same rule set, same DB hits via Rule::exists/Rule::unique).
     * `business_unit_id` is only required when the selected role isn't
     * Admin, mirroring the service's Rule::requiredIf() exactly.
     */
    protected function rules(): array
    {
        $usernameUniqueRule = Rule::unique('users', 'username');

        if ($this->editingId !== null) {
            $usernameUniqueRule = $usernameUniqueRule->ignore($this->editingId);
        }

        $rules = [
            'form.username' => ['required', 'string', 'max:255', $usernameUniqueRule],
            'form.name' => ['required', 'string', 'max:255'],
            'form.role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'form.business_unit_id' => [
                Rule::requiredIf(fn () => $this->form['role'] !== UserRole::Admin->value),
                'nullable',
                'string',
                Rule::exists('business_units', 'id'),
            ],
        ];

        if ($this->editingId === null) {
            $rules['form.password'] = ['required', 'string', 'min:6'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'form.username.required' => 'Username wajib diisi.',
            'form.username.max' => 'Username maksimal 255 karakter.',
            'form.username.unique' => 'Username sudah digunakan.',
            'form.name.required' => 'Nama wajib diisi.',
            'form.name.max' => 'Nama maksimal 255 karakter.',
            'form.role.required' => 'Role wajib dipilih.',
            'form.role.in' => 'Role yang dipilih tidak valid.',
            'form.business_unit_id.required' => 'Business Unit wajib dipilih untuk role selain Admin.',
            'form.business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'form.password.required' => 'Password wajib diisi.',
            'form.password.min' => 'Password minimal 6 karakter.',
        ];
    }

    protected function emptyForm(): array
    {
        return [
            'username' => '',
            'name' => '',
            'role' => '',
            'business_unit_id' => '',
            'password' => '',
        ];
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    public function openEditForm(string $id): void
    {
        $user = User::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $user->id;
        $this->form = [
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
            'business_unit_id' => (string) ($user->business_unit_id ?? ''),
            'password' => '',
        ];
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set. On
     * create, role != admin resets business_unit_id validation to
     * required; on update, password is never sent (this form's edit mode
     * has no password field at all — see the view).
     */
    public function save(): void
    {
        $this->formErrorMessage = null;

        $this->validate();

        $service = app(UserService::class);

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $this->form);
            } else {
                $service->create($this->form);
            }
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'User tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("form.$field", $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    /**
     * "Aktifkan"/"Nonaktifkan" row action — business_logic step "status":
     * validate id exists → 404 if not → 409 CANNOT_DEACTIVATE_SELF if
     * deactivating the acting admin's own account → else toggle
     * is_active. No confirmation dialog (not a destructive/permanent
     * action, unlike delete on the master-data screens).
     */
    public function toggleStatus(string $id, bool $newIsActive): void
    {
        $this->statusErrorMessage = null;

        $service = app(UserService::class);

        try {
            $service->setStatus($id, $newIsActive, (string) auth()->id());
        } catch (CannotDeactivateSelfException $e) {
            $this->statusErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->statusErrorMessage = 'User tidak ditemukan, mungkin sudah dihapus.';
        }
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function render()
    {
        $service = app(UserService::class);

        $result = $service->listUsers(
            $this->page,
            $this->perPage,
            $this->filterRole !== '' ? $this->filterRole : null,
            $this->filterBusinessUnitId !== '' ? $this->filterBusinessUnitId : null,
        );

        return view('livewire.user-management.kelola-user-role', [
            'users' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
            'roleOptions' => UserRole::cases(),
            'currentUserId' => (string) auth()->id(),
        ]);
    }
}
