<?php

namespace App\Livewire\UserManagement;

use App\Enums\UserRole;
use App\Exceptions\CannotDeactivateSelfException;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * Validasi SENGAJA tidak diduplikasi di komponen ini lagi (dulu ada
     * rules() sendiri dengan password `min:6` yang menyimpang dari aturan
     * login → user yang tidak pernah bisa masuk). Seluruh aturan hidup di
     * UserService::validate(); save() memetakan ValidationException-nya ke
     * kunci form.* dengan pesan yang sama.
     */
    public ?string $successMessage = null;

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
        $this->successMessage = null;
        $this->statusErrorMessage = null;
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
        $this->successMessage = null;
        $this->statusErrorMessage = null;
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
     * required; on update, form.password = kolom opsional "Reset
     * Password" (kosong = tidak diubah).
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->resetValidation();

        $service = app(UserService::class);

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $this->form, auth()->user());
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

        $wasEditing = $this->editingId !== null;
        $passwordReset = $wasEditing && $this->form['password'] !== '';

        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->resetValidation();
        $this->successMessage = ! $wasEditing
            ? 'User berhasil ditambahkan.'
            : ($passwordReset ? 'User berhasil diperbarui dan password telah direset.' : 'User berhasil diperbarui.');
    }

    /**
     * "Aktifkan"/"Nonaktifkan" row action — business_logic step "status":
     * validate id exists → 404 if not → 409 CANNOT_DEACTIVATE_SELF if
     * deactivating the acting admin's own account → else toggle
     * is_active. Nonaktifkan meminta konfirmasi (wire:confirm di view) —
     * akun itu langsung dikeluarkan dari sesi web & mobile-nya.
     */
    public function toggleStatus(string $id, bool $newIsActive): void
    {
        $this->statusErrorMessage = null;
        $this->successMessage = null;

        $service = app(UserService::class);

        try {
            $service->setStatus($id, $newIsActive, (string) auth()->id());
            $this->successMessage = $newIsActive ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.';
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
