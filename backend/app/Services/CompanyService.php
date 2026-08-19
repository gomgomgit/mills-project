<?php

namespace App\Services;

use App\Exceptions\CompanyHasBusinessUnitsException;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CompanyService — screen-028--kelola-company / usecase-028--kelola-company
 * (Kelola Company — admin-only master-data CRUD, one level below
 * Corporate).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * CompanyController) and the Livewire component (App\Livewire\MasterData\
 * KelolaCompany) — same code path, no internal HTTP round-trip, so
 * validation/business rules stay identical between the two entry points.
 * Mirrors App\Services\CorporateService's structure exactly (entity-
 * catalog v4 rework, screen-028--kelola-company 3-tech-spec ver 2,
 * docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid), with one
 * deliberate, load-bearing divergence: TWO independent uniqueness rules
 * coexist on `company` simultaneously —
 *
 *  - `company_code` is unique GLOBALLY across the whole table (mirrors
 *    Corporate's `corporate_code`).
 *  - `name` is unique WITHIN `corporate_id` only (the same Company name
 *    may exist under two different Corporates) — UNCHANGED from before
 *    this rework, kept exactly as-is.
 *
 * See validate() below for how both are validated together in a single
 * pass without either rule leaking into the other.
 */
class CompanyService
{
    /**
     * Every company field this screen's create/update forms accept
     * directly (i.e. everything except `logo`, which is handled
     * separately as a file upload, and `created_by`/`updated_by`, which
     * are never accepted from user input — see create()/update()).
     * `corporate_id` is intentionally excluded from this list — it is
     * validated/handled separately (Rule::exists against `corporates`,
     * not a TEXT_FIELDS-style plain string) exactly as it was before this
     * rework.
     */
    protected const TEXT_FIELDS = [
        'company_code',
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
     * Date-cast fields (see Company::$casts) — kept separate from
     * TEXT_FIELDS because they are not plain strings, but still need to
     * flow from the raw $data payload through normalizeTextFields() into
     * $attributes so validate()'s `array_key_exists('last_update', ...)`
     * check (and ultimately Company::create()/update()) actually sees the
     * value the caller sent. `last_update` is always optional/nullable —
     * see validate()'s `last_update` rule.
     */
    protected const DATE_FIELDS = [
        'last_update',
    ];

    /**
     * Optional fields (everything in TEXT_FIELDS except company_code and
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
     * Disk used for `logo` uploads — same convention as
     * CorporateService::LOGO_DISK (see that class's docblock for the full
     * rationale: `local` disk has `'serve' => true`, auto-registers
     * `/storage/{path}`, no `public` disk / `storage:link` needed).
     *
     * Public (not protected) so App\Livewire\MasterData\KelolaCompany can
     * resolve the same existing-logo preview URL without duplicating the
     * disk name as a second literal.
     */
    public const LOGO_DISK = 'local';

    protected const LOGO_DIRECTORY = 'company-logos';

    /**
     * listCompanies() — business_logic step 1: paginate, optional
     * corporate_id filter, eager-load corporate (for corporate_name) +
     * withCount('businessUnits') (for business_unit_count) — a single
     * query regardless of page size, same approach as
     * CorporateService::listCorporates()'s withCount('companies').
     */
    public function listCompanies(int $page, int $perPage, ?string $corporateId = null): array
    {
        $query = Company::query()
            ->with('corporate')
            ->withCount('businessUnits')
            ->orderBy('name');

        if ($corporateId !== null && $corporateId !== '') {
            $query->where('corporate_id', $corporateId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Company $company) => $this->toRow($company))
            ->all();

        return $formatted;
    }

    /**
     * corporateOptions() — business_logic step 2: SELECT id,name from all
     * Corporate, ordered by name, unpaginated — feeds the Corporate-select
     * dropdown on the Company create/edit form.
     */
    public function corporateOptions(): array
    {
        return Corporate::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Corporate $corporate) => [
                'id' => $corporate->id,
                'name' => $corporate->name,
            ])
            ->all();
    }

    /**
     * create() — business_logic step 3: validate corporate_id exists →
     * validate company_code required+unique GLOBALLY → validate name
     * required+unique WITHIN that corporate_id (+ logo file type/size if
     * present) → 422 if any invalid → insert. `created_by` is always set
     * from the authenticated admin — never accepted from $data even if
     * present.
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
        $corporateId = trim((string) ($data['corporate_id'] ?? ''));
        $attributes = $this->normalizeTextFields($data);

        $this->validate($corporateId, $attributes, $logo, null);

        $attributes['corporate_id'] = $corporateId;
        $attributes['logo'] = $this->storeLogo($logo);
        $attributes['created_by'] = auth()->id();

        $company = Company::create($attributes);
        $company->load('corporate');
        $company->loadCount('businessUnits');

        return $this->toRow($company);
    }

    /**
     * update() — business_logic step 4: validate id exists → 404 if not →
     * validate corporate_id exists → validate company_code required+
     * unique GLOBALLY excluding self → validate name required+unique
     * within corporate_id excluding self (+ logo file type/size if
     * present) → 422 if any invalid → update. `updated_by` is always set
     * from the authenticated admin on every update; a new `logo` upload
     * replaces the stored file (old file left in place, same as
     * CorporateService), a null/absent `logo` argument leaves the
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
        $company = Company::findOrFail($id);

        $corporateId = trim((string) ($data['corporate_id'] ?? ''));
        $attributes = $this->normalizeTextFields($data);

        $this->validate($corporateId, $attributes, $logo, $company->id);

        $attributes['corporate_id'] = $corporateId;

        if ($logo !== null) {
            $attributes['logo'] = $this->storeLogo($logo);
        }

        $attributes['updated_by'] = auth()->id();

        $company->update($attributes);
        $company->load('corporate');
        $company->loadCount('businessUnits');

        return $this->toRow($company);
    }

    /**
     * delete() — business_logic step 5: validate id exists → 404 if not →
     * count BusinessUnit WHERE company_id=id → 409 if >0 → else delete.
     *
     * UNCHANGED by the entity-catalog v4 rework — do not modify.
     *
     * @throws ModelNotFoundException
     * @throws CompanyHasBusinessUnitsException
     */
    public function delete(string $id): void
    {
        $company = Company::findOrFail($id);

        if (BusinessUnit::where('company_id', $company->id)->count() > 0) {
            throw new CompanyHasBusinessUnitsException();
        }

        $company->delete();
    }

    /**
     * Trims every TEXT_FIELDS key present in $data, leaves absent keys
     * out entirely (so update() only ever validates/writes fields the
     * caller actually sent), empty-string normalises to null for the
     * optional fields so "cleared" fields save as NULL rather than "" —
     * mirrors CorporateService::normalizeTextFields() exactly.
     *
     * Also copies DATE_FIELDS (currently just `last_update`) through with
     * the same present-key-only + empty-string-to-null treatment — the
     * raw string value (e.g. "2026-01-01") is left untouched (no further
     * date parsing here; that's what validate()'s `date` rule and the
     * model's `date` cast are for) so an absent/empty value still saves
     * as NULL and a well-formed date string still reaches validate() and
     * Company::create()/update() unchanged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTextFields(array $data): array
    {
        $attributes = [];

        foreach ([...self::TEXT_FIELDS, ...self::DATE_FIELDS] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            $value = $value === null ? null : trim((string) $value);

            if ($value === '' && (in_array($field, self::OPTIONAL_TEXT_FIELDS, true) || in_array($field, self::DATE_FIELDS, true))) {
                $value = null;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * Validates corporate_id, company_code, name, last_update, and logo
     * in a single Validator pass (rather than several sequential
     * validate() calls) so a 422 response can carry every invalid field's
     * errors at once — matches shared_decisions.error_format's
     * `{ message, errors: { field: [...] } }` shape.
     *
     * CRITICAL — the two independent uniqueness rules on this entity are
     * declared as two entirely separate Rule::unique instances, each
     * scoped to its own column and its own semantics:
     *
     *  - `company_code` uses a bare Rule::unique('companies',
     *    'company_code') — global, exactly like
     *    CorporateService::validate()'s `corporate_code` rule.
     *  - `name` keeps its pre-existing `->where('corporate_id', ...)`
     *    closure (mirrors the DB's own composite unique index on
     *    `companies.(corporate_id, name)`) — UNCHANGED from before this
     *    rework. The same Company name is still allowed to exist under
     *    two different Corporates; when corporate_id itself is invalid,
     *    the closure scopes the uniqueness check to that (non-existent)
     *    corporate_id, which naturally matches no existing rows, so an
     *    invalid corporate_id alone never spuriously also raises a `name`
     *    uniqueness error.
     *
     * Neither rule's `->ignore($excludeId)` call affects the other — both
     * are applied independently against the *same* $excludeId (the
     * company being updated), which is correct: on update, the row being
     * edited is exempted from both its own company_code check and its own
     * name check, but the two checks still search independently (global
     * vs per-corporate).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validate(string $corporateId, array $attributes, ?UploadedFile $logo, ?string $excludeId): void
    {
        $codeUniqueRule = Rule::unique('companies', 'company_code');
        $nameUniqueRule = Rule::unique('companies', 'name')
            ->where(fn ($query) => $query->where('corporate_id', $corporateId));

        if ($excludeId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($excludeId);
            $nameUniqueRule = $nameUniqueRule->ignore($excludeId);
        }

        $payload = array_merge($attributes, [
            'corporate_id' => $corporateId,
            'logo' => $logo,
        ]);

        $rules = [
            'corporate_id' => ['required', 'string', Rule::exists('corporates', 'id')],
            'company_code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        if (array_key_exists('last_update', $attributes)) {
            $rules['last_update'] = ['nullable', 'date'];
        }

        foreach (self::OPTIONAL_TEXT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        Validator::make(
            $payload,
            $rules,
            [
                'corporate_id.required' => 'Corporate wajib dipilih.',
                'corporate_id.exists' => 'Corporate yang dipilih tidak ditemukan.',
                'company_code.required' => 'Kode company wajib diisi.',
                'company_code.max' => 'Kode company maksimal 255 karakter.',
                'company_code.unique' => 'Kode company sudah digunakan.',
                'name.required' => 'Nama company wajib diisi.',
                'name.max' => 'Nama company maksimal 255 karakter.',
                'name.unique' => 'Nama company sudah digunakan pada Corporate ini.',
                'last_update.date' => 'Tanggal pembaruan terakhir tidak valid.',
                'logo.file' => 'Logo harus berupa file.',
                'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
                'logo.max' => 'Ukuran logo maksimal 2MB.',
            ]
        )->validate();
    }

    /**
     * Stores an uploaded logo file on LOGO_DISK under LOGO_DIRECTORY with
     * a randomly generated filename — same behaviour as
     * CorporateService::storeLogo(). Returns the stored relative path
     * (what's persisted into `companies.logo`), or null if no file was
     * given.
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
     * Maps a Company (with corporate eager-loaded + businessUnits_count
     * already loaded via withCount()/loadCount()) to the endpoints'
     * shared row shape: every new field plus a computed `logo_url`
     * (Storage::url() against LOGO_DISK, null when no logo is stored)
     * alongside the raw `logo` path.
     */
    protected function toRow(Company $company): array
    {
        return [
            'id' => $company->id,
            'company_code' => $company->company_code,
            'name' => $company->name,
            'short_name' => $company->short_name,
            'leader_name' => $company->leader_name,
            'lawyer_name' => $company->lawyer_name,
            'address' => $company->address,
            'telephone_no' => $company->telephone_no,
            'fax_no' => $company->fax_no,
            'contact_no' => $company->contact_no,
            'extension_no' => $company->extension_no,
            'email' => $company->email,
            'website' => $company->website,
            'map' => $company->map,
            'tax_register_no' => $company->tax_register_no,
            'insurance_no' => $company->insurance_no,
            'epf_employer' => $company->epf_employer,
            'socso_employer' => $company->socso_employer,
            'labor_union' => $company->labor_union,
            'logo' => $company->logo,
            'logo_url' => $company->logo
                ? Storage::disk(self::LOGO_DISK)->url($company->logo)
                : null,
            'last_update' => optional($company->last_update)->toDateString(),
            'corporate_id' => $company->corporate_id,
            'corporate_name' => optional($company->corporate)->name,
            'business_unit_count' => (int) ($company->business_units_count ?? 0),
            'created_by' => $company->created_by,
            'updated_by' => $company->updated_by,
            'created_at' => optional($company->created_at)->toIso8601String(),
        ];
    }
}
