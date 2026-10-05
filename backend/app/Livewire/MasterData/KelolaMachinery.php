<?php

namespace App\Livewire\MasterData;

use App\Exceptions\MachineryGroupHasMachineryException;
use App\Livewire\Concerns\HasFilterReset;
use App\Livewire\Concerns\ValidatesUploadOnSelect;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Rules\RealImage;
use App\Rules\UniqueCaseInsensitive;
use App\Services\MachineryGroupService;
use App\Services\MachineryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
 *    `production_line_id` are NEVER bound/submitted form properties — both
 *    are always derived server-side by the service from the selected
 *    MachineryGroup, regardless of what this component holds.
 *    `$selectedStationName`/`$selectedProductionLineName` are DISPLAY-ONLY
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
    use HasFilterReset;
    use ValidatesUploadOnSelect;
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

    public ?string $selectedProductionLineName = null;

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

    /*
     |--------------------------------------------------------------------
     | Penggabungan screen-031 + screen-033 (2026-09-30)
     |--------------------------------------------------------------------
     | Layar ini menyerap Kelola Machinery Group. Anggota milik grup diberi
     | akhiran/awalan `Group` alih-alih memakai satu set properti bersama
     | dengan diskriminator: kedua alur CRUD memang independen, dan
     | pemisahan nama membuat 23 test Machinery yang sudah ada tidak perlu
     | disentuh sama sekali.
     */

    /** 'grup' (hierarkis, bawaan) atau 'rata' (seluruh mesin satu daftar). */
    public string $viewMode = 'grup';

    /** Dipakai kedua mode; mencocokkan grup DAN mesin. */
    public string $search = '';

    /**
     * ID grup yang sedang terbuka. Sengaja TIDAK ikut berpindah halaman —
     * lihat gotoPage()/nextPage(): halaman baru selalu mulai tertutup,
     * kecuali sedang mencari, di mana grup yang cocok dibuka otomatis.
     *
     * @var array<int, string>
     */
    public array $expandedGroupIds = [];

    // ── state CRUD Machinery Group (diserap dari KelolaMachineryGroup) ──

    public string $filterStationId = '';

    public bool $showGroupForm = false;

    public ?string $editingGroupId = null;

    public string $station_id = '';

    /** Tampilan saja; production_line_id selalu diturunkan server dari Station. */
    public ?string $selectedGroupProductionLineName = null;

    /** @var array<string, string> */
    public array $groupForm = [];

    public ?string $groupFormErrorMessage = null;

    public ?string $confirmingDeleteGroupId = null;

    public ?string $deleteGroupErrorMessage = null;

    /** Umpan balik sukses simpan/hapus mesin & grup (temuan audit #12). */
    public ?string $successMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->groupForm = $this->emptyGroupForm();
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
            $this->selectedProductionLineName = null;

            return;
        }

        $group = MachineryGroup::with(['station', 'productionLine'])->find($value);
        $this->selectedStationName = optional(optional($group)->station)->name;
        $this->selectedProductionLineName = optional(optional($group)->productionLine)->name;
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
        $equipmentCodeUniqueRule = UniqueCaseInsensitive::on('machinery', 'equipment_code');

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

    /**
     * $groupId: tombol "+ Mesin" di baris grup langsung memilih grup itu
     * (temuan audit #9a — dulu form terbuka dengan grup kosong).
     */
    public function openCreateForm(?string $groupId = null): void
    {
        $this->clearFeedback();
        $this->resetValidation();
        $this->editingId = null;
        $this->machinery_group_id = '';
        $this->selectedStationName = null;
        $this->selectedProductionLineName = null;

        if ($groupId !== null && MachineryGroup::whereKey($groupId)->exists()) {
            $this->machinery_group_id = $groupId;
            $this->updatedMachineryGroupId($groupId);
        }

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
        $machinery = Machinery::with(['machineryGroup.station', 'machineryGroup.productionLine', 'insurances', 'taxPurchases'])
            ->findOrFail($id);

        $this->clearFeedback();
        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $machinery->id;
        $this->machinery_group_id = (string) $machinery->machinery_group_id;
        $this->selectedStationName = optional(optional($machinery->machineryGroup)->station)->name;
        $this->selectedProductionLineName = optional(optional($machinery->machineryGroup)->productionLine)->name;

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
        $this->selectedProductionLineName = null;
        $this->form = $this->emptyForm();
        $this->picture = null;
        $this->existingPictureUrl = null;
        $this->insurances = [];
        $this->taxPurchases = [];
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function pictureValidation(): array
    {
        return [[
            'picture' => ['file', 'mimes:jpg,jpeg,png', 'max:2048', new RealImage('Gambar')],
        ], [
            'picture.file' => 'Gambar harus berupa file gambar.',
            'picture.mimes' => 'Gambar harus berformat JPG atau PNG.',
            'picture.max' => 'Ukuran gambar maksimal 2MB.',
        ]];
    }

    /**
     * Gambar mesin dicek saat dipilih — lihat ValidatesUploadOnSelect.
     */
    public function updatedPicture(): void
    {
        if ($this->picture !== null) {
            $this->validateUploadNow('picture', ...$this->pictureValidation());
        }
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
        $this->successMessage = null;

        $this->buildValidator()->validate();

        if ($this->picture !== null) {
            $this->validate(...$this->pictureValidation());
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

        /** @var TemporaryUploadedFile|null $picture */
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
            // Error baris Asuransi / Pajak & Pembelian datang berkunci
            // 'insurances.0.premium' / 'tax_purchases.0.purchase_cost'. Dulu
            // semuanya dipetakan ke 'form.<kunci>' yang tidak pernah
            // dirender view — Simpan gagal tanpa pesan apa pun (temuan
            // audit #9b). Sekarang dipetakan ke properti yang di-bind input
            // itu (insurances.0.* / taxPurchases.0.*), dan view merendernya.
            foreach ($e->errors() as $field => $messages) {
                $this->addError($this->formErrorKey($field), $messages[0] ?? 'Validasi gagal.');
            }

            $this->formErrorMessage = 'Data belum tersimpan — periksa kembali isian yang ditandai.';

            return;
        }

        $this->successMessage = $this->editingId !== null ? 'Mesin berhasil diperbarui.' : 'Mesin berhasil ditambahkan.';
        $this->deleteErrorMessage = null;
        $this->deleteGroupErrorMessage = null;
        $this->showForm = false;
        $this->editingId = null;
        $this->machinery_group_id = '';
        $this->selectedStationName = null;
        $this->selectedProductionLineName = null;
        $this->form = $this->emptyForm();
        $this->picture = null;
        $this->existingPictureUrl = null;
        $this->insurances = [];
        $this->taxPurchases = [];
        $this->resetValidation();
    }

    /**
     * Kunci error service → kunci properti yang di-bind view.
     */
    protected function formErrorKey(string $field): string
    {
        if ($field === 'machinery_group_id' || $field === 'picture') {
            return $field;
        }

        if (str_starts_with($field, 'insurances.')) {
            return $field;
        }

        if (str_starts_with($field, 'tax_purchases.')) {
            return 'taxPurchases.'.substr($field, strlen('tax_purchases.'));
        }

        return "form.$field";
    }

    /**
     * Pesan sukses/gagal lama dihapus begitu pengguna memulai aksi baru —
     * dulu pesan error hapus tetap terpampang setelah aksi berikutnya
     * berhasil (temuan audit #12).
     */
    protected function clearFeedback(): void
    {
        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->deleteGroupErrorMessage = null;
    }

    public function askDelete(string $id): void
    {
        $this->clearFeedback();
        $this->confirmingDeleteId = $id;
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
            $this->successMessage = 'Mesin berhasil dihapus.';
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

    // ──────────────────────────────────────────────────────────────────
    // Mode tampilan, pencarian, buka/tutup grup
    // ──────────────────────────────────────────────────────────────────

    /**
     * Alih Grup <-> Rata. Selalu kembali ke halaman 1: satuan paginasinya
     * berbeda (grup vs mesin), jadi "halaman 5" di satu mode tidak punya
     * arti yang sama di mode lain. Kata kunci pencarian DIPERTAHANKAN agar
     * tidak perlu diketik ulang.
     */
    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['grup', 'rata'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->page = 1;
        $this->expandedGroupIds = [];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->expandedGroupIds = [];
    }

    public function updatedFilterStationId(): void
    {
        $this->page = 1;
        $this->expandedGroupIds = [];
    }

    public function toggleGroup(string $groupId): void
    {
        $this->expandedGroupIds = in_array($groupId, $this->expandedGroupIds, true)
            ? array_values(array_diff($this->expandedGroupIds, [$groupId]))
            : [...$this->expandedGroupIds, $groupId];
    }

    public function isGroupExpanded(string $groupId): bool
    {
        return in_array($groupId, $this->expandedGroupIds, true);
    }

    // ──────────────────────────────────────────────────────────────────
    // CRUD Machinery Group (diserap dari KelolaMachineryGroup)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    protected function emptyGroupForm(): array
    {
        return [
            'group_code' => '',
            'description' => '',
            'unit' => '',
            'workshop_factor' => '',
            'cost_per_equipment' => '',
        ];
    }

    /**
     * Fires when the group form's Station picker changes — re-derives the
     * display-only production line name. Cosmetic only: the server derives
     * production_line_id from station_id regardless.
     */
    public function updatedStationId(string $value): void
    {
        if ($value === '') {
            $this->selectedGroupProductionLineName = null;

            return;
        }

        $station = Station::with('productionLine')->find($value);
        $this->selectedGroupProductionLineName = optional(optional($station)->productionLine)->name;
    }

    protected function buildGroupValidator(): \Illuminate\Validation\Validator
    {
        $groupCodeUniqueRule = UniqueCaseInsensitive::on('machinery_groups', 'group_code');

        if ($this->editingGroupId !== null) {
            $groupCodeUniqueRule = $groupCodeUniqueRule->ignore($this->editingGroupId);
        }

        $payload = [
            'station_id' => $this->station_id,
            'groupForm' => [
                'group_code' => $this->groupForm['group_code'] !== '' ? $this->groupForm['group_code'] : null,
                'description' => $this->groupForm['description'] !== '' ? $this->groupForm['description'] : null,
                'unit' => $this->groupForm['unit'] !== '' ? $this->groupForm['unit'] : null,
                'workshop_factor' => $this->groupForm['workshop_factor'] !== '' ? $this->groupForm['workshop_factor'] : null,
                'cost_per_equipment' => $this->groupForm['cost_per_equipment'] !== '' ? $this->groupForm['cost_per_equipment'] : null,
            ],
        ];

        $rules = [
            'station_id' => ['required', 'string', Rule::exists('stations', 'id')],
            'groupForm.group_code' => ['required', 'string', 'max:255', $groupCodeUniqueRule],
            'groupForm.description' => ['nullable', 'string', 'max:255'],
            'groupForm.unit' => ['nullable', 'string', 'max:255'],
            'groupForm.workshop_factor' => ['nullable', 'numeric'],
            'groupForm.cost_per_equipment' => ['nullable', 'numeric'],
        ];

        $messages = [
            'station_id.required' => 'Station wajib dipilih.',
            'station_id.exists' => 'Station yang dipilih tidak ditemukan.',
            'groupForm.group_code.required' => 'Kode Machinery Group wajib diisi.',
            'groupForm.group_code.max' => 'Kode Machinery Group maksimal 255 karakter.',
            'groupForm.group_code.unique' => 'Kode Machinery Group sudah digunakan.',
            'groupForm.description.max' => 'Deskripsi maksimal 255 karakter.',
            'groupForm.unit.max' => 'Unit maksimal 255 karakter.',
            'groupForm.workshop_factor.numeric' => 'Workshop Factor harus berupa angka.',
            'groupForm.cost_per_equipment.numeric' => 'Cost per Equipment harus berupa angka.',
        ];

        return Validator::make($payload, $rules, $messages);
    }

    public function openCreateGroupForm(): void
    {
        $this->clearFeedback();
        $this->resetValidation();
        $this->editingGroupId = null;
        $this->station_id = '';
        $this->selectedGroupProductionLineName = null;
        $this->groupForm = $this->emptyGroupForm();
        $this->groupFormErrorMessage = null;
        $this->showGroupForm = true;
    }

    public function openEditGroupForm(string $id): void
    {
        $machineryGroup = MachineryGroup::with('productionLine')->findOrFail($id);

        $this->clearFeedback();
        $this->resetValidation();
        $this->groupFormErrorMessage = null;
        $this->editingGroupId = $machineryGroup->id;
        $this->station_id = $machineryGroup->station_id;
        $this->selectedGroupProductionLineName = optional($machineryGroup->productionLine)->name;

        $this->groupForm = [
            'group_code' => (string) ($machineryGroup->group_code ?? ''),
            'description' => (string) ($machineryGroup->description ?? ''),
            'unit' => (string) ($machineryGroup->unit ?? ''),
            'workshop_factor' => $machineryGroup->workshop_factor !== null ? (string) $machineryGroup->workshop_factor : '',
            'cost_per_equipment' => $machineryGroup->cost_per_equipment !== null ? (string) $machineryGroup->cost_per_equipment : '',
        ];

        $this->showGroupForm = true;
    }

    public function closeGroupForm(): void
    {
        $this->showGroupForm = false;
        $this->editingGroupId = null;
        $this->station_id = '';
        $this->selectedGroupProductionLineName = null;
        $this->groupForm = $this->emptyGroupForm();
        $this->groupFormErrorMessage = null;
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        $this->groupFormErrorMessage = null;
        $this->successMessage = null;

        $this->buildGroupValidator()->validate();

        $service = app(MachineryGroupService::class);

        // production_line_id deliberately absent: the service derives it
        // from station_id server-side, never from client input.
        $payload = [
            'station_id' => $this->station_id,
            'group_code' => $this->groupForm['group_code'],
            'description' => $this->groupForm['description'],
            'unit' => $this->groupForm['unit'],
            'workshop_factor' => $this->groupForm['workshop_factor'],
            'cost_per_equipment' => $this->groupForm['cost_per_equipment'],
        ];

        try {
            if ($this->editingGroupId !== null) {
                $service->update($this->editingGroupId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            $this->groupFormErrorMessage = 'Machinery Group tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'station_id' ? $field : "groupForm.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = $this->editingGroupId !== null ? 'Machinery Group berhasil diperbarui.' : 'Machinery Group berhasil ditambahkan.';
        $this->deleteErrorMessage = null;
        $this->deleteGroupErrorMessage = null;
        $this->showGroupForm = false;
        $this->editingGroupId = null;
        $this->station_id = '';
        $this->selectedGroupProductionLineName = null;
        $this->groupForm = $this->emptyGroupForm();
        $this->resetValidation();
    }

    public function askDeleteGroup(string $id): void
    {
        $this->clearFeedback();
        $this->confirmingDeleteGroupId = $id;
    }

    public function cancelDeleteGroup(): void
    {
        $this->confirmingDeleteGroupId = null;
    }

    public function confirmDeleteGroup(): void
    {
        if ($this->confirmingDeleteGroupId === null) {
            return;
        }

        $service = app(MachineryGroupService::class);

        try {
            $service->delete($this->confirmingDeleteGroupId);
            $this->confirmingDeleteGroupId = null;
            $this->deleteGroupErrorMessage = null;
            $this->successMessage = 'Machinery Group berhasil dihapus.';
        } catch (MachineryGroupHasMachineryException $e) {
            $this->confirmingDeleteGroupId = null;
            $this->deleteGroupErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteGroupId = null;
            $this->deleteGroupErrorMessage = 'Machinery Group tidak ditemukan, mungkin sudah dihapus.';
        }
    }

    /**
     * Bawaan filter untuk "Reset filter" (x-filter.bar) — sama dengan
     * deklarasi properti di atas.
     *
     * @return array<string, string>
     */
    protected function filterDefaults(): array
    {
        return [
            'search' => '',
            'filterMachineryGroupId' => '',
            'filterStationId' => '',
        ];
    }

    /**
     * Sama dengan updatedSearch() / updatedFilterStationId(): grup yang
     * terbuka milik hasil filter lama, jadi ikut dikosongkan.
     */
    protected function afterFilterReset(): void
    {
        $this->expandedGroupIds = [];
    }

    /**
     * Mode Grup memaginasi daftar GRUP; mode Rata memaginasi daftar MESIN.
     * Dua endpoint service yang berbeda, jadi mesin yang tampil karena
     * grupnya dibuka mustahil ikut terhitung sebagai baris halaman.
     */
    public function render()
    {
        $machineryService = app(MachineryService::class);
        $search = $this->search !== '' ? $this->search : null;

        if ($this->viewMode === 'rata') {
            $result = $machineryService->listMachinery(
                $this->page,
                $this->perPage,
                $this->filterMachineryGroupId !== '' ? $this->filterMachineryGroupId : null,
                false,
                $search,
            );

            return view('livewire.master-data.kelola-machinery', [
                'viewMode' => 'rata',
                'machineryRows' => $result['data'],
                'meta' => $result['meta'],
                'groupRows' => [],
                'machineryByGroup' => [],
                'ungroupedRows' => [],
                'ungroupedCount' => 0,
                'machineryGroupOptions' => $machineryService->machineryGroupOptions(),
                'stationOptions' => app(MachineryGroupService::class)->stationOptions(),
            ]);
        }

        $groupService = app(MachineryGroupService::class);

        $result = $groupService->listMachineryGroups(
            $this->page,
            $this->perPage,
            $this->filterStationId !== '' ? $this->filterStationId : null,
            $search,
        );

        // A group whose MACHINE matched is expanded automatically — a hit
        // hidden behind a collapsed row is indistinguishable from no hit.
        $autoExpanded = collect($result['data'])
            ->filter(fn (array $row) => $row['has_search_match_in_machinery'] ?? false)
            ->pluck('id')
            ->all();

        $expanded = array_values(array_unique([...$this->expandedGroupIds, ...$autoExpanded]));

        $machineryByGroup = [];
        foreach ($expanded as $groupId) {
            $machineryByGroup[$groupId] = $machineryService
                ->listMachinery(1, 100, $groupId, false, $search)['data'];
        }

        // Machines with no group would otherwise vanish from this screen.
        $ungroupedCount = (int) ($result['meta']['ungrouped_machinery_count'] ?? 0);
        $ungroupedRows = $ungroupedCount > 0
            ? $machineryService->listMachinery(1, 100, null, true, $search)['data']
            : [];

        // Saat mencari, wadah "Tanpa grup" hanya tampil bila ADA mesin tanpa
        // grup yang cocok, dan jumlahnya = yang cocok (temuan audit #15 —
        // dulu tampil dengan jumlah total walau tak satu pun cocok).
        if ($search !== null) {
            $ungroupedCount = count($ungroupedRows);
        }

        return view('livewire.master-data.kelola-machinery', [
            'viewMode' => 'grup',
            'machineryRows' => [],
            'meta' => $result['meta'],
            'groupRows' => $result['data'],
            'expandedGroupIds' => $expanded,
            'machineryByGroup' => $machineryByGroup,
            'ungroupedRows' => $ungroupedRows,
            'ungroupedCount' => $ungroupedCount,
            'machineryGroupOptions' => $machineryService->machineryGroupOptions(),
            'stationOptions' => $groupService->stationOptions(),
        ]);
    }
}
