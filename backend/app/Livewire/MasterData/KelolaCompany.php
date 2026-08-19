<?php

namespace App\Livewire\MasterData;

use App\Exceptions\CompanyHasBusinessUnitsException;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * KelolaCompany — screen-028--kelola-company / usecase-028--kelola-company
 * (Livewire web "Kelola Company", route name `master-data.companies`,
 * /master-data/companies).
 *
 * Reuses CompanyService — the exact same service the API controller
 * (App\Http\Controllers\Api\CompanyController) uses — so validation and
 * business rules stay identical between the web and API entry points.
 * Mirrors App\Livewire\MasterData\KelolaCorporate's structure exactly
 * (entity-catalog v4 rework, screen-028--kelola-company 3-tech-spec ver
 * 2), with two additions on top of that pattern: a `corporate_id` select
 * field on the form (this screen is one level below Corporate, UNCHANGED
 * from before this rework) and an optional `filterCorporateId` filter on
 * the list (also UNCHANGED).
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/companies with 'auth' + 'role:admin' — EnsureRole::forbidden()
 * aborts(403) (Laravel's default HTML error page, since this is not a JSON
 * request) before this component ever mounts for a non-admin session —
 * same reasoning as KelolaCorporate's implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId), same as
 * KelolaCorporate — no JS confirm()/modal dialog, fully driven by
 * wire:click calls and directly testable via Livewire component tests.
 *
 * Entity-catalog v4 rework: the create/edit form grew from
 * { corporate_id, name } to the full company field set — grouped into
 * Identity / Contact / Legal & Employment sections in the view (see
 * FIELDS below), plus a `logo` file upload via WithFileUploads with a
 * live preview (new-upload preview via Livewire's own temporaryUrl(),
 * existing logo preview via CompanyService::LOGO_DISK when editing).
 * `$form` is a single keyed array (bound in the view via
 * `wire:model="form.<field>"`) rather than one public property per field,
 * mirroring KelolaCorporate — `corporate_id` is deliberately kept OUT of
 * `$form` (bound directly as `wire:model="corporate_id"`) since it needs
 * its own Rule::exists validation and its own dedicated select control,
 * exactly as it was before this rework.
 */
#[Layout('master-data.companies')]
class KelolaCompany extends Component
{
    use WithFileUploads;

    /**
     * Every company field (besides corporate_id, name, and logo — see
     * class docblock) the create/edit form binds via `form.<field>` —
     * mirrors CompanyService::TEXT_FIELDS minus company_code/name is not
     * applicable (both are included here) — kept in sync manually.
     */
    protected const FIELDS = [
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

    protected const REQUIRED_FIELDS = ['company_code', 'name'];

    public int $page = 1;

    public int $perPage = 20;

    public string $filterCorporateId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $corporate_id = '';

    public string $last_update = '';

    /** @var array<string, string> */
    public array $form = [];

    /**
     * Newly selected (not yet saved) logo upload — a Livewire
     * TemporaryUploadedFile, previewed via ->temporaryUrl() in the view.
     * Null means "no new file chosen"; on update() a null $logo leaves
     * the company's existing stored logo untouched.
     */
    public $logo = null;

    /**
     * URL of the company's already-saved logo, populated by
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
     * Filter-by-corporate dropdown above the table — resets to page 1 so
     * the pagination stays consistent with the newly filtered result set.
     */
    public function updatedFilterCorporateId(): void
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
     * Client-side mirror of CompanyService::validate() (defense in depth
     * — same rule set, same DB hits via Rule::exists/Rule::unique, so
     * this never disagrees with the service). The `name` unique rule's
     * `where('corporate_id', ...)` closure is the same per-corporate
     * scope used server-side — CRITICAL divergence from
     * KelolaCorporate's global name uniqueness: here `company_code` is
     * the globally-unique field and `name` stays per-corporate, two
     * independent rules (see CompanyService::validate() docblock).
     * `ignore($this->editingId)` on edit implements "unique excluding
     * self" (business_logic step 4) for both.
     */
    protected function rules(): array
    {
        $codeUniqueRule = Rule::unique('companies', 'company_code');
        $nameUniqueRule = Rule::unique('companies', 'name')
            ->where(fn ($query) => $query->where('corporate_id', $this->corporate_id));

        if ($this->editingId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($this->editingId);
            $nameUniqueRule = $nameUniqueRule->ignore($this->editingId);
        }

        $rules = [
            'corporate_id' => ['required', 'string', Rule::exists('corporates', 'id')],
            'form.company_code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'form.name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'last_update' => ['nullable', 'date'],
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
            'corporate_id.required' => 'Corporate wajib dipilih.',
            'corporate_id.exists' => 'Corporate yang dipilih tidak ditemukan.',
            'form.company_code.required' => 'Kode company wajib diisi.',
            'form.company_code.max' => 'Kode company maksimal 255 karakter.',
            'form.company_code.unique' => 'Kode company sudah digunakan.',
            'form.name.required' => 'Nama company wajib diisi.',
            'form.name.max' => 'Nama company maksimal 255 karakter.',
            'form.name.unique' => 'Nama company sudah digunakan pada Corporate ini.',
            'last_update.date' => 'Tanggal pembaruan terakhir tidak valid.',
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
     * "Tambah Company" button — opens the form empty (create mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->corporate_id = '';
        $this->last_update = '';
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the company's
     * current field values (edit mode), plus its existing logo (if any)
     * as the initial preview.
     */
    public function openEditForm(string $id): void
    {
        $company = Company::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $company->id;
        $this->corporate_id = $company->corporate_id;
        $this->last_update = optional($company->last_update)->toDateString() ?? '';

        $form = [];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($company->{$field} ?? '');
        }
        $this->form = $form;

        $this->logo = null;
        $this->existingLogoUrl = $company->logo
            ? Storage::disk(CompanyService::LOGO_DISK)->url($company->logo)
            : null;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->corporate_id = '';
        $this->last_update = '';
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

        // Step 3 (create) / step 4 (update): corporate_id exists,
        // company_code required+unique globally, name required+unique
        // within corporate_id (excluding self on update), logo type/size
        // if present. A failure here populates Livewire's $errors bag
        // automatically — rendered under the relevant field, form stays
        // open, nothing is submitted.
        $this->validate();

        $service = app(CompanyService::class);

        /** @var TemporaryUploadedFile|null $logo */
        $logo = $this->logo;

        $payload = array_merge($this->form, [
            'corporate_id' => $this->corporate_id,
            'last_update' => $this->last_update !== '' ? $this->last_update : null,
        ]);

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload, $logo);
            } else {
                $service->create($payload, $logo);
            }
        } catch (ModelNotFoundException) {
            // Company was deleted by someone else between opening the
            // edit form and submitting it.
            $this->formErrorMessage = 'Company tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (CompanyService::validate())
            // caught something the client-side rules() missed (defense in
            // depth, e.g. a race with another admin's create/update).
            // Remapped from the service's plain field keys onto this
            // form's binding keys — corporate_id/last_update/logo stay
            // unprefixed (bound directly), every other field is remapped
            // onto its `form.<field>` key — so the error surfaces under
            // the right input instead of being silently dropped.
            foreach ($e->errors() as $field => $messages) {
                $key = in_array($field, ['corporate_id', 'last_update', 'logo'], true)
                    ? $field
                    : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->corporate_id = '';
        $this->last_update = '';
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->resetValidation();
    }

    /**
     * "Hapus" row action — arms the inline confirmation for this row
     * (business_logic step 5 is only actually invoked by confirmDelete()
     * below).
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
     * Confirming the delete — business_logic step 5: validate id exists →
     * 404 if not → count related BusinessUnit rows → 409
     * COMPANY_HAS_BUSINESS_UNITS if any exist (row stays in the list,
     * error surfaced inline) → else delete (row disappears from the list
     * on next render()).
     *
     * UNCHANGED by the entity-catalog v4 rework — do not modify.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(CompanyService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (CompanyHasBusinessUnitsException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Company tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(CompanyService::class);

        $result = $service->listCompanies(
            $this->page,
            $this->perPage,
            $this->filterCorporateId !== '' ? $this->filterCorporateId : null
        );

        return view('livewire.master-data.kelola-company', [
            'companies' => $result['data'],
            'meta' => $result['meta'],
            'corporateOptions' => $service->corporateOptions(),
        ]);
    }
}
