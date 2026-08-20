<?php

namespace App\Services;

use App\Exceptions\BusinessUnitHasStationsException;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * BusinessUnitService — screen-029--kelola-business-unit /
 * usecase-029--kelola-business-unit (Kelola Business Unit — admin-only
 * master-data CRUD, one level below Company).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * BusinessUnitController) and the Livewire component (App\Livewire\
 * MasterData\KelolaBusinessUnit) — same code path, no internal HTTP
 * round-trip, so validation/business rules stay identical between the two
 * entry points. Mirrors App\Services\CorporateService / App\Services\
 * CompanyService's structure exactly (entity-catalog v4 rework,
 * screen-029--kelola-business-unit 3-tech-spec ver 2,
 * docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid), with one
 * deliberate, load-bearing divergence from BOTH precedents:
 *
 *  - `code` is unique GLOBALLY across the whole table (mirrors
 *    Corporate's `corporate_code` / Company's `company_code` — and was
 *    already globally unique on the ORIGINAL business_units table, see
 *    2025_01_15_000003_create_business_units_table.php).
 *  - `name` has NO uniqueness rule at all — neither global (like
 *    Corporate) nor scoped to the parent (like Company's per-corporate_id
 *    rule). Just `['required', 'string', 'max:255']`. This is the
 *    critical divergence from both Corporate and Company — see validate()
 *    below.
 */
class BusinessUnitService
{
    /**
     * Every business unit field this screen's create/update forms accept
     * directly (i.e. everything except `logo`, which is handled
     * separately as a file upload, `company_id`, which is validated/
     * handled separately (Rule::exists against `companies`, not a
     * TEXT_FIELDS-style plain string), and `created_by`/`updated_by`,
     * which are never accepted from user input — see create()/update()).
     */
    protected const TEXT_FIELDS = [
        'code',
        'name',
        'business_unit_type_code',
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
     * Optional fields (everything in TEXT_FIELDS except code and name,
     * which are the only two required-by-app-validation fields).
     */
    protected const OPTIONAL_TEXT_FIELDS = [
        'business_unit_type_code',
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
     * Disk used for `logo` uploads — same convention as
     * CorporateService::LOGO_DISK / CompanyService::LOGO_DISK (see
     * CorporateService's docblock for the full rationale: `local` disk
     * has `'serve' => true`, auto-registers `/storage/{path}`, no
     * `public` disk / `storage:link` needed).
     *
     * Public (not protected) so App\Livewire\MasterData\KelolaBusinessUnit
     * can resolve the same existing-logo preview URL without duplicating
     * the disk name as a second literal.
     */
    public const LOGO_DISK = 'local';

    protected const LOGO_DIRECTORY = 'business-unit-logos';

    /**
     * listBusinessUnits() — business_logic step "list": paginate,
     * optional company_id filter, eager-load company (for company_name) +
     * withCount('stations') (for station_count) — a single query
     * regardless of page size, same approach as
     * CompanyService::listCompanies()'s with('corporate')/
     * withCount('businessUnits').
     */
    public function listBusinessUnits(int $page, int $perPage, ?string $companyId = null): array
    {
        $query = BusinessUnit::query()
            ->with('company')
            ->withCount('stations')
            ->orderBy('name');

        if ($companyId !== null && $companyId !== '') {
            $query->where('company_id', $companyId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (BusinessUnit $businessUnit) => $this->toRow($businessUnit))
            ->all();

        return $formatted;
    }

    /**
     * companyOptions() — business_logic step "companyOptions": SELECT
     * id,name from all Company, ordered by name, unpaginated — feeds the
     * Company-select dropdown on the Business Unit create/edit form.
     * Mirrors CompanyService::corporateOptions() exactly — queries the
     * Company model directly (only `id`/`name` columns are used, which
     * are stable regardless of the concurrent Company v4 rework).
     */
    public function companyOptions(): array
    {
        return Company::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
            ])
            ->all();
    }

    /**
     * create() — business_logic step "create": validate company_id
     * exists → validate code required+unique GLOBALLY → validate name
     * required (NO uniqueness rule) (+ logo file type/size if present) →
     * 422 if any invalid → insert. `created_by` is always set from the
     * authenticated admin — never accepted from $data even if present.
     *
     * 2026-08-20 (entity-catalog v9): NO LONGER auto-provisions any
     * stations — that behavior moved to ProductionLineService::create(),
     * since each Production Line (the new hierarchy level inserted between
     * Business Unit and Station) now gets its own full set of stations,
     * not one shared set per Business Unit. A newly created Business Unit
     * has zero Production Lines and zero Stations until an Admin
     * explicitly creates a Production Line for it via screen-036.
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
        $companyId = trim((string) ($data['company_id'] ?? ''));
        $attributes = $this->normalizeTextFields($data);

        $this->validate($companyId, $attributes, $logo, null);

        $attributes['company_id'] = $companyId;
        $attributes['logo'] = $this->storeLogo($logo);
        $attributes['created_by'] = auth()->id();

        $businessUnit = BusinessUnit::create($attributes);

        $businessUnit->load('company');
        $businessUnit->loadCount('stations');

        return $this->toRow($businessUnit);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → validate company_id exists → validate code required+
     * unique GLOBALLY excluding self → validate name required (NO
     * uniqueness rule) (+ logo file type/size if present) → 422 if any
     * invalid → update. `updated_by` is always set from the authenticated
     * admin on every update; a new `logo` upload replaces the stored file
     * (old file left in place, same as CorporateService/CompanyService),
     * a null/absent `logo` argument leaves the existing `logo` column
     * untouched.
     *
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Http\UploadedFile|null  $logo
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, ?UploadedFile $logo = null): array
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        $companyId = trim((string) ($data['company_id'] ?? ''));
        $attributes = $this->normalizeTextFields($data);

        $this->validate($companyId, $attributes, $logo, $businessUnit->id);

        $attributes['company_id'] = $companyId;

        if ($logo !== null) {
            $attributes['logo'] = $this->storeLogo($logo);
        }

        $attributes['updated_by'] = auth()->id();

        $businessUnit->update($attributes);
        $businessUnit->load('company');
        $businessUnit->loadCount('stations');

        return $this->toRow($businessUnit);
    }

    /**
     * delete() — business_logic step "delete": validate id exists → 404
     * if not → count Station WHERE business_unit_id=id → 409
     * BUSINESS_UNIT_HAS_STATIONS if any exist → else delete.
     *
     * @throws ModelNotFoundException
     * @throws BusinessUnitHasStationsException
     */
    public function delete(string $id): void
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        if ($businessUnit->stations()->count() > 0) {
            throw new BusinessUnitHasStationsException();
        }

        $businessUnit->delete();
    }

    /**
     * Trims every TEXT_FIELDS key present in $data, leaves absent keys
     * out entirely (so update() only ever validates/writes fields the
     * caller actually sent), empty-string normalises to null for the
     * optional fields so "cleared" fields save as NULL rather than "" —
     * mirrors CorporateService::normalizeTextFields() /
     * CompanyService::normalizeTextFields() exactly.
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
     * Validates company_id, code, name, and logo in a single Validator
     * pass (rather than several sequential validate() calls) so a 422
     * response can carry every invalid field's errors at once — matches
     * shared_decisions.error_format's `{ message, errors: { field: [...] } }`
     * shape.
     *
     * CRITICAL — divergence from CorporateService/CompanyService: `code`
     * uses a bare Rule::unique('business_units', 'code') — global, same
     * shape as Corporate's `corporate_code` / Company's `company_code`.
     * `name` carries NO Rule::unique at all — just required|string|max —
     * this entity's `name` field is not unique in any scope whatsoever
     * (neither globally like Corporate's `name`, nor scoped to the parent
     * like Company's per-corporate_id `name`).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validate(string $companyId, array $attributes, ?UploadedFile $logo, ?string $excludeId): void
    {
        $codeUniqueRule = Rule::unique('business_units', 'code');

        if ($excludeId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($excludeId);
        }

        $payload = array_merge($attributes, [
            'company_id' => $companyId,
            'logo' => $logo,
        ]);

        $rules = [
            'company_id' => ['required', 'string', Rule::exists('companies', 'id')],
            'code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        foreach (self::OPTIONAL_TEXT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        Validator::make(
            $payload,
            $rules,
            [
                'company_id.required' => 'Company wajib dipilih.',
                'company_id.exists' => 'Company yang dipilih tidak ditemukan.',
                'code.required' => 'Kode business unit wajib diisi.',
                'code.max' => 'Kode business unit maksimal 255 karakter.',
                'code.unique' => 'Kode business unit sudah digunakan.',
                'name.required' => 'Nama business unit wajib diisi.',
                'name.max' => 'Nama business unit maksimal 255 karakter.',
                'logo.file' => 'Logo harus berupa file.',
                'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
                'logo.max' => 'Ukuran logo maksimal 2MB.',
            ]
        )->validate();
    }

    /**
     * Stores an uploaded logo file on LOGO_DISK under LOGO_DIRECTORY with
     * a randomly generated filename — same behaviour as
     * CorporateService::storeLogo() / CompanyService::storeLogo(). Returns
     * the stored relative path (what's persisted into
     * `business_units.logo`), or null if no file was given.
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
     * Maps a BusinessUnit (with company eager-loaded + stations_count
     * already loaded via withCount()/loadCount()) to the endpoints'
     * shared row shape: every field plus a computed `logo_url`
     * (Storage::url() against LOGO_DISK, null when no logo is stored)
     * alongside the raw `logo` path.
     */
    protected function toRow(BusinessUnit $businessUnit): array
    {
        return [
            'id' => $businessUnit->id,
            'code' => $businessUnit->code,
            'name' => $businessUnit->name,
            'business_unit_type_code' => $businessUnit->business_unit_type_code,
            'short_name' => $businessUnit->short_name,
            'leader_name' => $businessUnit->leader_name,
            'lawyer_name' => $businessUnit->lawyer_name,
            'address' => $businessUnit->address,
            'telephone_no' => $businessUnit->telephone_no,
            'fax_no' => $businessUnit->fax_no,
            'contact_no' => $businessUnit->contact_no,
            'extension_no' => $businessUnit->extension_no,
            'email' => $businessUnit->email,
            'website' => $businessUnit->website,
            'map' => $businessUnit->map,
            'tax_register_no' => $businessUnit->tax_register_no,
            'insurance_no' => $businessUnit->insurance_no,
            'epf_employer' => $businessUnit->epf_employer,
            'socso_employer' => $businessUnit->socso_employer,
            'labor_union' => $businessUnit->labor_union,
            'logo' => $businessUnit->logo,
            'logo_url' => $businessUnit->logo
                ? Storage::disk(self::LOGO_DISK)->url($businessUnit->logo)
                : null,
            'company_id' => $businessUnit->company_id,
            'company_name' => optional($businessUnit->company)->name,
            'station_count' => (int) ($businessUnit->stations_count ?? 0),
            'created_by' => $businessUnit->created_by,
            'updated_by' => $businessUnit->updated_by,
            'created_at' => optional($businessUnit->created_at)->toIso8601String(),
        ];
    }
}
