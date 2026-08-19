<?php

namespace App\Livewire\MasterData;

use App\Exceptions\MachineryGroupHasMachineryException;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Services\MachineryGroupService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * KelolaMachineryGroup — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group (Livewire web "Kelola Machinery
 * Group", route name `master-data.machinery-groups`,
 * /master-data/machinery-groups).
 *
 * Reuses MachineryGroupService — the exact same service the API
 * controller (App\Http\Controllers\Api\MachineryGroupController) uses —
 * so validation and business rules stay identical between the web and
 * API entry points. Mirrors App\Livewire\MasterData\KelolaStation's
 * structure closely, with the key structural divergence this screen
 * exists to enforce: `business_unit_id` is NEVER a bound/submitted form
 * property here — it is always derived server-side by the service from
 * the selected Station, regardless of what this component holds.
 * `$selectedBusinessUnitName` below is a DISPLAY-ONLY property (derived
 * via updatedStationId()/openEditForm()) so the admin can see which
 * Business Unit a Machinery Group will belong to before saving — it is
 * never sent to the service, purely a UX convenience matching this
 * screen's tech-spec requirement that the FE "copy business_unit_id
 * client-side for display before submit".
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/machinery-groups with 'auth' + 'role:admin' —
 * EnsureRole::forbidden() aborts(403) (Laravel's default HTML error page,
 * since this is not a JSON request) before this component ever mounts
 * for a non-admin session — same reasoning as KelolaStation's
 * implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * KelolaStation — no JS confirm()/modal dialog, fully driven by
 * wire:click calls and directly testable via Livewire component tests.
 */
#[Layout('master-data.machinery-groups')]
class KelolaMachineryGroup extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    public string $filterStationId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $station_id = '';

    /**
     * Display-only — the name of the Business Unit the currently-selected
     * Station belongs to. Derived via updatedStationId() (create mode) or
     * openEditForm() (edit mode), never bound to a submitted form input,
     * never read by save() or the service — MachineryGroupService::
     * create()/::update() independently re-derive the real
     * business_unit_id server-side from station_id regardless of what
     * this property holds.
     */
    public ?string $selectedBusinessUnitName = null;

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    /**
     * Filter-by-station dropdown above the table — resets to page 1 so
     * the pagination stays consistent with the newly filtered result set.
     */
    public function updatedFilterStationId(): void
    {
        $this->page = 1;
    }

    /**
     * Fires whenever `station_id` changes (the create/edit form's Station
     * picker) — re-derives the display-only selectedBusinessUnitName from
     * the newly selected Station. Purely cosmetic: see this class's own
     * docblock and $selectedBusinessUnitName's docblock.
     */
    public function updatedStationId(string $value): void
    {
        if ($value === '') {
            $this->selectedBusinessUnitName = null;

            return;
        }

        $station = Station::with('businessUnit')->find($value);
        $this->selectedBusinessUnitName = optional(optional($station)->businessUnit)->name;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
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
     * Builds the same Validator::make() rule set as
     * MachineryGroupService::validate() (defense in depth — same DB hits
     * via Rule::exists/Rule::unique, so this never disagrees with the
     * service). Returns the configured (not-yet-validated) Validator.
     *
     * Keys mirror the blade view's @error() directives exactly:
     * `station_id` is bound directly (unprefixed), while group_code/
     * description/unit/workshop_factor/cost_per_equipment all live under
     * the `form.` array binding — mirrors KelolaStation::buildValidator()'s
     * business_unit_id-vs-form.* split exactly.
     */
    protected function buildValidator(): \Illuminate\Validation\Validator
    {
        $groupCodeUniqueRule = Rule::unique('machinery_groups', 'group_code');

        if ($this->editingId !== null) {
            $groupCodeUniqueRule = $groupCodeUniqueRule->ignore($this->editingId);
        }

        $payload = [
            'station_id' => $this->station_id,
            'form' => [
                'group_code' => $this->form['group_code'] !== '' ? $this->form['group_code'] : null,
                'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
                'unit' => $this->form['unit'] !== '' ? $this->form['unit'] : null,
                'workshop_factor' => $this->form['workshop_factor'] !== '' ? $this->form['workshop_factor'] : null,
                'cost_per_equipment' => $this->form['cost_per_equipment'] !== '' ? $this->form['cost_per_equipment'] : null,
            ],
        ];

        $rules = [
            'station_id' => ['required', 'string', Rule::exists('stations', 'id')],
            'form.group_code' => ['required', 'string', 'max:255', $groupCodeUniqueRule],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.unit' => ['nullable', 'string', 'max:255'],
            'form.workshop_factor' => ['nullable', 'numeric'],
            'form.cost_per_equipment' => ['nullable', 'numeric'],
        ];

        $messages = [
            'station_id.required' => 'Station wajib dipilih.',
            'station_id.exists' => 'Station yang dipilih tidak ditemukan.',
            'form.group_code.required' => 'Kode Machinery Group wajib diisi.',
            'form.group_code.max' => 'Kode Machinery Group maksimal 255 karakter.',
            'form.group_code.unique' => 'Kode Machinery Group sudah digunakan.',
            'form.description.max' => 'Deskripsi maksimal 255 karakter.',
            'form.unit.max' => 'Unit maksimal 255 karakter.',
            'form.workshop_factor.numeric' => 'Workshop Factor harus berupa angka.',
            'form.cost_per_equipment.numeric' => 'Cost per Equipment harus berupa angka.',
        ];

        return Validator::make($payload, $rules, $messages);
    }

    /**
     * "Tambah Machinery Group" button — opens the form empty (create
     * mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->station_id = '';
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the machinery
     * group's current field values (edit mode).
     */
    public function openEditForm(string $id): void
    {
        $machineryGroup = MachineryGroup::with('businessUnit')->findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $machineryGroup->id;
        $this->station_id = $machineryGroup->station_id;
        $this->selectedBusinessUnitName = optional($machineryGroup->businessUnit)->name;

        $this->form = [
            'group_code' => (string) ($machineryGroup->group_code ?? ''),
            'description' => (string) ($machineryGroup->description ?? ''),
            'unit' => (string) ($machineryGroup->unit ?? ''),
            'workshop_factor' => $machineryGroup->workshop_factor !== null ? (string) $machineryGroup->workshop_factor : '',
            'cost_per_equipment' => $machineryGroup->cost_per_equipment !== null ? (string) $machineryGroup->cost_per_equipment : '',
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->station_id = '';
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;

        // create: station_id exists, group_code required+unique globally,
        // description/unit/workshop_factor/cost_per_equipment nullable.
        // update: same, group_code unique excluding self. A failure here
        // populates Livewire's $errors bag automatically — rendered under
        // the relevant field, form stays open, nothing is submitted.
        $this->buildValidator()->validate();

        $service = app(MachineryGroupService::class);

        // `business_unit_id` is deliberately never included in this
        // payload — MachineryGroupService::create()/::update() derive it
        // server-side from station_id regardless of what (if anything)
        // this component holds client-side (see $selectedBusinessUnitName's
        // docblock).
        $payload = [
            'station_id' => $this->station_id,
            'group_code' => $this->form['group_code'],
            'description' => $this->form['description'],
            'unit' => $this->form['unit'],
            'workshop_factor' => $this->form['workshop_factor'],
            'cost_per_equipment' => $this->form['cost_per_equipment'],
        ];

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            // The machinery group (or its Station) was deleted by someone
            // else between opening the form and submitting it.
            $this->formErrorMessage = 'Machinery Group tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (MachineryGroupService::validate())
            // caught something the client-side buildValidator() missed
            // (defense in depth, e.g. a race with another admin's
            // create/update). Remapped from the service's plain field
            // keys onto this form's binding keys — station_id stays
            // unprefixed (bound directly), every other field is remapped
            // onto its `form.<field>` key.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'station_id' ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->station_id = '';
        $this->selectedBusinessUnitName = null;
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    /**
     * "Hapus" row action — arms the inline confirmation for this row
     * (business_logic step "delete" is only actually invoked by
     * confirmDelete() below).
     */
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
     * exists → 404 if not → count related Machinery rows → 409
     * MACHINERY_GROUP_HAS_MACHINERY if non-zero (row stays in the list,
     * error surfaced inline) → else delete (row disappears from the list
     * on next render()).
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(MachineryGroupService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (MachineryGroupHasMachineryException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Machinery Group tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(MachineryGroupService::class);

        $result = $service->listMachineryGroups(
            $this->page,
            $this->perPage,
            $this->filterStationId !== '' ? $this->filterStationId : null
        );

        return view('livewire.master-data.kelola-machinery-group', [
            'machineryGroups' => $result['data'],
            'meta' => $result['meta'],
            'stationOptions' => $service->stationOptions(),
        ]);
    }
}
