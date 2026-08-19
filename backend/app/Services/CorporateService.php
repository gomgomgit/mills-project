<?php

namespace App\Services;

use App\Exceptions\CorporateHasCompaniesException;
use App\Models\Corporate;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CorporateService — screen-027--kelola-corporate / usecase-027--kelola-corporate
 * (Kelola Corporate — admin-only master-data CRUD).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * CorporateController) and the Livewire component (App\Livewire\
 * MasterData\KelolaCorporate) — same code path, no internal HTTP
 * round-trip, so validation/business rules (name uniqueness, the
 * "has related Company rows" delete-guard) stay identical between the two
 * entry points (mirrors the AuthService / WeighbridgeRecordService
 * pattern).
 *
 * Entity-catalog v4 rework (screen-027--kelola-corporate 3-tech-spec ver 2,
 * docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid): `corporate` gained
 * a much larger field set (identity/contact/legal-employment fields, a
 * `logo` upload, and `created_by`/`updated_by` audit columns). See
 * implementation_notes on validate()/storeLogo()/toRow() below for how
 * each of those is handled.
 */
class CorporateService
{
    /**
     * Every corporate field this screen's create/update forms accept
     * directly (i.e. everything except `logo`, which is handled
     * separately as a file upload, and `created_by`/`updated_by`, which
     * are never accepted from user input — see create()/update()).
     */
    protected const TEXT_FIELDS = [
        'corporate_code',
        'name',
        'short_name',
        'leader_name',
        'lawyer_name',
        'address',
        'telephone_no',
        'fax_no',
        'contact_no',
        'extension_no',
        'email',
        'website',
        'map',
        'tax_register_no',
        'insurance_no',
        'epf_employer',
        'socso_employer',
        'labor_union',
    ];

    /**
     * Optional fields (everything in TEXT_FIELDS except corporate_code and
     * name, which are the only two required-by-app-validation fields).
     */
    protected const OPTIONAL_TEXT_FIELDS = [
        'short_name',
        'leader_name',
        'lawyer_name',
        'address',
        'telephone_no',
        'fax_no',
        'contact_no',
        'extension_no',
        'email',
        'website',
        'map',
        'tax_register_no',
        'insurance_no',
        'epf_employer',
        'socso_employer',
        'labor_union',
    ];

    /**
     * Disk used for `logo` uploads. Reuses this project's already-
     * established file-upload convention: FILESYSTEM_DISK=local (see
     * .env.example's "File upload disimpan di Laravel Filesystem local
     * disk (on-premise)." comment and config/filesystems.php) — the
     * 'local' disk has `'serve' => true`, which auto-registers a
     * `/storage/{path}` route (named `storage.local`) via Laravel 11's
     * FilesystemServiceProvider::serveFiles(), so `Storage::url()`
     * against this disk resolves to a working `/storage/...` URL out of
     * the box with zero extra wiring — no need to introduce the 'public'
     * disk / `storage:link` symlink convention for this. See
     * implementation_notes.
     *
     * Public (not protected) so App\Livewire\MasterData\KelolaCorporate
     * can resolve the same existing-logo preview URL (Storage::disk(
     * CorporateService::LOGO_DISK)->url(...)) without duplicating the
     * disk name as a second literal.
     */
    public const LOGO_DISK = 'local';

    protected const LOGO_DIRECTORY = 'corporate-logos';

    /**
     * listCorporates() — business_logic step 1: paginate, company_count
     * computed via COUNT(company WHERE corporate_id) — done here with
     * withCount('companies') rather than a raw COUNT subquery/N+1 loop, so
     * it stays a single query regardless of page size.
     */
    public function listCorporates(int $page, int $perPage): array
    {
        $query = Corporate::query()->withCount('companies')->orderBy('name');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Corporate $corporate) => $this->toRow($corporate))
            ->all();

        return $formatted;
    }

    /**
     * create() — business_logic step 2: validate corporate_code/name
     * required+unique (+ logo file type/size if present) → 422 if
     * invalid → insert. `created_by` is always set from the authenticated
     * admin — never accepted from $data even if present (per
     * screen_tech_spec: "never accepted as user input").
     *
     * @param  array<string, mixed>  $data  raw create() payload — any
     *                                       'created_by'/'updated_by' keys
     *                                       are ignored (see above)
     * @param  \Illuminate\Http\UploadedFile|null  $logo
     *
     * @throws ValidationException
     */
    public function create(array $data, ?UploadedFile $logo = null): array
    {
        $attributes = $this->normalizeTextFields($data);

        $this->validate($attributes, $logo, null);

        $attributes['logo'] = $this->storeLogo($logo);
        $attributes['created_by'] = auth()->id();

        $corporate = Corporate::create($attributes);
        $corporate->loadCount('companies');

        return $this->toRow($corporate);
    }

    /**
     * update() — business_logic step 3: validate id exists → 404 if not →
     * validate corporate_code/name required+unique excluding self (+ logo
     * file type/size if present) → 422 if invalid → update. `updated_by`
     * is always set from the authenticated admin on every update; a new
     * `logo` upload replaces the stored file (old file left in place —
     * see implementation_notes), a null/absent `logo` argument leaves the
     * existing `logo` column untouched.
     *
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Http\UploadedFile|null  $logo
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, ?UploadedFile $logo = null): array
    {
        $corporate = Corporate::findOrFail($id);

        $attributes = $this->normalizeTextFields($data);

        $this->validate($attributes, $logo, $corporate->id);

        if ($logo !== null) {
            $attributes['logo'] = $this->storeLogo($logo);
        }

        $attributes['updated_by'] = auth()->id();

        $corporate->update($attributes);
        $corporate->loadCount('companies');

        return $this->toRow($corporate);
    }

    /**
     * delete() — business_logic step 4: validate id exists → 404 if not →
     * count Company WHERE corporate_id=id → 409 if >0 → else delete.
     *
     * UNCHANGED by the entity-catalog v4 rework — do not modify.
     *
     * @throws ModelNotFoundException
     * @throws CorporateHasCompaniesException
     */
    public function delete(string $id): void
    {
        $corporate = Corporate::findOrFail($id);

        if ($corporate->companies()->count() > 0) {
            throw new CorporateHasCompaniesException();
        }

        $corporate->delete();
    }

    /**
     * Trims every TEXT_FIELDS key present in $data, leaves absent keys
     * out entirely (so update() only ever validates/writes fields the
     * caller actually sent — matches the pre-existing create()/update()
     * behaviour of trim((string) ($data['name'] ?? '')) generalised across
     * the full field set, except empty-string normalises to null for the
     * optional fields so "cleared" fields save as NULL rather than "").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTextFields(array $data): array
    {
        $attributes = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            $value = $value === null ? null : trim((string) $value);

            if ($value === '' && in_array($field, self::OPTIONAL_TEXT_FIELDS, true)) {
                $value = null;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validate(array $attributes, ?UploadedFile $logo, ?string $excludeId): void
    {
        $codeUniqueRule = Rule::unique('corporates', 'corporate_code');
        $nameUniqueRule = Rule::unique('corporates', 'name');

        if ($excludeId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($excludeId);
            $nameUniqueRule = $nameUniqueRule->ignore($excludeId);
        }

        $payload = array_merge($attributes, ['logo' => $logo]);

        $rules = [
            'corporate_code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        foreach (self::OPTIONAL_TEXT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        Validator::make(
            $payload,
            $rules,
            [
                'corporate_code.required' => 'Kode corporate wajib diisi.',
                'corporate_code.max' => 'Kode corporate maksimal 255 karakter.',
                'corporate_code.unique' => 'Kode corporate sudah digunakan.',
                'name.required' => 'Nama corporate wajib diisi.',
                'name.max' => 'Nama corporate maksimal 255 karakter.',
                'name.unique' => 'Nama corporate sudah digunakan.',
                'logo.file' => 'Logo harus berupa file.',
                'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
                'logo.max' => 'Ukuran logo maksimal 2MB.',
            ]
        )->validate();
    }

    /**
     * Stores an uploaded logo file on LOGO_DISK under LOGO_DIRECTORY with
     * a randomly generated filename (Storage::putFile()'s default
     * behaviour — avoids collisions/overwrites between corporates without
     * needing to know the corporate's id up front, since this runs before
     * Corporate::create() on the create() path). Returns the stored
     * relative path (what's persisted into `corporates.logo`), or null if
     * no file was given.
     */
    protected function storeLogo(?UploadedFile $logo): ?string
    {
        if ($logo === null) {
            return null;
        }

        $path = Storage::disk(self::LOGO_DISK)->putFile(self::LOGO_DIRECTORY, $logo);

        return $path ?: null;
    }

    /**
     * Maps a Corporate (with companies_count already loaded via
     * withCount()/loadCount()) to the endpoints' shared row shape — every
     * new field plus a computed `logo_url` (Storage::url() against
     * LOGO_DISK, null when no logo is stored) alongside the raw `logo`
     * path.
     */
    protected function toRow(Corporate $corporate): array
    {
        return [
            'id' => $corporate->id,
            'corporate_code' => $corporate->corporate_code,
            'name' => $corporate->name,
            'short_name' => $corporate->short_name,
            'leader_name' => $corporate->leader_name,
            'lawyer_name' => $corporate->lawyer_name,
            'address' => $corporate->address,
            'telephone_no' => $corporate->telephone_no,
            'fax_no' => $corporate->fax_no,
            'contact_no' => $corporate->contact_no,
            'extension_no' => $corporate->extension_no,
            'email' => $corporate->email,
            'website' => $corporate->website,
            'map' => $corporate->map,
            'tax_register_no' => $corporate->tax_register_no,
            'insurance_no' => $corporate->insurance_no,
            'epf_employer' => $corporate->epf_employer,
            'socso_employer' => $corporate->socso_employer,
            'labor_union' => $corporate->labor_union,
            'logo' => $corporate->logo,
            'logo_url' => $corporate->logo
                ? Storage::disk(self::LOGO_DISK)->url($corporate->logo)
                : null,
            'company_count' => (int) ($corporate->companies_count ?? 0),
            'created_by' => $corporate->created_by,
            'updated_by' => $corporate->updated_by,
            'created_at' => optional($corporate->created_at)->toIso8601String(),
        ];
    }
}
