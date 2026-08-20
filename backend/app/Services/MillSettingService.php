<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\Station;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MillSettingService — screen-034--mills-setting /
 * usecase-034--mills-setting (Mills Setting — Admin + Mill Management,
 * per-mill app branding + per-station icon override).
 *
 * `jumlah_cages` was REMOVED 2026-08-20 (entity-catalog v9) — see
 * App\Models\MillSetting's docblock and
 * App\Services\CagesTrackRecordService::machineryCountForStation().
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * MillSettingController) and the Livewire component (App\Livewire\
 * Settings\MillsSetting), mirroring every other screen in this codebase.
 *
 * Two divergences from the master-data services (Corporate/Company/
 * BusinessUnit/Station/Machinery) this class otherwise mirrors:
 *
 *  - There is no create()/delete() — a MillSetting row is a lazily
 *    auto-created 1:1 companion to a BusinessUnit (see getOrCreate()),
 *    never explicitly created or deleted through this screen.
 *  - Every method takes the acting User and enforces ownership scoping
 *    (checkAccess()) BEFORE touching any MillSetting/Station row — Admin
 *    may act on any business_unit_id, Mill Management only on their own
 *    (user.business_unit_id). This is the only screen in this codebase
 *    with per-resource (not just per-role) access control, so it's
 *    implemented once here rather than copied from an existing pattern.
 */
class MillSettingService
{
    /**
     * Disk for logo/home_page_image uploads — same convention as
     * BusinessUnitService::LOGO_DISK / CorporateService::LOGO_DISK
     * ('local' disk has 'serve' => true, auto-registers /storage/{path},
     * no `public` disk / storage:link needed).
     */
    public const LOGO_DISK = 'local';

    protected const LOGO_DIRECTORY = 'mill-setting-logos';

    protected const HOME_IMAGE_DIRECTORY = 'mill-setting-home-images';

    /**
     * Fixed allow-list of supported station icon names — screen-034 v2
     * tech-spec's REVISI: `station.icon` is a picker over a bounded set
     * of Lucide icon names, not a file upload. Matches the default icons
     * already used by station-tile (gauge/layers/package, see uiux-spec
     * component_patterns) plus a few additional mill/industrial-relevant
     * names for variety. Extensible later without a migration (plain
     * string column) if the picker's option list needs to grow.
     */
    public const SUPPORTED_ICONS = [
        'gauge', 'layers', 'package', 'truck', 'scale',
        'warehouse', 'factory', 'container', 'box', 'boxes',
    ];

    /**
     * checkAccess() — enforced at the START of every public method below
     * (GET and mutating alike, per tech-spec's actor_permissions: Mill
     * Management is scoped even for read access, not just writes).
     * Admin bypasses entirely. Mill Management is rejected the moment
     * $businessUnitId != $user->business_unit_id, before any
     * MillSetting/Station query runs — so a rejected request never
     * leaks whether a MillSetting row exists for that other mill.
     *
     * @throws AuthorizationException
     */
    protected function checkAccess(User $user, string $businessUnitId): void
    {
        if ($user->role === UserRole::Admin) {
            return;
        }

        if ($user->role === UserRole::MillManagement && $user->business_unit_id === $businessUnitId) {
            return;
        }

        throw new AuthorizationException('Anda tidak memiliki akses untuk mengatur mill ini.');
    }

    /**
     * getOrCreate() — business_logic step 3: validate business_unit_id
     * exists → 404 if not → SELECT mill-setting WHERE business_unit_id →
     * if none, INSERT default (app_name = business_unit.name) and return
     * the newly created row.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function getOrCreate(User $user, string $businessUnitId): array
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);

        $this->checkAccess($user, $businessUnitId);

        $millSetting = $this->findOrCreateRow($businessUnit);

        return $this->toRow($millSetting);
    }

    /**
     * update() — business_logic step 4: validate logo/home_page_image
     * format+size (if sent) → 422 if invalid → get-or-create the row
     * (step 3) → store any uploaded files → UPDATE.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(
        User $user,
        string $businessUnitId,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $homePageImage = null,
    ): array {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);

        $this->checkAccess($user, $businessUnitId);

        $payload = [
            'app_name' => array_key_exists('app_name', $data) ? trim((string) $data['app_name']) : null,
            'logo' => $logo,
            'home_page_image' => $homePageImage,
        ];

        Validator::make($payload, [
            'app_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'home_page_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'app_name.max' => 'Nama aplikasi maksimal 255 karakter.',
            'logo.file' => 'Logo harus berupa file.',
            'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'home_page_image.file' => 'Gambar halaman utama harus berupa file.',
            'home_page_image.mimes' => 'Gambar halaman utama harus berformat JPG atau PNG.',
            'home_page_image.max' => 'Ukuran gambar halaman utama maksimal 2MB.',
        ])->validate();

        $millSetting = $this->findOrCreateRow($businessUnit);

        $attributes = [];

        if (array_key_exists('app_name', $data) && trim((string) $data['app_name']) !== '') {
            $attributes['app_name'] = trim((string) $data['app_name']);
        }

        if ($logo !== null) {
            $attributes['logo'] = $this->storeFile($logo, self::LOGO_DIRECTORY);
        }

        if ($homePageImage !== null) {
            $attributes['home_page_image'] = $this->storeFile($homePageImage, self::HOME_IMAGE_DIRECTORY);
        }

        $attributes['updated_by'] = $user->id;

        $millSetting->update($attributes);

        return $this->toRow($millSetting);
    }

    /**
     * listStations() — business_logic step 5: validate business_unit_id
     * exists → SELECT station WHERE business_unit_id, ordered by name.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function listStations(User $user, string $businessUnitId): array
    {
        BusinessUnit::findOrFail($businessUnitId);

        $this->checkAccess($user, $businessUnitId);

        return $this->mapStations($businessUnitId);
    }

    /**
     * getCurrent() — GET /api/mill-settings/current (screen-005--home,
     * screen-012--form-cages-track mobile consumers). Self-scoped to the
     * acting user's own business_unit_id — no :business_unit_id param, no
     * Admin/Mill-Management role restriction (any authenticated mobile
     * role — operator/supervisor — may read their own mill's setting).
     * checkAccess() is deliberately NOT called here: self-scoping via
     * $user->business_unit_id already IS the access control, and
     * operator/supervisor are not accepted by checkAccess() at all.
     *
     * @throws ModelNotFoundException if the user has no business_unit_id
     */
    public function getCurrent(User $user): array
    {
        if ($user->business_unit_id === null) {
            throw new ModelNotFoundException();
        }

        $businessUnit = BusinessUnit::findOrFail($user->business_unit_id);

        return $this->toRow($this->findOrCreateRow($businessUnit));
    }

    /**
     * listCurrentStations() — GET /api/mill-settings/current/stations.
     * Self-scoped counterpart to listStations(), same reasoning as
     * getCurrent() above.
     *
     * @throws ModelNotFoundException if the user has no business_unit_id
     */
    public function listCurrentStations(User $user): array
    {
        if ($user->business_unit_id === null) {
            throw new ModelNotFoundException();
        }

        return $this->mapStations($user->business_unit_id);
    }

    /**
     * Shared by listStations()/listCurrentStations() — factored out so
     * the self-scoped read path doesn't duplicate the mapping logic.
     */
    protected function mapStations(string $businessUnitId): array
    {
        return Station::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon'])
            ->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'type' => $station->type instanceof \App\Enums\StationType ? $station->type->value : $station->type,
                'icon' => $station->icon,
            ])
            ->all();
    }

    /**
     * setStationIcon() — business_logic step 6: validate business_unit_id
     * exists → validate station_id exists AND belongs to business_unit_id
     * → 404 if either fails → validate icon (if not null) is in
     * SUPPORTED_ICONS → 422 if not → UPDATE station.icon (null resets to
     * the type-default icon, no file involved).
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function setStationIcon(User $user, string $businessUnitId, string $stationId, ?string $icon): array
    {
        BusinessUnit::findOrFail($businessUnitId);

        $this->checkAccess($user, $businessUnitId);

        $station = Station::where('id', $stationId)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if ($station === null) {
            throw new ModelNotFoundException();
        }

        if ($icon !== null) {
            Validator::make(
                ['icon' => $icon],
                ['icon' => [Rule::in(self::SUPPORTED_ICONS)]],
                ['icon.in' => 'Icon yang dipilih tidak tersedia.']
            )->validate();
        }

        $station->update(['icon' => $icon]);

        return [
            'id' => $station->id,
            'icon' => $station->icon,
        ];
    }

    /**
     * Get-or-create-default (business_logic step 3), factored out so
     * update() can reuse it before applying the requested changes on
     * top, without duplicating the "does a row already exist" check.
     */
    protected function findOrCreateRow(BusinessUnit $businessUnit): MillSetting
    {
        $millSetting = MillSetting::where('business_unit_id', $businessUnit->id)->first();

        if ($millSetting !== null) {
            return $millSetting;
        }

        return MillSetting::create([
            'business_unit_id' => $businessUnit->id,
            'app_name' => $businessUnit->name,
        ]);
    }

    /**
     * Stores an uploaded file on LOGO_DISK under the given directory with
     * a randomly generated filename — same behaviour as
     * BusinessUnitService::storeLogo(). Returns the stored relative path.
     */
    protected function storeFile(UploadedFile $file, string $directory): ?string
    {
        $path = Storage::disk(self::LOGO_DISK)->putFile($directory, $file);

        return $path ?: null;
    }

    /**
     * Maps a MillSetting to the endpoints' shared row shape. `logo`/
     * `home_page_image` are exposed as their fully-resolved
     * Storage::url() (bare field name, no `_url` suffix — matches
     * screen-005--home's already-implemented `GET /api/mill-settings/
     * current` contract, whose response the mobile Home screen consumes
     * directly as an `<img>` src) — a deliberate divergence from
     * BusinessUnitService::toRow()'s `logo` (raw path) + `logo_url`
     * (resolved) pair, made to stay consistent with the sibling screen
     * that shares this same entity rather than with the unrelated
     * master-data convention.
     */
    protected function toRow(MillSetting $millSetting): array
    {
        return [
            'id' => $millSetting->id,
            'business_unit_id' => $millSetting->business_unit_id,
            'app_name' => $millSetting->app_name,
            'logo' => $millSetting->logo
                ? Storage::disk(self::LOGO_DISK)->url($millSetting->logo)
                : null,
            'home_page_image' => $millSetting->home_page_image
                ? Storage::disk(self::LOGO_DISK)->url($millSetting->home_page_image)
                : null,
            'created_at' => optional($millSetting->created_at)->toIso8601String(),
        ];
    }
}
