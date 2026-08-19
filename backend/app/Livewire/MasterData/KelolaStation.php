<?php

namespace App\Livewire\MasterData;

use App\Enums\StationType;
use App\Exceptions\StationHasMachineryException;
use App\Models\Station;
use App\Services\StationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * KelolaStation — screen-030--kelola-station /
 * usecase-030--kelola-station (Livewire web "Kelola Station", route name
 * `master-data.stations`, /master-data/stations).
 *
 * Reuses StationService — the exact same service the API controller
 * (App\Http\Controllers\Api\StationController) uses — so validation and
 * business rules stay identical between the web and API entry points.
 * Mirrors App\Livewire\MasterData\KelolaBusinessUnit's structure, minus
 * everything logo-related (no WithFileUploads, no TemporaryUploadedFile,
 * no $logo/$existingLogoUrl properties) — Station has no file-upload
 * field.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/stations with 'auth' + 'role:admin' —
 * EnsureRole::forbidden() aborts(403) (Laravel's default HTML error page,
 * since this is not a JSON request) before this component ever mounts
 * for a non-admin session — same reasoning as KelolaBusinessUnit's
 * implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * KelolaBusinessUnit — no JS confirm()/modal dialog, fully driven by
 * wire:click calls and directly testable via Livewire component tests.
 *
 * Cross-field validation ("is_active may only be true when type is not
 * 'other'") is done manually inside save() via Validator::make(...)
 * ->after(...)->validate() rather than the declarative rules()/
 * $this->validate() shortcut — Livewire's declarative rules() array
 * can't express a rule that depends on two properties at once the same
 * way Illuminate\Validation\Validator::after() closures can, and save()
 * already needs a try/catch around the service call regardless, so
 * folding validation into the same manual-Validator call keeps both
 * concerns (client-side pre-check + server-side re-check) reading the
 * exact same rule set.
 */
#[Layout('master-data.stations')]
class KelolaStation extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    public string $filterBusinessUnitId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $business_unit_id = '';

    public string $type = '';

    public bool $is_active = true;

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
     * Filter-by-business-unit dropdown above the table — resets to page 1
     * so the pagination stays consistent with the newly filtered result
     * set.
     */
    public function updatedFilterBusinessUnitId(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return [
            'name' => '',
            'code' => '',
            'description' => '',
        ];
    }

    /**
     * Builds the same Validator::make() rule set as
     * StationService::validate() (defense in depth — same DB hits via
     * Rule::exists/Rule::unique, so this never disagrees with the
     * service). Returns the configured Validator (not yet ->validate()d)
     * so save() can attach the is_active/type cross-field ->after()
     * closure before running it.
     */
    protected function buildValidator(): \Illuminate\Validation\Validator
    {
        $codeUniqueRule = Rule::unique('stations', 'code');

        if ($this->editingId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($this->editingId);
        }

        // Keys mirror the blade view's @error() directives exactly:
        // business_unit_id/type/is_active are bound directly (unprefixed),
        // while name/code/description live under the `form.` array
        // binding (`form.name`, `form.code`, `form.description`) — same
        // split save()'s catch(ValidationException) block below already
        // uses when remapping the service's plain field-keyed errors onto
        // this form. Using unprefixed keys here would populate Livewire's
        // error bag under 'name'/'code'/'description' instead, which the
        // view's `@error('form.name')`/`@error('form.code')`/
        // `@error('form.description')` directives would never match — the
        // error would silently fail to render under the input.
        //
        // `form` MUST be a genuinely nested array here (not a flat payload
        // with a literal 'form.name' string key) — Laravel's Validator
        // resolves dotted rule keys ('form.name') via Arr::get()/dot
        // notation against the DATA array's real nested structure, not
        // against a literal dotted key, so a flat 'form.name' => ... entry
        // would never be found and would always fail as missing/null.
        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'form' => [
                'name' => $this->form['name'] ?? '',
                'code' => $this->form['code'] !== '' ? $this->form['code'] : null,
                'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
            ],
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'form.name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_map(fn (StationType $case) => $case->value, StationType::cases()))],
            'is_active' => ['required', 'boolean'],
            'form.code' => ['nullable', 'string', 'max:255', $codeUniqueRule],
            'form.description' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'form.name.required' => 'Nama station wajib diisi.',
            'form.name.max' => 'Nama station maksimal 255 karakter.',
            'type.required' => 'Tipe station wajib dipilih.',
            'type.in' => 'Tipe station tidak valid.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
            'form.code.max' => 'Kode station maksimal 255 karakter.',
            'form.code.unique' => 'Kode station sudah digunakan.',
            'form.description.max' => 'Deskripsi maksimal 255 karakter.',
        ];

        $validator = Validator::make($payload, $rules, $messages);

        $validator->after(function ($validator) use ($payload) {
            if ($payload['is_active'] === true && $payload['type'] === StationType::Other->value) {
                $validator->errors()->add(
                    'is_active',
                    'Station dengan tipe Other tidak boleh berstatus aktif — set Status menjadi nonaktif.'
                );
            }
        });

        return $validator;
    }

    /**
     * "Tambah Station" button — opens the form empty (create mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->type = '';
        $this->is_active = true;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the station's
     * current field values (edit mode).
     */
    public function openEditForm(string $id): void
    {
        $station = Station::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $station->id;
        $this->business_unit_id = $station->business_unit_id;
        $this->type = $station->type instanceof StationType ? $station->type->value : (string) $station->type;
        $this->is_active = (bool) $station->is_active;

        $this->form = [
            'name' => (string) $station->name,
            'code' => (string) ($station->code ?? ''),
            'description' => (string) ($station->description ?? ''),
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->type = '';
        $this->is_active = true;
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

        // create: business_unit_id exists, name required, type
        // required+in-enum, is_active required boolean + cross-field
        // "not true when type=other", code nullable+unique globally,
        // description nullable. update: same, code unique excluding
        // self. A failure here populates Livewire's $errors bag
        // automatically — rendered under the relevant field, form stays
        // open, nothing is submitted.
        $this->buildValidator()->validate();

        $service = app(StationService::class);

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'name' => $this->form['name'],
            'type' => $this->type,
            'is_active' => $this->is_active,
            'code' => $this->form['code'],
            'description' => $this->form['description'],
        ];

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            // Station was deleted by someone else between opening the
            // edit form and submitting it.
            $this->formErrorMessage = 'Station tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (StationService::validate())
            // caught something the client-side buildValidator() missed
            // (defense in depth, e.g. a race with another admin's
            // create/update). Remapped from the service's plain field
            // keys onto this form's binding keys — business_unit_id/
            // type/is_active stay unprefixed (bound directly), name/
            // code/description are remapped onto their `form.<field>`
            // key — so the error surfaces under the right input instead
            // of being silently dropped.
            foreach ($e->errors() as $field => $messages) {
                $key = in_array($field, ['business_unit_id', 'type', 'is_active'], true)
                    ? $field
                    : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->type = '';
        $this->is_active = true;
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
     * exists → 404 if not → count related MachineryGroup + Machinery
     * rows → 409 STATION_HAS_MACHINERY if either count is non-zero (row
     * stays in the list, error surfaced inline) → else delete (row
     * disappears from the list on next render()).
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(StationService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (StationHasMachineryException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Station tidak ditemukan, mungkin sudah dihapus.';
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

    /**
     * Type-select options — Indonesian-friendly labels for the 4
     * StationType enum cases, fed to the view's `<select>`.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function typeOptions(): array
    {
        return [
            ['value' => StationType::Weighbridge->value, 'label' => 'Weighbridge'],
            ['value' => StationType::Grading->value, 'label' => 'Grading'],
            ['value' => StationType::CagesTrack->value, 'label' => 'Cages Track'],
            ['value' => StationType::Other->value, 'label' => 'Other'],
        ];
    }

    public function render()
    {
        $service = app(StationService::class);

        $result = $service->listStations(
            $this->page,
            $this->perPage,
            $this->filterBusinessUnitId !== '' ? $this->filterBusinessUnitId : null
        );

        return view('livewire.master-data.kelola-station', [
            'stations' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => $service->businessUnitOptions(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
