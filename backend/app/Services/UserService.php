<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\CannotDeactivateSelfException;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UserService — screen-032--kelola-user-role / usecase-032--kelola-user-role
 * (Kelola User & Role — admin-only CRUD over App\Models\User, the same
 * table `auth:web`/`auth:sanctum` authenticate against).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * UserController) and the Livewire component (App\Livewire\
 * UserManagement\KelolaUserRole) — same code path, so validation/business
 * rules stay identical between the two entry points, mirroring every
 * other master-data service in this codebase (BusinessUnitService et al.).
 *
 * Two divergences from the master-data CRUD services this otherwise
 * mirrors:
 *
 *  - No delete() at all — users are never removed, only deactivated
 *    (is_active), to preserve referential integrity on created_by/
 *    checked_by/acknowledged_by across every other entity. See
 *    setStatus() instead of a destroy()-style method.
 *  - update() never touches password_hash — password changes are the
 *    self-service Ganti Password screen's (screen-003/004) domain, not
 *    this screen's.
 */
class UserService
{
    /**
     * Minimum password length — entity-catalog `user.password_hash`:
     * "minimal 6 karakter, case-sensitive, alfanumerik+simbol sebelum
     * di-hash".
     */
    protected const PASSWORD_MIN_LENGTH = 6;

    /**
     * listUsers() — business_logic step "list": paginate, optional
     * role/business_unit_id filters, eager-load businessUnit (for
     * business_unit_name). password_hash is never exposed — the User
     * model already hides it ($hidden), and toRow() below never reads it.
     */
    public function listUsers(int $page, int $perPage, ?string $role = null, ?string $businessUnitId = null): array
    {
        $query = User::query()
            ->with('businessUnit')
            ->orderBy('name');

        if ($role !== null && $role !== '') {
            $query->where('role', $role);
        }

        if ($businessUnitId !== null && $businessUnitId !== '') {
            $query->where('business_unit_id', $businessUnitId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (User $user) => $this->toRow($user))
            ->all();

        return $formatted;
    }

    /**
     * create() — business_logic step "create": validate username
     * required+unique → validate name required → validate role required
     * (enum) → validate business_unit_id required unless role=admin, must
     * be an existing Business Unit → validate password required+min
     * length → 422 if any invalid → hash password → insert User with
     * is_active=true.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): array
    {
        $attributes = $this->normalize($data);

        $this->validate($attributes, null, isCreate: true);

        $user = User::create([
            'username' => $attributes['username'],
            'password_hash' => Hash::make($attributes['password']),
            'name' => $attributes['name'],
            'role' => $attributes['role'],
            'business_unit_id' => $attributes['business_unit_id'],
            'is_active' => true,
        ]);
        $user->load('businessUnit');

        return $this->toRow($user);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → validate name required → validate role required →
     * validate business_unit_id required unless role=admin → 422 if any
     * invalid → update name/role/business_unit_id. password_hash is
     * NEVER touched here — no password field accepted by this method at
     * all (see class docblock).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data): array
    {
        $user = User::findOrFail($id);

        $attributes = $this->normalize($data);

        $this->validate($attributes, $user->id, isCreate: false);

        $user->update([
            'name' => $attributes['name'],
            'role' => $attributes['role'],
            'business_unit_id' => $attributes['business_unit_id'],
        ]);
        $user->load('businessUnit');

        return $this->toRow($user);
    }

    /**
     * setStatus() — business_logic step "status": validate id exists →
     * 404 if not → if $isActive is false AND $id matches the acting
     * user's own id → 409 CANNOT_DEACTIVATE_SELF (reactivating one's own
     * account, is_active=true, is NOT blocked) → else update is_active.
     *
     * @throws ModelNotFoundException
     * @throws CannotDeactivateSelfException
     */
    public function setStatus(string $id, bool $isActive, string $actingUserId): array
    {
        $user = User::findOrFail($id);

        if (! $isActive && $id === $actingUserId) {
            throw new CannotDeactivateSelfException();
        }

        $user->update(['is_active' => $isActive]);
        $user->load('businessUnit');

        return $this->toRow($user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        return [
            'username' => trim((string) ($data['username'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'role' => trim((string) ($data['role'] ?? '')),
            'business_unit_id' => array_key_exists('business_unit_id', $data) && $data['business_unit_id'] !== ''
                ? trim((string) $data['business_unit_id'])
                : null,
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /**
     * Validates username/name/role/business_unit_id (+ password on
     * create) in a single Validator pass, so a 422 response carries every
     * invalid field's errors at once — matches
     * shared_decisions.error_format's `{ message, errors: { field: [...] } }`
     * shape.
     *
     * `business_unit_id` is required unless role=admin — implemented via
     * a conditional Rule::requiredIf() closure rather than a plain
     * `required`, since the requirement depends on the sibling `role`
     * field's value.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validate(array $attributes, ?string $excludeId, bool $isCreate): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'business_unit_id' => [
                Rule::requiredIf(fn () => $attributes['role'] !== UserRole::Admin->value),
                'nullable',
                'string',
                Rule::exists('business_units', 'id'),
            ],
        ];

        // username is only ever set on create() — update() never accepts or
        // changes it (see class docblock), so its uniqueness/required rule
        // only applies here; validating it on update() would incorrectly
        // fail since update()'s $data never carries a 'username' key.
        if ($isCreate) {
            $usernameUniqueRule = Rule::unique('users', 'username');

            if ($excludeId !== null) {
                $usernameUniqueRule = $usernameUniqueRule->ignore($excludeId);
            }

            $rules['username'] = ['required', 'string', 'max:255', $usernameUniqueRule];
            $rules['password'] = ['required', 'string', 'min:'.self::PASSWORD_MIN_LENGTH];
        }

        Validator::make(
            $attributes,
            $rules,
            [
                'username.required' => 'Username wajib diisi.',
                'username.max' => 'Username maksimal 255 karakter.',
                'username.unique' => 'Username sudah digunakan.',
                'name.required' => 'Nama wajib diisi.',
                'name.max' => 'Nama maksimal 255 karakter.',
                'role.required' => 'Role wajib dipilih.',
                'role.in' => 'Role yang dipilih tidak valid.',
                'business_unit_id.required' => 'Business Unit wajib dipilih untuk role selain Admin.',
                'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
                'password.required' => 'Password wajib diisi.',
                'password.min' => 'Password minimal '.self::PASSWORD_MIN_LENGTH.' karakter.',
            ]
        )->validate();
    }

    /**
     * Maps a User (with businessUnit eager-loaded) to the endpoints'
     * shared row shape. password_hash is never included — the User model
     * already hides it via $hidden, and it is intentionally not read here
     * either.
     */
    protected function toRow(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
            'business_unit_id' => $user->business_unit_id,
            'business_unit_name' => optional($user->businessUnit)->name,
            'is_active' => (bool) $user->is_active,
            'created_at' => optional($user->created_at)->toIso8601String(),
        ];
    }
}
