<?php

namespace App\Livewire\MasterData;

use App\Exceptions\BusinessUnitHasStationsException;
use App\Models\BusinessUnit;
use App\Services\BusinessUnitService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * KelolaBusinessUnit — screen-029--kelola-business-unit /
 * usecase-029--kelola-business-unit (Livewire web "Kelola Business Unit",
 * route name `master-data.business-units`, /master-data/business-units).
 *
 * Reuses BusinessUnitService — the exact same service the API controller
 * (App\Http\Controllers\Api\BusinessUnitController) uses — so validation
 * and business rules stay identical between the web and API entry
 * points. Mirrors App\Livewire\MasterData\KelolaCorporate's structure
 * exactly (WithFileUploads, logo preview + FileNotPreviewableException
 * guard, inline delete confirmation), MERGED with App\Livewire\
 * MasterData\KelolaCompany's parent-select dropdown pattern (a
 * `company_id` property, kept OUT of `$form`, plus an optional
 * `filterCompanyId` filter on the list) — this screen sits one level
 * below Company, exactly as Company sits one level below Corporate.
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/business-units with 'auth' + 'role:admin' —
 * EnsureRole::forbidden() aborts(403) (Laravel's default HTML error page,
 * since this is not a JSON request) before this component ever mounts
 * for a non-admin session — same reasoning as KelolaCorporate/
 * KelolaCompany's implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * KelolaCorporate/KelolaCompany — no JS confirm()/modal dialog, fully
 * driven by wire:click calls and directly testable via Livewire
 * component tests.
 *
 * CRITICAL divergence from BOTH KelolaCorporate and KelolaCompany: `code`
 * is unique GLOBALLY (like Corporate's corporate_code / Company's
 * company_code), but `name` has NO uniqueness rule at all — neither
 * global (Corporate) nor scoped to the parent (Company's per-corporate_id
 * rule). See rules() below.
 */
#[Layout('master-data.business-units')]
class KelolaBusinessUnit extends Component
{
    use WithFileUploads;

    /**
     * Every business unit field (besides company_id, and logo — see class
     * docblock) the create/edit form binds via `form.<field>` — mirrors
     * BusinessUnitService::TEXT_FIELDS exactly (kept in sync manually).
     */
    protected const FIELDS = [
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

    protected const REQUIRED_FIELDS = ['code', 'name'];

    public int $page = 1;

    public int $perPage = 20;

    public string $filterCompanyId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $company_id = '';

    /** @var array<string, string> */
    public array $form = [];

    /**
     * Newly selected (not yet saved) logo upload — a Livewire
     * TemporaryUploadedFile, previewed via ->temporaryUrl() in the view.
     * Null means "no new file chosen"; on update() a null $logo leaves
     * the business unit's existing stored logo untouched.
     */
    public $logo = null;

    /**
     * URL of the business unit's already-saved logo, populated by
     * openEditForm() — shown as the preview until/unless a new $logo is
     * chosen. Always null in create mode (nothing saved yet).
     */
    public ?string $existingLogoUrl = null;

    /**
     * Extensions Livewire can safely call ->temporaryUrl() on for this
     * field, mirroring the `mimes:jpg,jpeg,png` rule below. Guards the
     * view's preview so a disallowed upload (e.g. .pdf) — still sitting
     * in $logo during the re-render that follows a failed validate() —
     * never crashes with FileNotPreviewableException before the
     * validation error can be displayed.
     *
     * @var list<string>
     */
    private const PREVIEWABLE_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public ?string $formErrorMessage = null;

    public ?string $confirmingDeleteId = null;

    public ?string $deleteErrorMessage = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    /**
     * Filter-by-company dropdown above the table — resets to page 1 so
     * the pagination stays consistent with the newly filtered result set.
     */
    public function updatedFilterCompanyId(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return array_fill_keys(self::FIELDS, '');
    }

    /**
     * Client-side mirror of BusinessUnitService::validate() (defense in
     * depth — same rule set, same DB hits via Rule::exists/Rule::unique,
     * so this never disagrees with the service). `code`'s unique rule is
     * global (no scoping closure, unlike KelolaCompany's per-corporate_id
     * `name` rule) — `ignore($this->editingId)` on edit implements
     * "unique excluding self". `name` carries NO Rule::unique at all —
     * the critical divergence from both KelolaCorporate and
     * KelolaCompany.
     */
    protected function rules(): array
    {
        $codeUniqueRule = Rule::unique('business_units', 'code');

        if ($this->editingId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($this->editingId);
        }

        $rules = [
            'company_id' => ['required', 'string', Rule::exists('companies', 'id')],
            'form.code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'form.name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        foreach (self::FIELDS as $field) {
            if (in_array($field, self::REQUIRED_FIELDS, true)) {
                continue;
            }

            $rules["form.$field"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'company_id.required' => 'Company wajib dipilih.',
            'company_id.exists' => 'Company yang dipilih tidak ditemukan.',
            'form.code.required' => 'Kode business unit wajib diisi.',
            'form.code.max' => 'Kode business unit maksimal 255 karakter.',
            'form.code.unique' => 'Kode business unit sudah digunakan.',
            'form.name.required' => 'Nama business unit wajib diisi.',
            'form.name.max' => 'Nama business unit maksimal 255 karakter.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
        ];
    }

    /**
     * Computed property (`$this->logoIsPreviewable` in the view) — true
     * only when $logo is a temporary upload whose extension is one
     * Livewire is configured to preview. Checked before the view calls
     * ->temporaryUrl(), so a disallowed file (e.g. .pdf) just falls back
     * to "no preview" instead of throwing FileNotPreviewableException
     * mid-render — letting save()'s validation error reach the user.
     */
    public function getLogoIsPreviewableProperty(): bool
    {
        if (! $this->logo) {
            return false;
        }

        $extension = strtolower((string) $this->logo->getClientOriginalExtension());

        return in_array($extension, self::PREVIEWABLE_LOGO_EXTENSIONS, true);
    }

    /**
     * "Tambah Business Unit" button — opens the form empty (create mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->company_id = '';
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the business
     * unit's current field values (edit mode), plus its existing logo
     * (if any) as the initial preview.
     */
    public function openEditForm(string $id): void
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $businessUnit->id;
        $this->company_id = $businessUnit->company_id;

        $form = [];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($businessUnit->{$field} ?? '');
        }
        $this->form = $form;

        $this->logo = null;
        $this->existingLogoUrl = $businessUnit->logo
            ? Storage::disk(BusinessUnitService::LOGO_DISK)->url($businessUnit->logo)
            : null;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->company_id = '';
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create or update, per whether $editingId is set.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;

        // create: company_id exists, code required+unique globally, name
        // required (no uniqueness rule), logo type/size if present.
        // update: same, code unique excluding self. A failure here
        // populates Livewire's $errors bag automatically — rendered
        // under the relevant field, form stays open, nothing is
        // submitted.
        $this->validate();

        $service = app(BusinessUnitService::class);

        /** @var TemporaryUploadedFile|null $logo */
        $logo = $this->logo;

        $payload = array_merge($this->form, [
            'company_id' => $this->company_id,
        ]);

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload, $logo);
            } else {
                $service->create($payload, $logo);
            }
        } catch (ModelNotFoundException) {
            // Business Unit was deleted by someone else between opening
            // the edit form and submitting it.
            $this->formErrorMessage = 'Business Unit tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (BusinessUnitService::validate())
            // caught something the client-side rules() missed (defense in
            // depth, e.g. a race with another admin's create/update).
            // Remapped from the service's plain field keys onto this
            // form's binding keys — company_id/logo stay unprefixed
            // (bound directly), every other field is remapped onto its
            // `form.<field>` key — so the error surfaces under the right
            // input instead of being silently dropped.
            foreach ($e->errors() as $field => $messages) {
                $key = in_array($field, ['company_id', 'logo'], true)
                    ? $field
                    : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->company_id = '';
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
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
     * exists → 404 if not → count related Station rows → 409
     * BUSINESS_UNIT_HAS_STATIONS if any exist (row stays in the list,
     * error surfaced inline) → else delete (row disappears from the list
     * on next render()).
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(BusinessUnitService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (BusinessUnitHasStationsException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Business Unit tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(BusinessUnitService::class);

        $result = $service->listBusinessUnits(
            $this->page,
            $this->perPage,
            $this->filterCompanyId !== '' ? $this->filterCompanyId : null
        );

        return view('livewire.master-data.kelola-business-unit', [
            'businessUnits' => $result['data'],
            'meta' => $result['meta'],
            'companyOptions' => $service->companyOptions(),
        ]);
    }
}
