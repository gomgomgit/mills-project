<?php

namespace App\Livewire\MasterData;

use App\Exceptions\CorporateHasCompaniesException;
use App\Models\Corporate;
use App\Services\CorporateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * KelolaCorporate — screen-027--kelola-corporate / usecase-027--kelola-corporate
 * (Livewire web "Kelola Corporate", route name `master-data.corporates`,
 * /master-data/corporates).
 *
 * Reuses CorporateService — the exact same service the API controller
 * (App\Http\Controllers\Api\CorporateController) uses — so validation and
 * business rules (name/corporate_code uniqueness, the delete-guard) stay
 * identical between the web and API entry points (mirrors the
 * WeighbridgeRecordService / ChangePasswordForm pattern).
 *
 * Access control: route-level only. routes/web.php guards
 * /master-data/corporates with 'auth' + 'role:admin' — EnsureRole::forbidden()
 * aborts(403) (Laravel's default HTML error page, since this is not a JSON
 * request) before this component ever mounts for a non-admin session. This
 * satisfies "a non-admin actor must see an access-denied state — no list or
 * controls rendered at all": a non-admin request never reaches render() (or
 * any other part of this component) in the first place — see
 * implementation_notes.
 *
 * Delete UX: inline per-row confirmation (confirmingDeleteId) rather than a
 * JS confirm()/modal dialog — keeps the whole flow driven by plain
 * wire:click calls with no extra JS, and is directly testable via Livewire
 * component tests (mirrors this codebase's existing preference for
 * server-driven state over client-side JS, e.g. ChangePasswordForm's toast
 * dismissal via Alpine only for the timed auto-hide, not the core flow).
 *
 * Entity-catalog v4 rework (3-tech-spec ver 2): the create/edit form grew
 * from a single `name` input to the full corporate field set — grouped
 * into Identity / Contact / Legal & Employment sections in the view (see
 * FIELDS below), plus a `logo` file upload via WithFileUploads with a live
 * preview (new-upload preview via Livewire's own temporaryUrl(), existing
 * logo preview via CorporateService::LOGO_DISK when editing). `$form` is a
 * single keyed array (bound in the view via `wire:model="form.<field>"`)
 * rather than one public property per field — keeps this component's
 * property list from growing to 18 near-identical string properties, and
 * lets validate()/openEditForm()/save() iterate FIELDS generically instead
 * of repeating each field name by hand in five different places.
 */
#[Layout('master-data.corporates')]
class KelolaCorporate extends Component
{
    use WithFileUploads;

    /**
     * Every corporate field the create/edit form binds via `form.<field>`
     * — mirrors CorporateService::TEXT_FIELDS exactly (kept in sync
     * manually; both lists changing together is expected whenever this
     * screen's field set changes again).
     */
    protected const FIELDS = [
        'corporate_code',
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

    protected const REQUIRED_FIELDS = ['corporate_code', 'name'];

    public int $page = 1;

    public int $perPage = 20;

    public bool $showForm = false;

    public ?string $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    /**
     * Newly selected (not yet saved) logo upload — a Livewire
     * TemporaryUploadedFile, previewed via ->temporaryUrl() in the view.
     * Null means "no new file chosen"; on update() a null $logo leaves
     * the corporate's existing stored logo untouched.
     */
    public $logo = null;

    /**
     * URL of the corporate's already-saved logo, populated by
     * openEditForm() — shown as the preview until/unless a new $logo is
     * chosen. Always null in create mode (nothing saved yet).
     */
    public ?string $existingLogoUrl = null;

    /**
     * Extensions Livewire can safely call ->temporaryUrl() on for this
     * field, mirroring the `mimes:jpg,jpeg,png` rule above. Guards the
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
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return array_fill_keys(self::FIELDS, '');
    }

    /**
     * Client-side mirror of CorporateService::validate() (defense in
     * depth — same rule set, same DB hit via Rule::unique, so this never
     * disagrees with the service). `ignore($this->editingId)` on edit
     * implements "unique excluding self" (business_logic step 3).
     */
    protected function rules(): array
    {
        $codeUniqueRule = Rule::unique('corporates', 'corporate_code');
        $nameUniqueRule = Rule::unique('corporates', 'name');

        if ($this->editingId !== null) {
            $codeUniqueRule = $codeUniqueRule->ignore($this->editingId);
            $nameUniqueRule = $nameUniqueRule->ignore($this->editingId);
        }

        $rules = [
            'form.corporate_code' => ['required', 'string', 'max:255', $codeUniqueRule],
            'form.name' => ['required', 'string', 'max:255', $nameUniqueRule],
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
            'form.corporate_code.required' => 'Kode corporate wajib diisi.',
            'form.corporate_code.max' => 'Kode corporate maksimal 255 karakter.',
            'form.corporate_code.unique' => 'Kode corporate sudah digunakan.',
            'form.name.required' => 'Nama corporate wajib diisi.',
            'form.name.max' => 'Nama corporate maksimal 255 karakter.',
            'form.name.unique' => 'Nama corporate sudah digunakan.',
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
     * "Tambah Corporate" button — opens the form empty (create mode).
     */
    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->formErrorMessage = null;
        $this->showForm = true;
    }

    /**
     * "Edit" row action — opens the form pre-filled with the corporate's
     * current field values (edit mode), plus its existing logo (if any)
     * as the initial preview.
     */
    public function openEditForm(string $id): void
    {
        $corporate = Corporate::findOrFail($id);

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->editingId = $corporate->id;

        $form = [];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($corporate->{$field} ?? '');
        }
        $this->form = $form;

        $this->logo = null;
        $this->existingLogoUrl = $corporate->logo
            ? Storage::disk(CorporateService::LOGO_DISK)->url($corporate->logo)
            : null;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
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

        // Step 2 (create) / step 3 (update): corporate_code/name
        // required+unique (excluding self on update), logo type/size if
        // present. A failure here populates Livewire's $errors bag
        // automatically — rendered under the relevant field, form stays
        // open, nothing is submitted (matches the "must NOT submit/
        // create/update" requirement).
        $this->validate();

        $service = app(CorporateService::class);

        /** @var TemporaryUploadedFile|null $logo */
        $logo = $this->logo;

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $this->form, $logo);
            } else {
                $service->create($this->form, $logo);
            }
        } catch (ModelNotFoundException) {
            // Corporate was deleted by someone else between opening the
            // edit form and submitting it.
            $this->formErrorMessage = 'Corporate tidak ditemukan, mungkin sudah dihapus.';

            return;
        } catch (ValidationException $e) {
            // Server-side re-validation (CorporateService::validate())
            // caught something the client-side rules() missed (defense in
            // depth, e.g. a race with another admin's create/update).
            // Remapped from the service's plain field keys (e.g.
            // 'corporate_code') onto this form's `form.<field>` binding
            // keys (except `logo`, which is already unprefixed on both
            // sides) so the error surfaces under the right input instead
            // of being silently dropped.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'logo' ? 'logo' : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->logo = null;
        $this->existingLogoUrl = null;
        $this->resetValidation();
    }

    /**
     * "Hapus" row action — arms the inline confirmation for this row
     * (business_logic step 4 is only actually invoked by confirmDelete()
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
     * Confirming the delete — business_logic step 4: validate id exists →
     * 404 if not → count related Company rows → 409 CORPORATE_HAS_COMPANIES
     * if any exist (row stays in the list, error surfaced inline) → else
     * delete (row disappears from the list on next render()).
     *
     * UNCHANGED by the entity-catalog v4 rework — do not modify.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $service = app(CorporateService::class);

        try {
            $service->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = null;
        } catch (CorporateHasCompaniesException $e) {
            // Delete-guard: nothing was deleted, row must remain in the
            // list — drop back to the un-confirming state and surface the
            // guard message inline instead.
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->deleteErrorMessage = 'Corporate tidak ditemukan, mungkin sudah dihapus.';
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
        $service = app(CorporateService::class);

        $result = $service->listCorporates($this->page, $this->perPage);

        return view('livewire.master-data.kelola-corporate', [
            'corporates' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}
