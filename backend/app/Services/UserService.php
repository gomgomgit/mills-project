<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\CannotDeactivateSelfException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Support\Pagination;
use App\Support\PasswordPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
 *  - update() hanya menyentuh password_hash bila Admin mengisi kolom
 *    opsional "Reset Password" (2026-10-04) — kosong = password tidak
 *    berubah. Ganti password mandiri tetap domain screen-003/004.
 */
class UserService
{
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
     * invalid → update name/role/business_unit_id. `password` opsional =
     * Reset Password oleh Admin (2026-10-04): bila diisi, divalidasi dengan
     * PasswordPolicy lalu password_hash diganti; kosong = tidak berubah.
     * Dipakai layar KelolaUserRole DAN PATCH /api/users/{id} (sejak
     * 2026-10-05). Reset mencabut SEMUA token Sanctum dan sesi web akun itu
     * (keputusan 2026-10-05) — kecuali sesi web saat ini bila $actor
     * mereset password-nya sendiri. Lihat revokeWebSessions().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, ?User $actor = null): array
    {
        $user = User::findOrFail($id);

        $attributes = $this->normalize($data);

        $this->validate($attributes, $user->id, isCreate: false);

        $changes = [
            'name' => $attributes['name'],
            'role' => $attributes['role'],
            'business_unit_id' => $attributes['business_unit_id'],
        ];

        // Reset Password oleh Admin — opsional; kosong = tidak berubah.
        $passwordReset = $attributes['password'] !== '';

        if ($passwordReset) {
            $changes['password_hash'] = Hash::make($attributes['password']);
            $changes['sessions_revoked_at'] = now();
        }

        DB::transaction(function () use ($user, $changes, $passwordReset) {
            $user->forceFill($changes)->save();

            if ($passwordReset) {
                $user->tokens()->delete();
            }
        });

        if ($passwordReset) {
            $this->revokeWebSessions($user, $actor);
        }

        $user->load('businessUnit');

        return $this->toRow($user);
    }

    /**
     * Sesi web akun yang password-nya direset dicabut lewat
     * users.sessions_revoked_at (diperiksa EnsureUserIsActive pada request
     * berikutnya) — SESSION_DRIVER=file tidak bisa dihapus per user. Bila
     * driver-nya database, baris sesinya juga langsung dihapus.
     *
     * Pengecualian (keputusan 2026-10-05): Admin yang mereset password-nya
     * SENDIRI tetap memegang sesi web saat ini — stempel sesi ini diperbarui
     * sehingga tidak lebih tua dari waktu pencabutan. Sesinya di perangkat
     * lain tetap tercabut.
     */
    protected function revokeWebSessions(User $user, ?User $actor): void
    {
        // Store sesi aplikasi (instance yang sama dengan sesi request web /
        // Livewire yang sedang berjalan); tidak "started" di CLI/antrian.
        $session = app()->bound('session') ? app('session')->driver() : null;

        $keepCurrent = $actor !== null
            && $actor->getKey() === $user->getKey()
            && $session !== null
            && $session->isStarted();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($keepCurrent, fn ($q) => $q->where('id', '!=', $session->getId()))
                ->delete();
        }

        if ($keepCurrent) {
            $session->put(
                EnsureUserIsActive::SESSION_AUTH_AT,
                $user->sessions_revoked_at->getTimestampMs(),
            );
        }
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
            throw new CannotDeactivateSelfException;
        }

        $user->update(['is_active' => $isActive]);

        // Penonaktifan mencabut seluruh token Sanctum (sesi mobile) akun itu
        // seketika; sesi web-nya dikeluarkan EnsureUserIsActive pada request
        // berikutnya.
        if (! $isActive) {
            $user->tokens()->delete();
        }
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
            $rules['username'] = ['required', 'string', 'max:255', 'regex:/^\S+$/u', self::usernameUniqueRule($excludeId)];
            $rules['password'] = ['required', 'string', PasswordPolicy::rule()];
        } elseif ($attributes['password'] !== '') {
            // Reset Password (opsional) di Edit User — aturan yang SAMA
            // dengan login/Ganti Password.
            $rules['password'] = ['string', PasswordPolicy::rule()];
        }

        Validator::make(
            $attributes,
            $rules,
            [
                'username.required' => 'Username wajib diisi.',
                'username.max' => 'Username maksimal 255 karakter.',
                'username.regex' => 'Username tidak boleh mengandung spasi.',
                'name.required' => 'Nama wajib diisi.',
                'name.max' => 'Nama maksimal 255 karakter.',
                'role.required' => 'Role wajib dipilih.',
                'role.in' => 'Role yang dipilih tidak valid.',
                'business_unit_id.required' => 'Business Unit wajib dipilih untuk role selain Admin.',
                'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
                'password.required' => 'Password wajib diisi.',
            ]
        )->validate();
    }

    /**
     * Keunikan username TIDAK peka huruf besar/kecil ("OPERATOR01" dianggap
     * sama dengan "operator01"). lower() di kedua sisi — aman di SQLite
     * maupun PostgreSQL (Rule::unique membandingkan apa adanya, peka huruf
     * di PostgreSQL).
     */
    public static function usernameUniqueRule(?string $excludeId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($excludeId): void {
            $exists = User::query()
                ->whereRaw('lower(username) = ?', [mb_strtolower(trim((string) $value))])
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();

            if ($exists) {
                $fail('Username sudah digunakan.');
            }
        };
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
