<?php

namespace App\Livewire\MasterData;

use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Services\MachineryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * KelolaMachinery — screen-031--kelola-machinery / usecase-031--kelola-machinery
 * (Livewire web "Kelola Machinery", route name `master-data.machinery`,
 * /master-data/machinery). The LAST screen of this master-data round.
 *
 * Reuses MachineryService — the exact same service the API controller
 * (App\Http\Controllers\Api\MachineryController) uses — so validation and
 * business rules stay identical between the web and API entry points.
 * Mirrors App\Livewire\MasterData\KelolaMachineryGroup's structure
 * closely, with the divergences this screen's field set requires:
 *
 *  - `machinery_group_id` is a bare top-level bound property (like
 *    `station_id` on KelolaMachineryGroup); `station_id`/
 *    `business_unit_id` are NEVER bound/submitted form properties — both
 *    are always derived server-side by the service from the selected
 *    MachineryGroup, regardless of what this component holds.
 *    `$selectedStationName`/`$selectedBusinessUnitName` are DISPLAY-ONLY
 *    properties (derived via updatedMachineryGroupId()/openEditForm()),
 *    never sent to the service.
 *  - `$insurances`/`$taxPurchases` are single-element public arrays
 *    (index 0 only — one Asuransi row and one Pajak/Pembelian row per
 *    machinery in practice, not a repeatable history grid), bound via
 *    `wire:model="insurances.0.<field>"`. save() sends `[$row]` to the
 *    service only if the row has at least one non-blank field, else `[]`
 *    (see rowIsBlank()) — the service still applies replace-all
 *    semantics on update() (see MachineryService::update()'s docblock),
 *    so this component always sends both keys, even as an empty array.
 *  - `picture` is a WithFileUploads upload, mirrors KelolaCorporate's
 *    `logo` handling exactly (PREVIEWABLE_PICTURE_EXTENSIONS guards the
 *    view's ->temporaryUrl() call the same way KelolaCorporate's
 *    PREVIEWABLE_LOGO_EXTENSIONS does).
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/machinery with 'auth' + 'role:admin' —
 * EnsureRole::forbidden() aborts(403) before this component ever mounts
 * for a non-admin session.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * every sibling screen in this round. No delete-guard exists for this
 * screen (see MachineryService::delete()'s docblock) — confirmDelete()
 * therefore has no guard-exception branch to handle, unlike
 * KelolaMachineryGroup::confirmDelete().
 */
#[Layout('master-data.machinery')]
class KelolaMachinery extends Component
{
    use WithFileUploads;

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

    private const PREVIEWABLE_PICTURE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public int $page = 1;

    public int $perPage = 20;

    public string $filterMachineryGroupId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $machinery_group_id = '';

    public ?string $selectedStationName = null;

    public ?string $selectedBusinessUnitName = null;

    /** @var array<string, string> */
    public array $form = [];

    public $picture = null;

    public ?string $existingPictureUrl = null;

    /** @var array<int, array<string, string>> */
    public array $insurances = [];

    /** @var array<int, array<string, string>> */
    public array $taxPurchases = [];

    public ?string $formErrorMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function updatedFilterMachineryGroupId(): void
    {
        $this->page = 1;
    }

    /**
     * Fires whenever `machinery_group_id` changes — re-derives the
     * display-only station/business-unit names from the newly selected
     * MachineryGroup. Purely cosmetic (mirrors KelolaMachineryGroup::
     * updatedStationId()).
     */
    public function updatedMachineryGroupId(string $value): void
    {
        if ($value === '') {
            $this->selectedStationName = null;
            $this->selectedBusinessUnitName = null;

            return;
        }

        $group = MachineryGroup::with(['station', 'businessUnit'])->find($value);
        $this->selectedStationName = optional(optional($group)->station)->name;
        $this->selectedBusinessUnitName = optional(optional($group)->businessUnit)->name;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        $form = [
            'equipment_code' => '',
            'name' => '',
            'description' => '',
        ];

        foreach (self::TECH_TEXT_FIELDS as $field) {
            $form[$field] = '';
        }

        $form['rpm'] = '';
        $form['year_made'] = '';

        return $form;
    }

    protected function emptyInsuranceRow(): array
    {
        return [
            'ownership' => '',
            'insurance_policy_no' => '',
            'insurance_company' => '',
            'insurance_expiry_date' => '',
            'premium' => '',
            'amount_insured' => '',
        ];
    }

    protected function emptyTaxPurchaseRow(): array
    {
        return [
            'purchase_date' => '',
            'purchase_cost' => '',
            'policy_type' => '',
            'contact_name' => '',
            'contact_phone' => '',
            'contact_fax' => '',
            'contact_email' => '',
        ];
    }

    /**
     * A machinery unit only ever has one Asuransi row and one Pajak/
     * Pembelian row in practice (not a true repeatable history grid) —
     * the form binds directly to index 0 of each array (see
     * openCreateForm()/openEditForm()/save()), so no add/remove-row
     * actions exist anymore.
     */
    protected function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Client-side mirror of MachineryService::validate() (defense in
     * depth). Keys mirror the blade view's @error() directives:
     * `machinery_group_id` is bound directly, every parent field lives
     * under `form.*`. Child-row arrays are validated leniently here
     * (nullable everywhere) — the service is the authority for the
     * child-row rules (date/numeric formats), surfaced via save()'s
     * ValidationException remap on a mismatch.
     */
    protected function buildValidator(): \Illuminate\Validation\Validator
    {
        $equipmentCodeUniqueRule = Rule::unique('machinery', 'equipment_code');

        if ($this->editingId !== null) {
            $equipmentCodeUniqueRule = $equipmentCodeUniqueRule->ignore($this->editingId);
        }

        $payload = [
            'machinery_group_id' => $this->machinery_group_id,
            'form' => [
                'equipment_code' => $this->form['equipment_code'] !== '' ? $this->form['equipment_code'] : null,
                'name' => $this->form['name'] !== '' ? $this->form['name'] : null,
            ],
        ];

        $rules = [
            'machinery_group_id' => ['required', 'string', Rule::exists('machinery_groups', 'id')],
            'form.equipment_code' => ['required', 'string', 'max:255', $equipmentCodeUniqueRule],
            'form.name' => ['required', 'string', 'max:255'],
        ];

        $messages = [
            'machinery_group_id.required' => 'Machinery Group wajib dipilih.',
            'machinery_group_id.exists' => 'Machinery Group yang dipilih tidak ditemukan.',
            'form.equipment_code.required' => 'Kode Equipment wajib diisi.',
            'form.equipment_code.max' => 'Kode Equipment maksimal 255 karakter.',
            'form.equipment_code.unique' => 'Kode Equipment sudah digunakan.',
            'form.name.required' => 'Nama wajib diisi.',
            'form.name.max' => 'Nama maksimal 255 karakter.',
        ];

        return Validator::make($payload, $rules, $messages);
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->machinery_group_id = '';
        $this->selectedStationName = null;
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->picture = null;
        $this->existingPictureUrl = null;
        $this->insurances = [$this->emptyInsuranceRow()];
        $this->taxPurchases = [$this->emptyTaxPurchaseRow()];
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    public function openEditForm(string $id): void
    {
        $machinery = Machinery::with(['machineryGroup.station', 'machineryGroup.businessUnit', 'insurances', 'taxPurchases'])
            ->findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $machinery->id;
        $this->machinery_group_id = (string) $machinery->machinery_group_id;
        $this->selectedStationName = optional(optional($machinery->machineryGroup)->station)->name;
        $this->selectedBusinessUnitName = optional(optional($machinery->machineryGroup)->businessUnit)->name;

        $this->form = [
            'equipment_code' => (string) ($machinery->equipment_code ?? ''),
            'name' => (string) ($machinery->name ?? ''),
            'description' => (string) ($machinery->description ?? ''),
            'rpm' => $machinery->rpm !== null ? (string) $machinery->rpm : '',
            'year_made' => $machinery->year_made !== null ? (string) $machinery->year_made : '',
        ];

        foreach (self::TECH_TEXT_FIELDS as $field) {
            $this->form[$field] = (string) ($machinery->{$field} ?? '');
        }

        $this->picture = null;
        $this->existingPictureUrl = $machinery->picture
            ? Storage::disk(MachineryService::PICTURE_DISK)->url($machinery->picture)
            : null;

        // Only one Asuransi / Pajak & Pembelian row is ever used per
        // machinery in practice — the form binds a single fixed-field
        // section, not a repeatable grid. If legacy data somehow has
        // more than one row, only the first is shown/editable here;
        // save() replaces all rows with just this one on the next save.
        $insurance = $machinery->insurances->first();
        $this->insurances = [[
            'ownership' => (string) (optional($insurance)->ownership ?? ''),
            'insurance_policy_no' => (string) (optional($insurance)->insurance_policy_no ?? ''),
            'insurance_company' => (string) (optional($insurance)->insurance_company ?? ''),
            'insurance_expiry_date' => optional(optional($insurance)->insurance_expiry_date)->toDateString() ?? '',
            'premium' => optional($insurance)->premium !== null ? (string) $insurance->premium : '',
            'amount_insured' => optional($insurance)->amount_insured !== null ? (string) $insurance->amount_insured : '',
        ]];

        $taxPurchase = $machinery->taxPurchases->first();
        $this->taxPurchases = [[
            'purchase_date' => optional(optional($taxPurchase)->purchase_date)->toDateString() ?? '',
            'purchase_cost' => optional($taxPurchase)->purchase_cost !== null ? (string) $taxPurchase->purchase_cost : '',
            'policy_type' => (string) (optional($taxPurchase)->policy_type ?? ''),
            'contact_name' => (string) (optional($taxPurchase)->contact_name ?? ''),
            'contact_phone' => (string) (optional($taxPurchase)->contact_phone ?? ''),
            'contact_fax' => (string) (optional($taxPurchase)->contact_fax ?? ''),
            'contact_email' => (string) (optional($taxPurchase)->contact_email ?? ''),
        ]];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->machinery_group_id = '';
        $this->selectedStationName = null;
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->picture = null;
        $this->existingPictureUrl = null;
        $this->insurances = [];
        $this->taxPurchases = [];
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     * `insurances`/`taxPurchases` are always sent (replace-all semantics
     * — MachineryService::update() replaces child rows whenever the key
     * is present, and this component always sends both keys, even as
     * empty arrays).
     */
    public function save(): void
    {
        $this->formErrorMessage = null;

        $this->buildValidator()->validate();

        if ($this->picture !== null) {
            $this->validate([
                'picture' => ['file', 'mimes:jpg,jpeg,png', 'max:2048'],
            ], [
                'picture.mimes' => 'Gambar harus berformat JPG atau PNG.',
                'picture.max' => 'Ukuran gambar maksimal 2MB.',
            ]);
        }

        $service = app(MachineryService::class);

        $insuranceRow = $this->insurances[0] ?? [];
        $taxPurchaseRow = $this->taxPurchases[0] ?? [];

        $payload = array_merge($this->form, [
            'machinery_group_id' => $this->machinery_group_id,
            // Single-row sections: an all-blank row means "no data", so
            // nothing is sent (no empty child record gets created).
            'insurances' => $this->rowIsBlank($insuranceRow) ? [] : [$insuranceRow],
            'tax_purchases' => $this->rowIsBlank($taxPurchaseRow) ? [] : [$taxPurchaseRow],
        ]);

        /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $picture */
        $picture = $this->picture;

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload, $picture);
            } else {
                $service->create($payload, $picture);
            }
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Machinery (atau Machinery Group terkait) tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'machinery_group_id' ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->machinery_group_id = '';
        $this->selectedStationName = null;
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->picture = null;
        $this->existingPictureUrl = null;
        $this->insurances = [];
        $this->taxPurchases = [];
        $this->resetValidation();
    }

    public function askDelete(string $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->deleteErrorMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Confirming the delete — business_logic step "delete": validate id
     * exists → 404 if not → delete child rows → delete Machinery. NO
     * guard/exception branch — this screen has no delete-guard (see
     * MachineryService::delete()'s docblock).
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(MachineryService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Machinery tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(MachineryService::class);

        $result = $service->listMachinery(
            $this->page,
            $this->perPage,
            $this->filterMachineryGroupId !== '' ? $this->filterMachineryGroupId : null
        );

        return view('livewire.master-data.kelola-machinery', [
            'machineryRows' => $result['data'],
            'meta' => $result['meta'],
            'machineryGroupOptions' => $service->machineryGroupOptions(),
        ]);
    }
}
