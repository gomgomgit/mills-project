<?php

namespace App\Livewire\MasterData;

use App\Exceptions\ProductionLineHasStationsException;
use App\Models\ProductionLine;
use App\Services\ProductionLineService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * KelolaProductionLine — screen-036--kelola-production-line /
 * usecase-036--kelola-production-line (Livewire web "Kelola Production
 * Line", route name `master-data.production-lines`,
 * /master-data/production-lines).
 *
 * Reuses ProductionLineService — the exact same service the API controller
 * (App\Http\Controllers\Api\ProductionLineController) uses — so validation
 * and business rules stay identical between the web and API entry points.
 * Mirrors App\Livewire\MasterData\KelolaBusinessUnit's structure closely
 * (a `business_unit_id` property kept OUT of `$form`, plus an optional
 * `filterBusinessUnitId` filter on the list) — this screen sits one level
 * below Business Unit, exactly as Business Unit sits one level below
 * Company. Unlike KelolaBusinessUnit, there is no file upload field here.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/production-lines with 'auth' + 'role:admin' —
 * EnsureRole::forbidden() aborts(403) (Laravel's default HTML error page,
 * since this is not a JSON request) before this component ever mounts for
 * a non-admin session — same reasoning as every other master-data screen's
 * implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * every other master-data screen — no JS confirm()/modal dialog, fully
 * driven by wire:click calls and directly testable via Livewire component
 * tests.
 *
 * create() auto-provisions the 15 canonical stations for the new
 * Production Line (see ProductionLineService::DEFAULT_STATIONS) — this
 * component does not need to know about that, it just calls
 * ProductionLineService::create() the same as the API controller does.
 */
#[Layout('master-data.production-lines')]
class KelolaProductionLine extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    public string $filterBusinessUnitId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $business_unit_id = '';

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
     * Client-side mirror of ProductionLineService::validate() (defense in
     * depth — same rule set, same DB hits via Rule::exists/Rule::unique,
     * so this never disagrees with the service).
     */
    protected function buildValidator(): \Illuminate\Validation\Validator
    {
        $codeUniqueRule = Rule::unique('production_lines', 'code');

        if ($this->editingId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($this->editingId);
        }

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'form' => [
                'name' => $this->form['name'] !== '' ? $this->form['name'] : null,
                'code' => $this->form['code'] !== '' ? $this->form['code'] : null,
                'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
            ],
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['nullable', 'string', 'max:255', $codeUniqueRule],
            'form.description' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'form.name.required' => 'Nama Production Line wajib diisi.',
            'form.name.max' => 'Nama Production Line maksimal 255 karakter.',
            'form.code.max' => 'Kode Production Line maksimal 255 karakter.',
            'form.code.unique' => 'Kode Production Line sudah digunakan.',
            'form.description.max' => 'Deskripsi maksimal 255 karakter.',
        ];

        return Validator::make($payload, $rules, $messages);
    }

    /**
     * "Tambah Production Line" button — opens the form empty (create
     * mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->business_unit_id = '';
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the production
     * line's current field values (edit mode).
     */
    public function openEditForm(string $id): void
    {
        $productionLine = ProductionLine::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $productionLine->id;
        $this->business_unit_id = $productionLine->business_unit_id;

        $this->form = [
            'name' => (string) ($productionLine->name ?? ''),
            'code' => (string) ($productionLine->code ?? ''),
            'description' => (string) ($productionLine->description ?? ''),
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
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

        // create: business_unit_id exists, name required, code
        // unique-if-filled globally, description nullable. update: same,
        // code unique excluding self. A failure here populates Livewire's
        // $errors bag automatically — rendered under the relevant field,
        // form stays open, nothing is submitted.
        $this->buildValidator()->validate();

        $service = app(ProductionLineService::class);

        $payload = [
            'business_unit_id' => $this->business_unit_id,
            'name' => $this->form['name'],
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
            // The production line was deleted by someone else between
            // opening the form and submitting it.
            $this->formErrorMessage = 'Production Line tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (ProductionLineService::validate())
            // caught something the client-side buildValidator() missed
            // (defense in depth, e.g. a race with another admin's
            // create/update). Remapped from the service's plain field keys
            // onto this form's binding keys — business_unit_id stays
            // unprefixed (bound directly), every other field is remapped
            // onto its `form.<field>` key.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'business_unit_id' ? $field : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->business_unit_id = '';
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
     * exists → 404 if not → count related Station rows (WHERE
     * production_line_id=id) → 409 PRODUCTION_LINE_HAS_STATIONS if
     * non-zero (row stays in the list, error surfaced inline) → else
     * delete (row disappears from the list on next render()).
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(ProductionLineService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (ProductionLineHasStationsException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Production Line tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(ProductionLineService::class);

        $result = $service->listProductionLines(
            $this->page,
            $this->perPage,
            $this->filterBusinessUnitId !== '' ? $this->filterBusinessUnitId : null
        );

        return view('livewire.master-data.kelola-production-line', [
            'productionLines' => $result['data'],
            'meta' => $result['meta'],
            'businessUnitOptions' => $service->businessUnitOptions(),
        ]);
    }
}
