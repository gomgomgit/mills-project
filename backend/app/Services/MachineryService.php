<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MachineryService — screen-031--kelola-machinery / usecase-031--kelola-machinery
 * (Kelola Machinery — admin-only master-data CRUD, one level below
 * Machinery Group, with two child-grid entities managed directly inside
 * the Machinery form: MachineryInsurance and MachineryTaxPurchase).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * MachineryController) and the Livewire component (App\Livewire\
 * MasterData\KelolaMachinery), so validation/business rules stay
 * identical between the two entry points — mirrors
 * MachineryGroupService's/CorporateService's structure.
 *
 * STRUCTURAL RULES this screen exists to enforce:
 *  - `station_id` AND `business_unit_id` are NEVER read from the
 *    create()/update() $data payload, even if present — validate() below
 *    deliberately excludes both from the returned validated array.
 *    create()/update() instead look up the (already-validated-to-exist)
 *    MachineryGroup by its `machinery_group_id` and copy that group's own
 *    `station_id`/`business_unit_id` onto the Machinery being written.
 *    This mirrors MachineryGroupService's business_unit_id-from-Station
 *    pattern one level down: business_unit_id is derived from
 *    machinery_group_id, NOT from station_id directly, since MachineryGroup
 *    already carries a verified business_unit_id (copied from ITS own
 *    Station by MachineryGroupService).
 *  - `equipment_code` is REQUIRED and globally unique (mirrors
 *    group_code/corporate_code's required+unique convention).
 *  - Two child-row collections — `insurances` (MachineryInsurance) and
 *    `tax_purchases` (MachineryTaxPurchase) — are accepted as arrays of
 *    plain field maps inside the same create()/update() payload and
 *    written inside the same DB transaction as the Machinery row itself.
 *    REPLACE-ALL semantics on update(): if the `insurances` (resp.
 *    `tax_purchases`) key is PRESENT in $data (even as an empty array),
 *    every existing child row for this machinery_id is deleted and
 *    replaced with whatever was sent (empty array = zero rows, a valid
 *    end state). If the key is ABSENT from $data entirely, existing child
 *    rows are left untouched — this lets a caller update only the parent
 *    Machinery fields (e.g. via a partial PATCH) without accidentally
 *    wiping its child rows. create() always syncs both collections
 *    (defaulting to an empty array when absent), since there is nothing
 *    to "leave untouched" yet.
 *  - NO delete-guard of any kind (unlike every other master-data screen
 *    in this round) — delete() explicitly deletes both child collections
 *    first (for clarity/testability), then the Machinery row; the child
 *    tables' cascadeOnDelete() FK is a safety net for the same edge, not
 *    the primary mechanism. Deleting a Machinery row with child rows must
 *    never throw.
 */
class MachineryService
{
    /**
     * Disk/directory for `picture` uploads — mirrors CorporateService::
     * LOGO_DISK's reasoning exactly (FILESYSTEM_DISK=local, `local` disk
     * has `'serve' => true` so Storage::url() resolves to a working
     * `/storage/...` URL with zero extra wiring).
     */
    public const PICTURE_DISK = 'local';

    protected const PICTURE_DIRECTORY = 'machinery-pictures';

    /**
     * Every nullable technical-spec text field this screen's form
     * accepts, validated as nullable|string|max:255. `rpm` (float) and
     * `year_made` (integer) are validated separately below since they are
     * not plain strings.
     */
    protected const TECH_TEXT_FIELDS = [
        'registration_no',
        'make',
        'model',
        'equipment_type',
        'part_no',
        'serial_no',
        'gearbox',
        'motor',
        'mounting',
        'chain',
        'capacity',
        'brand',
        'fixed_asset',
        'control_activity',
        'owner_ite',
    ];

    /**
     * listMachinery() — business_logic step "list": paginate, optional
     * machinery_group_id filter, eager-load machineryGroup (for
     * machinery_group_code) — NO child arrays, keeps the list endpoint
     * lightweight per this screen's api_contracts.
     */
    public function listMachinery(int $page, int $perPage, ?string $machineryGroupId = null): array
    {
        $query = Machinery::query()
            ->with('machineryGroup')
            ->orderBy('equipment_code');

        if ($machineryGroupId !== null && $machineryGroupId !== '') {
            $query->where('machinery_group_id', $machineryGroupId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Machinery $machinery) => $this->toListRow($machinery))
            ->all();

        return $formatted;
    }

    /**
     * machineryGroupOptions() — business_logic step "machineryGroupOptions":
     * SELECT id,group_code,station_id,business_unit_id from all
     * MachineryGroup, ordered by group_code, unpaginated — feeds the
     * Machinery Group-select dropdown on this screen's create/edit form.
     * `station_id`/`business_unit_id` are included so the FE can
     * copy/display them client-side before submit — the server
     * independently re-derives both again on create()/update(), never
     * trusting client input for either (see this class's own docblock).
     */
    public function machineryGroupOptions(): array
    {
        return MachineryGroup::query()
            ->orderBy('group_code')
            ->get(['id', 'group_code', 'station_id', 'business_unit_id'])
            ->map(fn (MachineryGroup $group) => [
                'id' => $group->id,
                'group_code' => $group->group_code,
                'station_id' => $group->station_id,
                'business_unit_id' => $group->business_unit_id,
            ])
            ->all();
    }

    /**
     * detail() — business_logic step "detail": GET /api/machinery/:id,
     * the only sibling-screen-shaped endpoint in this round with a
     * dedicated detail fetch — includes `insurances`/`tax_purchases`
     * arrays, unlike listMachinery()'s lightweight rows. Populates the
     * Edit form.
     *
     * @throws ModelNotFoundException
     */
    public function detail(string $id): array
    {
        $machinery = Machinery::with(['machineryGroup', 'insurances', 'taxPurchases'])->findOrFail($id);

        return $this->toDetailRow($machinery);
    }

    /**
     * create() — business_logic step "create": validate machinery_group_id
     * exists → validate equipment_code required+unique globally → validate
     * name required, description/picture/technical fields nullable → 422
     * if any invalid → derive station_id/business_unit_id from the found
     * MachineryGroup → insert Machinery + insurances + tax_purchases
     * inside one DB transaction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function create(array $data, ?UploadedFile $picture = null): array
    {
        $attributes = $this->validate($data, $picture, null);

        $insurances = $this->validateInsurances($data['insurances'] ?? []);
        $taxPurchases = $this->validateTaxPurchases($data['tax_purchases'] ?? []);

        // machinery_group_id was already validated to exist via
        // Rule::exists above — findOrFail() here is a defensive re-fetch
        // (the actual MachineryGroup row is needed regardless, to copy
        // its station_id/business_unit_id) rather than a second
        // validation pass; a race where the MachineryGroup is deleted
        // between validate() and this line is an acceptable, extremely
        // narrow edge case shared by every sibling Service::create() in
        // this codebase (see MachineryGroupService::create()'s identical
        // precedent).
        $machineryGroup = MachineryGroup::findOrFail($attributes['machinery_group_id']);
        $attributes['station_id'] = $machineryGroup->station_id;
        $attributes['business_unit_id'] = $machineryGroup->business_unit_id;

        $attributes['picture'] = $this->storePicture($picture);

        $machinery = DB::transaction(function () use ($attributes, $insurances, $taxPurchases) {
            $machinery = Machinery::create($attributes);

            $this->syncInsurances($machinery, $insurances);
            $this->syncTaxPurchases($machinery, $taxPurchases);

            return $machinery;
        });

        $machinery->load(['machineryGroup', 'insurances', 'taxPurchases']);

        return $this->toDetailRow($machinery);
    }

    /**
     * update() — business_logic step "update": validate id exists → 404
     * if not → same field validation as create() (equipment_code unique
     * excluding self) → 422 if any invalid → re-derive
     * station_id/business_unit_id from the (possibly changed)
     * machinery_group_id → update Machinery, and — only when the
     * `insurances`/`tax_purchases` keys are PRESENT in $data — replace
     * all existing child rows for this machinery_id (see this class's own
     * docblock for the "present vs absent" distinction). All inside one
     * DB transaction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, ?UploadedFile $picture = null): array
    {
        $machinery = Machinery::findOrFail($id);

        $attributes = $this->validate($data, $picture, $machinery->id);

        $syncInsurances = array_key_exists('insurances', $data);
        $syncTaxPurchases = array_key_exists('tax_purchases', $data);

        $insurances = $syncInsurances ? $this->validateInsurances($data['insurances'] ?? []) : [];
        $taxPurchases = $syncTaxPurchases ? $this->validateTaxPurchases($data['tax_purchases'] ?? []) : [];

        $machineryGroup = MachineryGroup::findOrFail($attributes['machinery_group_id']);
        $attributes['station_id'] = $machineryGroup->station_id;
        $attributes['business_unit_id'] = $machineryGroup->business_unit_id;

        if ($picture !== null) {
            $attributes['picture'] = $this->storePicture($picture);
        }

        DB::transaction(function () use ($machinery, $attributes, $syncInsurances, $insurances, $syncTaxPurchases, $taxPurchases) {
            $machinery->update($attributes);

            if ($syncInsurances) {
                $this->syncInsurances($machinery, $insurances);
            }

            if ($syncTaxPurchases) {
                $this->syncTaxPurchases($machinery, $taxPurchases);
            }
        });

        $machinery->load(['machineryGroup', 'insurances', 'taxPurchases']);

        return $this->toDetailRow($machinery);
    }

    /**
     * delete() — business_logic step "delete": validate id exists → 404
     * if not → delete both child collections → delete Machinery. NO
     * guard/exception of any kind (see this class's own docblock) — the
     * child tables' cascadeOnDelete() FK is a safety net for the same
     * edge, this explicit delete is for clarity/testability.
     *
     * @throws ModelNotFoundException
     */
    public function delete(string $id): void
    {
        $machinery = Machinery::findOrFail($id);

        DB::transaction(function () use ($machinery) {
            $machinery->insurances()->delete();
            $machinery->taxPurchases()->delete();
            $machinery->delete();
        });
    }

    /**
     * Replaces every existing MachineryInsurance row for $machinery with
     * $rows (already-validated field maps) — delete-then-insert, an empty
     * $rows array is a valid "zero rows" end state.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncInsurances(Machinery $machinery, array $rows): void
    {
        $machinery->insurances()->delete();

        foreach ($rows as $row) {
            $machinery->insurances()->create($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncTaxPurchases(Machinery $machinery, array $rows): void
    {
        $machinery->taxPurchases()->delete();

        foreach ($rows as $row) {
            $machinery->taxPurchases()->create($row);
        }
    }

    /**
     * Validates the top-level Machinery fields (machinery_group_id,
     * equipment_code, name, description, picture, technical fields).
     * DELIBERATELY never validates or returns `station_id`/
     * `business_unit_id` — see this class's own docblock; create()/
     * update() derive both themselves from the MachineryGroup found via
     * `machinery_group_id`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?UploadedFile $picture, ?string $excludeId): array
    {
        $equipmentCodeUniqueRule = Rule::unique('machinery', 'equipment_code');

        if ($excludeId !== null) {
            $equipmentCodeUniqueRule = $equipmentCodeUniqueRule->ignore($excludeId);
        }

        $payload = [
            'machinery_group_id' => $data['machinery_group_id'] ?? null,
            'equipment_code' => $this->emptyToNull($data['equipment_code'] ?? null),
            'name' => $this->emptyToNull($data['name'] ?? null),
            'description' => $this->emptyToNull($data['description'] ?? null),
            'picture' => $picture,
            'rpm' => $this->emptyToNull($data['rpm'] ?? null),
            'year_made' => $this->emptyToNull($data['year_made'] ?? null),
        ];

        $rules = [
            'machinery_group_id' => ['required', 'string', Rule::exists('machinery_groups', 'id')],
            'equipment_code' => ['required', 'string', 'max:255', $equipmentCodeUniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'picture' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'rpm' => ['nullable', 'numeric'],
            'year_made' => ['nullable', 'integer'],
        ];

        $messages = [
            'machinery_group_id.required' => 'Machinery Group wajib dipilih.',
            'machinery_group_id.exists' => 'Machinery Group yang dipilih tidak ditemukan.',
            'equipment_code.required' => 'Kode Equipment wajib diisi.',
            'equipment_code.max' => 'Kode Equipment maksimal 255 karakter.',
            'equipment_code.unique' => 'Kode Equipment sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'picture.file' => 'Gambar harus berupa file.',
            'picture.mimes' => 'Gambar harus berformat JPG atau PNG.',
            'picture.max' => 'Ukuran gambar maksimal 2MB.',
            'rpm.numeric' => 'RPM harus berupa angka.',
            'year_made.integer' => 'Tahun Pembuatan harus berupa bilangan bulat.',
        ];

        foreach (self::TECH_TEXT_FIELDS as $field) {
            $payload[$field] = $this->emptyToNull($data[$field] ?? null);
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        $validated = Validator::make($payload, $rules, $messages)->validate();

        // 'picture' is validated above but written separately (see
        // create()/update()'s $attributes['picture'] = $this->storePicture(...))
        // — drop it from the returned attribute set so it never collides
        // with the string path assignment.
        unset($validated['picture']);

        if ($validated['rpm'] !== null) {
            $validated['rpm'] = (float) $validated['rpm'];
        }
        if ($validated['year_made'] !== null) {
            $validated['year_made'] = (int) $validated['year_made'];
        }

        return $validated;
    }

    /**
     * Validates each row of the `insurances` array payload.
     *
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    protected function validateInsurances(mixed $rows): array
    {
        if (! is_array($rows)) {
            $rows = [];
        }

        $payload = ['insurances' => array_values($rows)];

        $rules = [
            'insurances' => ['array'],
            'insurances.*.ownership' => ['nullable', 'string', 'max:255'],
            'insurances.*.insurance_policy_no' => ['nullable', 'string', 'max:255'],
            'insurances.*.insurance_company' => ['nullable', 'string', 'max:255'],
            'insurances.*.insurance_expiry_date' => ['nullable', 'date'],
            'insurances.*.premium' => ['nullable', 'numeric'],
            'insurances.*.amount_insured' => ['nullable', 'numeric'],
        ];

        $validated = Validator::make($payload, $rules, [], [
            'insurances.*.ownership' => 'Kepemilikan',
            'insurances.*.insurance_policy_no' => 'No. Polis Asuransi',
            'insurances.*.insurance_company' => 'Perusahaan Asuransi',
            'insurances.*.insurance_expiry_date' => 'Tanggal Kadaluarsa Asuransi',
            'insurances.*.premium' => 'Premi',
            'insurances.*.amount_insured' => 'Jumlah Diasuransikan',
        ])->validate();

        return array_map(function (array $row) {
            return [
                'ownership' => $this->emptyToNull($row['ownership'] ?? null),
                'insurance_policy_no' => $this->emptyToNull($row['insurance_policy_no'] ?? null),
                'insurance_company' => $this->emptyToNull($row['insurance_company'] ?? null),
                'insurance_expiry_date' => $this->emptyToNull($row['insurance_expiry_date'] ?? null),
                'premium' => $this->toFloatOrNull($row['premium'] ?? null),
                'amount_insured' => $this->toFloatOrNull($row['amount_insured'] ?? null),
            ];
        }, $validated['insurances']);
    }

    /**
     * Validates each row of the `tax_purchases` array payload.
     *
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    protected function validateTaxPurchases(mixed $rows): array
    {
        if (! is_array($rows)) {
            $rows = [];
        }

        $payload = ['tax_purchases' => array_values($rows)];

        $rules = [
            'tax_purchases' => ['array'],
            'tax_purchases.*.purchase_date' => ['nullable', 'date'],
            'tax_purchases.*.purchase_cost' => ['nullable', 'numeric'],
            'tax_purchases.*.policy_type' => ['nullable', 'string', 'max:255'],
            'tax_purchases.*.contact_name' => ['nullable', 'string', 'max:255'],
            'tax_purchases.*.contact_phone' => ['nullable', 'string', 'max:255'],
            'tax_purchases.*.contact_fax' => ['nullable', 'string', 'max:255'],
            'tax_purchases.*.contact_email' => ['nullable', 'email', 'max:255'],
        ];

        $validated = Validator::make($payload, $rules)->validate();

        return array_map(function (array $row) {
            return [
                'purchase_date' => $this->emptyToNull($row['purchase_date'] ?? null),
                'purchase_cost' => $this->toFloatOrNull($row['purchase_cost'] ?? null),
                'policy_type' => $this->emptyToNull($row['policy_type'] ?? null),
                'contact_name' => $this->emptyToNull($row['contact_name'] ?? null),
                'contact_phone' => $this->emptyToNull($row['contact_phone'] ?? null),
                'contact_fax' => $this->emptyToNull($row['contact_fax'] ?? null),
                'contact_email' => $this->emptyToNull($row['contact_email'] ?? null),
            ];
        }, $validated['tax_purchases']);
    }

    /**
     * Stores an uploaded picture file on PICTURE_DISK under
     * PICTURE_DIRECTORY with a randomly generated filename — mirrors
     * CorporateService::storeLogo() exactly.
     */
    protected function storePicture(?UploadedFile $picture): ?string
    {
        if ($picture === null) {
            return null;
        }

        $path = Storage::disk(self::PICTURE_DISK)->putFile(self::PICTURE_DIRECTORY, $picture);

        return $path ?: null;
    }

    /**
     * Normalizes an empty-string input to null, so a "cleared" optional
     * field saves as NULL rather than "" — mirrors StationService/
     * MachineryGroupService/CorporateService's identical helper.
     */
    protected function emptyToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    protected function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Maps a Machinery (with machineryGroup eager-loaded) to the list
     * endpoint's lightweight row shape — no child arrays.
     */
    protected function toListRow(Machinery $machinery): array
    {
        return [
            'id' => $machinery->id,
            'machinery_group_id' => $machinery->machinery_group_id,
            'machinery_group_code' => optional($machinery->machineryGroup)->group_code,
            'station_id' => $machinery->station_id,
            'business_unit_id' => $machinery->business_unit_id,
            'equipment_code' => $machinery->equipment_code,
            'name' => $machinery->name,
            'equipment_type' => $machinery->equipment_type,
            'brand' => $machinery->brand,
            'picture' => $machinery->picture,
            'picture_url' => $machinery->picture
                ? Storage::disk(self::PICTURE_DISK)->url($machinery->picture)
                : null,
            'created_at' => optional($machinery->created_at)->toIso8601String(),
        ];
    }

    /**
     * Maps a Machinery (with machineryGroup/insurances/taxPurchases
     * eager-loaded) to the detail/create/update endpoints' full row shape
     * — includes every field plus the two child arrays.
     */
    protected function toDetailRow(Machinery $machinery): array
    {
        $row = [
            'id' => $machinery->id,
            'machinery_group_id' => $machinery->machinery_group_id,
            'machinery_group_code' => optional($machinery->machineryGroup)->group_code,
            'station_id' => $machinery->station_id,
            'business_unit_id' => $machinery->business_unit_id,
            'equipment_code' => $machinery->equipment_code,
            'name' => $machinery->name,
            'description' => $machinery->description,
            'picture' => $machinery->picture,
            'picture_url' => $machinery->picture
                ? Storage::disk(self::PICTURE_DISK)->url($machinery->picture)
                : null,
            'notes' => $machinery->notes,
            'rpm' => $machinery->rpm,
            'year_made' => $machinery->year_made,
        ];

        foreach (self::TECH_TEXT_FIELDS as $field) {
            $row[$field] = $machinery->{$field};
        }

        $row['insurances'] = $machinery->insurances->map(fn ($insurance) => [
            'id' => $insurance->id,
            'ownership' => $insurance->ownership,
            'insurance_policy_no' => $insurance->insurance_policy_no,
            'insurance_company' => $insurance->insurance_company,
            'insurance_expiry_date' => optional($insurance->insurance_expiry_date)->toDateString(),
            'premium' => $insurance->premium,
            'amount_insured' => $insurance->amount_insured,
        ])->all();

        $row['tax_purchases'] = $machinery->taxPurchases->map(fn ($taxPurchase) => [
            'id' => $taxPurchase->id,
            'purchase_date' => optional($taxPurchase->purchase_date)->toDateString(),
            'purchase_cost' => $taxPurchase->purchase_cost,
            'policy_type' => $taxPurchase->policy_type,
            'contact_name' => $taxPurchase->contact_name,
            'contact_phone' => $taxPurchase->contact_phone,
            'contact_fax' => $taxPurchase->contact_fax,
            'contact_email' => $taxPurchase->contact_email,
        ])->all();

        $row['created_at'] = optional($machinery->created_at)->toIso8601String();

        return $row;
    }
}
