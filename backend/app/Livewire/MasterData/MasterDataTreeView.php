<?php

namespace App\Livewire\MasterData;

use App\Exceptions\BusinessUnitHasStationsException;
use App\Exceptions\CompanyHasBusinessUnitsException;
use App\Exceptions\CorporateHasCompaniesException;
use App\Exceptions\ProductionLineHasStationsException;
use App\Livewire\Concerns\HasFilterReset;
use App\Livewire\Concerns\ValidatesUploadOnSelect;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Rules\RealImage;
use App\Rules\UniqueCaseInsensitive;
use App\Services\BusinessUnitService;
use App\Services\CompanyService;
use App\Services\CorporateService;
use App\Services\MasterDataTreeService;
use App\Services\ProductionLineService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * MasterDataTreeView — screen-127--master-data-tree-view /
 * usecase-127, -157, -158, -159 (Livewire web "Struktur Mills", route name
 * `master-data.tree-view`, /master-data/tree-view).
 *
 * REVAMPED 2026-10-06. Was a read-only tree; is now a mill-centric card
 * board with FULL CRUD over the four hierarchy levels (Corporate ->
 * Company -> Business Unit -> Production Line). The route name, the path,
 * this class name and the Blade file names are all deliberately unchanged
 * — they are dep-graph keys and possibly bookmarked addresses, and
 * renaming them for cosmetic alignment would mean touching RouteAccess,
 * the existing tests, and users' bookmarks. Only the human-facing name
 * changed ("Struktur Mills"): sidebar label, layout title, page <h1>.
 *
 * SCOPE IS UNCHANGED: Station, Machinery Group and Machinery are still
 * outside this screen entirely and still managed on their own "Kelola"
 * screens. The page states that boundary explicitly and links there,
 * because an unexplained absence reads as missing data rather than as a
 * deliberate limit.
 *
 * DATA IS LOADED IN render(), NOT mount() — THE OPPOSITE OF THE PREVIOUS
 * VERSION, DELIBERATELY. The old component loaded the whole tree once in
 * mount() with a written reason: the screen was read-only and unpaginated,
 * so refetching on every expand/collapse would have been waste. That
 * reason died with this revamp — the screen now WRITES, and anything
 * loaded once in mount() is stale the instant the first action lands.
 * Loading in render() is also what makes "counts are recomputed after
 * every action" true with zero manual adjustment code, including the
 * awkward case of moving an entity, which changes the child count of two
 * different parents at once. Do not move it back to mount().
 *
 * VALIDATION IS TWO-LAYER, ON PURPOSE. rules() mirrors the service rules
 * so a message can appear under the field that caused it; the SERVICE
 * remains the authority and validates again. A ValidationException thrown
 * by a service is remapped onto this form's `form.<field>` binding keys
 * (except `logo`, unprefixed on both sides) — the same pattern as
 * KelolaCorporate::save(). Where the two layers ever disagree the service
 * is right and the difference is a defect, not a second rule.
 *
 * DELETE RULES ARE NOT REIMPLEMENTED HERE. The four services own both the
 * dependency checks and the refusal messages; this component calls
 * delete() and, on refusal, shows $e->getMessage() VERBATIM inside a
 * confirmation modal that STAYS OPEN. The service messages already name
 * the blockers and their counts ("masih memiliki 3 User, 2 Production
 * Line, 36 Station"); rewording them here would risk dropping exactly the
 * numbers that save the Admin from deleting things one by one to find out
 * what is in the way.
 *
 * ONE MODAL SERVES FOUR LEVELS. $modalLevel decides which fields render
 * and which service is called. Four near-identical modals would be four
 * places to change every time a field is added.
 *
 * PAGINATION IS USED WHEN NEEDED, and only then. The pattern is the one
 * the four "Kelola" screens already use — $page/$perPage (20),
 * nextPage()/previousPage(), HasFilterReset — NOT Livewire's
 * WithPagination trait, so this page does not become a fifth way of doing
 * the same thing. Controls render only once a list exceeds $perPage, so at
 * today's volume the page looks exactly as originally designed, with no
 * page controls at all. Production Lines are never paginated: they live
 * inside their mill's card, and truncating them would make the card lie
 * about the mill's contents.
 *
 * Access control: route-level only, identical to every other master-data
 * screen — routes/web.php guards /master-data/tree-view with 'auth' +
 * 'role:admin'; EnsureRole::forbidden() aborts(403) before this component
 * ever mounts for a non-admin session. There is deliberately no second
 * role check inside the component: two checks that could disagree are
 * worse than one that is clear.
 */
#[Layout('master-data.tree-view')]
class MasterDataTreeView extends Component
{
    use HasFilterReset;
    use ValidatesUploadOnSelect;
    use WithFileUploads;

    /** The four levels this screen manages, in hierarchy order. */
    public const LEVELS = ['corporate', 'company', 'business-unit', 'production-line'];

    /** Human labels — used in messages, headings and aria-labels. */
    public const LABELS = [
        'corporate' => 'Corporate',
        'company' => 'Company',
        'business-unit' => 'Business Unit',
        'production-line' => 'Production Line',
    ];

    /**
     * The form field holding each level's parent id. Corporate is the
     * root and has none.
     */
    public const PARENT_FIELD = [
        'corporate' => null,
        'company' => 'corporate_id',
        'business-unit' => 'company_id',
        'production-line' => 'business_unit_id',
    ];

    /**
     * The shared optional text fields Corporate / Company / Business Unit
     * all carry — identical in all three services' TEXT_FIELDS, listed
     * once here instead of three times.
     */
    private const SHARED_TEXT_FIELDS = [
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

    /** Required fields per level (business rule 6). */
    private const REQUIRED_FIELDS = [
        'corporate' => ['corporate_code', 'name'],
        'company' => ['corporate_id', 'company_code', 'name'],
        'business-unit' => ['company_id', 'code', 'name'],
        'production-line' => ['business_unit_id', 'name'],
    ];

    /** Levels whose service accepts an optional logo upload. */
    private const LOGO_LEVELS = ['corporate', 'company', 'business-unit'];

    /** Mill card board page (perPage 20, same as the Kelola screens). */
    public int $page = 1;

    public int $perPage = 20;

    /** The two summary lists paginate independently of the board. */
    public int $corporatePage = 1;

    public int $companyPage = 1;

    /**
     * One filter box narrowing the card board AND both summary lists at
     * once, matching name and code at all four levels. #[Url] so a
     * filtered view can be linked/bookmarked, and so the parameter
     * disappears again once the box is cleared.
     */
    #[Url]
    public string $search = '';

    /** Which level the open modal is editing: one of self::LEVELS. */
    public ?string $modalLevel = null;

    /** 'create' | 'edit' | null (no modal open). */
    public ?string $modalMode = null;

    public ?string $editingId = null;

    /** @var array<string, mixed> bound in the view via `form.<field>` */
    public array $form = [];

    /**
     * Newly selected (not yet saved) logo upload. Null on edit means
     * "leave the logo alone" — NOT "delete the logo". None of the four
     * services has a remove-logo path, so this screen must not promise
     * one either.
     */
    public $logo = null;

    /**
     * Name of the logo file currently stored on the entity being edited,
     * shown as a caption. Deliberately NOT loaded into the file input —
     * that is technically impossible and semantically wrong.
     */
    public ?string $existingLogoName = null;

    public ?string $formErrorMessage = null;

    /**
     * ['level' => ..., 'id' => ..., 'name' => ...] while a delete
     * confirmation is open; empty otherwise. The NAME is captured at
     * askDelete() time rather than looked up again at render time, so the
     * confirmation keeps naming the right entity even if the row changes
     * underneath between two renders.
     *
     * @var array<string, string>
     */
    public array $confirmingDelete = [];

    public ?string $deleteErrorMessage = null;

    public ?string $successMessage = null;

    /**
     * Only $search is restored here (via #[Url]). NOTHING is loaded —
     * see the class docblock.
     */
    public function mount(): void
    {
        $this->form = [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterDefaults(): array
    {
        return ['search' => ''];
    }

    /**
     * The fields one level's form binds, in render order.
     *
     * @return list<string>
     */
    public static function fieldsFor(string $level): array
    {
        return match ($level) {
            'corporate' => array_merge(['corporate_code', 'name'], self::SHARED_TEXT_FIELDS),
            'company' => array_merge(['corporate_id', 'company_code', 'name'], self::SHARED_TEXT_FIELDS),
            'business-unit' => array_merge(
                ['company_id', 'code', 'name', 'business_unit_type_code'],
                self::SHARED_TEXT_FIELDS
            ),
            'production-line' => ['business_unit_id', 'name', 'code', 'description'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(string $level): array
    {
        return array_fill_keys(self::fieldsFor($level), '');
    }

    public function hasLogo(?string $level = null): bool
    {
        return in_array($level ?? $this->modalLevel, self::LOGO_LEVELS, true);
    }

    /**
     * Mirror of the service validation for the level currently open — so
     * a message lands under the field that caused it. The service remains
     * the authority (see class docblock). Unique rules use
     * ignore($editingId) on edit so an entity never collides with itself.
     */
    protected function rules(): array
    {
        return match ($this->modalLevel) {
            'corporate' => $this->corporateRules(),
            'company' => $this->companyRules(),
            'business-unit' => $this->businessUnitRules(),
            'production-line' => $this->productionLineRules(),
            default => [],
        };
    }

    /** Mirrors CorporateService::validate(). */
    private function corporateRules(): array
    {
        $codeUnique = UniqueCaseInsensitive::on('corporates', 'corporate_code');
        $nameUnique = UniqueCaseInsensitive::on('corporates', 'name');

        if ($this->editingId !== null) {
            $codeUnique = $codeUnique->ignore($this->editingId);
            $nameUnique = $nameUnique->ignore($this->editingId);
        }

        $rules = [
            'form.corporate_code' => ['required', 'string', 'max:255', $codeUnique],
            'form.name' => ['required', 'string', 'max:255', $nameUnique],
            'logo' => $this->logoRule(),
        ];

        foreach (self::SHARED_TEXT_FIELDS as $field) {
            $rules["form.$field"] = ['nullable', 'string', 'max:255'];
        }

        $rules['form.email'][] = 'email';
        $rules['form.website'][] = 'regex:'.CorporateService::WEBSITE_PATTERN;

        return $rules;
    }

    /**
     * Mirrors CompanyService::validate() — note `name` is unique WITHIN
     * the chosen corporate, not globally, and that Company deliberately
     * has no email/website format rule (the service has none either).
     */
    private function companyRules(): array
    {
        // '' is coerced to null before it can reach the query: `corporate_id`
        // is a uuid column, and binding the empty string into a comparison
        // against it is tolerated by SQLite (the test suite) but rejected by
        // PostgreSQL (production) with SQLSTATE[22P02] — a 500, not a
        // validation error. The rule below DOES run with no corporate picked:
        // Laravel only halts the remaining rules on the attribute that failed
        // ('form.corporate_id'), never the rule on 'form.name'.
        $corporateId = trim((string) ($this->form['corporate_id'] ?? '')) ?: null;

        $codeUnique = UniqueCaseInsensitive::on('companies', 'company_code');
        // `name` is unique WITHIN the chosen corporate. Until one IS chosen
        // there is no scope to be unique within, so the check must be able to
        // find nothing — a global name check would wrongly report 'sudah
        // digunakan' for a name that only exists under a DIFFERENT corporate,
        // on top of the 'Corporate wajib dipilih.' the user actually needs.
        $nameUnique = UniqueCaseInsensitive::on('companies', 'name')
            ->where(fn ($query) => $corporateId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('corporate_id', $corporateId));

        if ($this->editingId !== null) {
            $codeUnique = $codeUnique->ignore($this->editingId);
            $nameUnique = $nameUnique->ignore($this->editingId);
        }

        $rules = [
            'form.corporate_id' => ['required', 'string', Rule::exists('corporates', 'id')],
            'form.company_code' => ['required', 'string', 'max:255', $codeUnique],
            'form.name' => ['required', 'string', 'max:255', $nameUnique],
            'logo' => $this->logoRule(),
        ];

        foreach (self::SHARED_TEXT_FIELDS as $field) {
            $rules["form.$field"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Mirrors BusinessUnitService::validate() — `code` unique globally,
     * `name` with NO uniqueness rule at all (the service's own
     * deliberate divergence from Corporate and Company; mirroring it
     * loosely or strictly would both be wrong).
     */
    private function businessUnitRules(): array
    {
        $codeUnique = UniqueCaseInsensitive::on('business_units', 'code');

        if ($this->editingId !== null) {
            $codeUnique = $codeUnique->ignore($this->editingId);
        }

        $rules = [
            'form.company_id' => ['required', 'string', Rule::exists('companies', 'id')],
            'form.code' => ['required', 'string', 'max:255', $codeUnique],
            'form.name' => ['required', 'string', 'max:255'],
            'logo' => $this->logoRule(),
        ];

        foreach (array_merge(['business_unit_type_code'], self::SHARED_TEXT_FIELDS) as $field) {
            $rules["form.$field"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Mirrors ProductionLineService::validate() — `code` is OPTIONAL but
     * still unique when filled; empty and "already taken" are two
     * different things. No logo at this level.
     */
    private function productionLineRules(): array
    {
        $codeUnique = UniqueCaseInsensitive::on('production_lines', 'code');

        if ($this->editingId !== null) {
            $codeUnique = $codeUnique->ignore($this->editingId);
        }

        return [
            'form.business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['nullable', 'string', 'max:255', $codeUnique],
            'form.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The one logo rule all three logo-bearing levels share — identical
     * to the services' own rule, RealImage included (an extension alone
     * does not make a file an image).
     */
    private function logoRule(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048', new RealImage('Logo')];
    }

    /**
     * Indonesian messages, mirroring each service's own message array so
     * the same failure reads the same whichever layer caught it.
     */
    protected function messages(): array
    {
        $shared = [
            'logo.file' => 'Logo harus berupa file gambar.',
            'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
        ];

        return match ($this->modalLevel) {
            'corporate' => $shared + [
                'form.corporate_code.required' => 'Kode corporate wajib diisi.',
                'form.corporate_code.max' => 'Kode corporate maksimal 255 karakter.',
                'form.corporate_code.unique' => 'Kode corporate sudah digunakan.',
                'form.name.required' => 'Nama corporate wajib diisi.',
                'form.name.max' => 'Nama corporate maksimal 255 karakter.',
                'form.name.unique' => 'Nama corporate sudah digunakan.',
                'form.email.email' => 'Format email tidak valid.',
                'form.website.regex' => 'Format website tidak valid (contoh: www.contoh.co.id).',
            ],
            'company' => $shared + [
                'form.corporate_id.required' => 'Corporate wajib dipilih.',
                'form.corporate_id.exists' => 'Corporate yang dipilih tidak ditemukan.',
                'form.company_code.required' => 'Kode company wajib diisi.',
                'form.company_code.max' => 'Kode company maksimal 255 karakter.',
                'form.company_code.unique' => 'Kode company sudah digunakan.',
                'form.name.required' => 'Nama company wajib diisi.',
                'form.name.max' => 'Nama company maksimal 255 karakter.',
                'form.name.unique' => 'Nama company sudah digunakan pada Corporate ini.',
            ],
            'business-unit' => $shared + [
                'form.company_id.required' => 'Company wajib dipilih.',
                'form.company_id.exists' => 'Company yang dipilih tidak ditemukan.',
                'form.code.required' => 'Kode business unit wajib diisi.',
                'form.code.max' => 'Kode business unit maksimal 255 karakter.',
                'form.code.unique' => 'Kode business unit sudah digunakan.',
                'form.name.required' => 'Nama business unit wajib diisi.',
                'form.name.max' => 'Nama business unit maksimal 255 karakter.',
            ],
            'production-line' => [
                'form.business_unit_id.required' => 'Business Unit wajib dipilih.',
                'form.business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
                'form.name.required' => 'Nama Production Line wajib diisi.',
                'form.name.max' => 'Nama Production Line maksimal 255 karakter.',
                'form.code.max' => 'Kode Production Line maksimal 255 karakter.',
                'form.code.unique' => 'Kode Production Line sudah digunakan.',
                'form.description.max' => 'Deskripsi maksimal 255 karakter.',
            ],
            default => $shared,
        };
    }

    /**
     * The logo is checked the moment it is chosen, not only on Simpan —
     * see App\Livewire\Concerns\ValidatesUploadOnSelect. Skipped for
     * Production Line, which has no logo rule to validate against.
     */
    public function updatedLogo(): void
    {
        if (! $this->hasLogo()) {
            return;
        }

        $this->validateUploadNow('logo');
    }

    /**
     * Changing the filter resets ALL THREE page positions to 1. Page 3 of
     * the old result almost never exists in the new one, and an
     * unexplained empty page reads as "no results".
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->corporatePage = 1;
        $this->companyPage = 1;
    }

    protected function afterFilterReset(): void
    {
        $this->corporatePage = 1;
        $this->companyPage = 1;
    }

    /**
     * Opens an empty form for $level. When invoked from inside a parent's
     * card, $parentId pre-fills the parent field — but the select stays
     * visible and changeable (business rule 8): locking it would make the
     * only way to fix a wrong card "cancel and start over from the right
     * one".
     *
     * Refuses to open at all when the level needs a parent and there is
     * no candidate parent yet, naming the level that has to be created
     * first. A modal holding an empty parent select with no explanation
     * is a dead end.
     */
    public function openCreate(string $level, ?string $parentId = null): void
    {
        if (! in_array($level, self::LEVELS, true)) {
            return;
        }

        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->resetValidation();

        $parentField = self::PARENT_FIELD[$level];

        if ($parentField !== null && $this->parentOptionsFor($level) === []) {
            $this->formErrorMessage = 'Belum ada '.$this->parentLabel($level).'. Tambah '
                .$this->parentLabel($level).' terlebih dahulu sebelum menambah '
                .self::LABELS[$level].'.';

            return;
        }

        $this->modalLevel = $level;
        $this->modalMode = 'create';
        $this->editingId = null;
        $this->form = $this->emptyForm($level);
        $this->logo = null;
        $this->existingLogoName = null;
        $this->formErrorMessage = null;

        if ($parentId !== null && $parentField !== null) {
            $this->form[$parentField] = $parentId;
        }
    }

    /**
     * Opens the form for an existing entity, pre-filled with EVERY field
     * of that level including its parent id — which is why moving an
     * entity to another parent needs no separate action: it is the same
     * form.
     *
     * An entity already deleted elsewhere does NOT open a modal; a plain
     * sentence is shown and the next render() drops its row.
     */
    public function openEdit(string $level, string $id): void
    {
        if (! in_array($level, self::LEVELS, true)) {
            return;
        }

        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->resetValidation();
        $this->formErrorMessage = null;

        try {
            $model = $this->findOrFail($level, $id);
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = self::LABELS[$level]
                .' tidak ditemukan, mungkin sudah dihapus oleh Admin lain.';

            return;
        }

        $form = [];
        foreach (self::fieldsFor($level) as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }

        $this->form = $form;
        $this->logo = null;
        $this->existingLogoName = $this->hasLogo($level) && $model->logo
            ? basename((string) $model->logo)
            : null;
        $this->editingId = $model->getKey();
        $this->modalLevel = $level;
        $this->modalMode = 'edit';
    }

    public function closeModal(): void
    {
        $this->modalLevel = null;
        $this->modalMode = null;
        $this->editingId = null;
        $this->form = [];
        $this->logo = null;
        $this->existingLogoName = null;
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * Create or update, per $modalMode. The parent id is read from $form
     * at this point — never from openCreate()'s argument — which is
     * exactly why the parent select must not be locked.
     */
    public function save(): void
    {
        if ($this->modalLevel === null) {
            return;
        }

        $this->successMessage = null;
        $this->formErrorMessage = null;

        $level = $this->modalLevel;
        $isEdit = $this->modalMode === 'edit' && $this->editingId !== null;

        // Layer one: the mirror. A failure here fills Livewire's error
        // bag, the modal stays open, and the service is never called.
        $this->validate();

        /** @var TemporaryUploadedFile|null $logo */
        $logo = $this->logo;

        try {
            // Layer two: the authority.
            match ($level) {
                'corporate' => $isEdit
                    ? app(CorporateService::class)->update($this->editingId, $this->form, $logo)
                    : app(CorporateService::class)->create($this->form, $logo),
                'company' => $isEdit
                    ? app(CompanyService::class)->update($this->editingId, $this->form, $logo)
                    : app(CompanyService::class)->create($this->form, $logo),
                'business-unit' => $isEdit
                    ? app(BusinessUnitService::class)->update($this->editingId, $this->form, $logo)
                    : app(BusinessUnitService::class)->create($this->form, $logo),
                'production-line' => $isEdit
                    ? app(ProductionLineService::class)->update($this->editingId, $this->form)
                    : app(ProductionLineService::class)->create($this->form),
            };
        } catch (ModelNotFoundException) {
            // Either the entity being edited or the parent that was
            // picked is gone — deleted by another Admin between opening
            // this form and submitting it. Plain sentence, modal stays
            // open with everything still filled in.
            $this->formErrorMessage = $isEdit
                ? self::LABELS[$level].' tidak ditemukan, mungkin sudah dihapus oleh Admin lain.'
                : $this->parentLabel($level).' yang dipilih tidak ditemukan, mungkin sudah dihapus oleh Admin lain.';

            return;
        } catch (ValidationException $e) {
            // Remap the service's plain field keys onto this form's
            // binding keys so each message surfaces under its own input
            // instead of vanishing. `logo` is unprefixed on both sides.
            foreach ($e->errors() as $field => $messages) {
                $key = $field === 'logo' ? 'logo' : "form.$field";
                $this->addError($key, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = $isEdit
            ? self::LABELS[$level].' berhasil diperbarui.'
            : self::LABELS[$level].' berhasil ditambahkan.';
        $this->deleteErrorMessage = null;

        // No manual reload: the next render() calls board() and counts()
        // again, so the new row and every number already reflect the
        // state after this action.
        $this->closeModal();
    }

    /**
     * Arms the delete confirmation. The entity's NAME is captured here on
     * purpose — a confirmation that cannot name what it is about to
     * delete does not satisfy its own rule.
     */
    public function askDelete(string $level, string $id): void
    {
        if (! in_array($level, self::LEVELS, true)) {
            return;
        }

        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->formErrorMessage = null;

        $model = $this->findModel($level, $id);

        if ($model === null) {
            $this->deleteErrorMessage = self::LABELS[$level]
                .' tidak ditemukan, mungkin sudah dihapus oleh Admin lain.';

            return;
        }

        $this->confirmingDelete = [
            'level' => $level,
            'id' => (string) $model->getKey(),
            'name' => (string) ($model->name ?? ''),
        ];
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = [];
        $this->deleteErrorMessage = null;
    }

    /**
     * Performs the delete through the service that owns the rules.
     *
     * The dependency checks are NOT repeated here — all four services
     * already do them, and a copy would eventually give a second,
     * different answer. On refusal the confirmation modal STAYS OPEN and
     * shows the service's own message verbatim, counts and all: closing
     * the modal for a red toast would throw away the only useful
     * information it carries.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDelete === []) {
            return;
        }

        $level = $this->confirmingDelete['level'] ?? null;
        $id = $this->confirmingDelete['id'] ?? null;

        if (! in_array($level, self::LEVELS, true) || $id === null) {
            return;
        }

        try {
            match ($level) {
                'corporate' => app(CorporateService::class)->delete($id),
                'company' => app(CompanyService::class)->delete($id),
                'business-unit' => app(BusinessUnitService::class)->delete($id),
                'production-line' => app(ProductionLineService::class)->delete($id),
            };
        } catch (CorporateHasCompaniesException|CompanyHasBusinessUnitsException|BusinessUnitHasStationsException|ProductionLineHasStationsException $e) {
            // Verbatim. Not rephrased, not summarised, not replaced.
            $this->deleteErrorMessage = $e->getMessage();

            return;
        } catch (ModelNotFoundException) {
            $this->confirmingDelete = [];
            $this->deleteErrorMessage = self::LABELS[$level]
                .' tidak ditemukan, mungkin sudah dihapus oleh Admin lain.';

            return;
        }

        $this->confirmingDelete = [];
        $this->deleteErrorMessage = null;
        $this->successMessage = self::LABELS[$level].' berhasil dihapus.';
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

    public function nextCorporatePage(): void
    {
        $this->corporatePage++;
    }

    public function previousCorporatePage(): void
    {
        if ($this->corporatePage > 1) {
            $this->corporatePage--;
        }
    }

    public function nextCompanyPage(): void
    {
        $this->companyPage++;
    }

    public function previousCompanyPage(): void
    {
        if ($this->companyPage > 1) {
            $this->companyPage--;
        }
    }

    /** Clears the filter box from the "no match" block. */
    public function clearSearch(): void
    {
        $this->resetFilters();
    }

    public function render()
    {
        $service = app(MasterDataTreeService::class);

        $search = trim($this->search);
        $term = $search === '' ? null : $search;

        // Totals come from four independent COUNTs and are NEVER derived
        // from the filtered/paginated rows — business rule 13.
        $counts = $service->counts();

        $board = $service->board($term, $this->page, $this->perPage);
        if ($this->clampPage('page', $board['meta'])) {
            $board = $service->board($term, $this->page, $this->perPage);
        }

        $corporates = $service->corporateRows($term, $this->corporatePage, $this->perPage);
        if ($this->clampPage('corporatePage', $corporates['meta'])) {
            $corporates = $service->corporateRows($term, $this->corporatePage, $this->perPage);
        }

        $companies = $service->companyRows($term, $this->companyPage, $this->perPage);
        if ($this->clampPage('companyPage', $companies['meta'])) {
            $companies = $service->companyRows($term, $this->companyPage, $this->perPage);
        }

        return view('livewire.master-data.master-data-tree-view', [
            'counts' => $counts,
            'millCards' => $board['data'],
            'millMeta' => $board['meta'],
            'corporateRows' => $corporates['data'],
            'corporateMeta' => $corporates['meta'],
            'companyRows' => $companies['data'],
            'companyMeta' => $companies['meta'],
            'hasSearch' => $search !== '',
            'searchTerm' => $search,
            'formGroups' => $this->modalLevel !== null ? $this->formGroups($this->modalLevel) : [],
            'parentOptions' => $this->modalLevel !== null ? $this->parentOptionsFor($this->modalLevel) : [],
            'parentField' => $this->modalLevel !== null ? self::PARENT_FIELD[$this->modalLevel] : null,
            'parentLabel' => $this->modalLevel !== null ? $this->parentLabel($this->modalLevel) : null,
            'levelLabel' => $this->modalLevel !== null ? self::LABELS[$this->modalLevel] : null,
            'levelLabels' => self::LABELS,
        ]);
    }

    /**
     * Walks a page position back into range — what makes "deleting the
     * last row on the last page steps back one page" true without any
     * delete-specific code, and what keeps a stale ?page= from rendering
     * a blank list. Returns true when the position moved, so render()
     * refetches with the corrected page.
     *
     * @param  array<string, int>  $meta
     */
    private function clampPage(string $property, array $meta): bool
    {
        if ($this->{$property} < 1) {
            $this->{$property} = 1;

            return true;
        }

        $totalPages = (int) ($meta['total_pages'] ?? 1);

        if ((int) ($meta['total'] ?? 0) > 0 && $this->{$property} > $totalPages) {
            $this->{$property} = max($totalPages, 1);

            return true;
        }

        return false;
    }

    /**
     * Candidate parents for a level, straight from the service that
     * already owns that list (no second query shape to keep in sync).
     * Corporate is the root and has none.
     *
     * @return list<array{id: string, name: string}>
     */
    private function parentOptionsFor(string $level): array
    {
        return match ($level) {
            'company' => app(CompanyService::class)->corporateOptions(),
            'business-unit' => app(BusinessUnitService::class)->companyOptions(),
            'production-line' => app(ProductionLineService::class)->businessUnitOptions(),
            default => [],
        };
    }

    private function parentLabel(string $level): string
    {
        return match ($level) {
            'company' => 'Corporate',
            'business-unit' => 'Company',
            'production-line' => 'Business Unit',
            default => 'Induk',
        };
    }

    /**
     * Fields of the open level, grouped Identitas / Kontak / Alamat.
     * Corporate and Company carry ~18 text fields each; one flat column
     * of eighteen inputs is not a form anyone can read.
     *
     * @return list<array{title: string, fields: list<array<string, mixed>>}>
     */
    private function formGroups(string $level): array
    {
        $groups = [
            'Identitas' => [],
            'Kontak' => [],
            'Alamat' => [],
        ];

        $contact = ['telephone_no', 'fax_no', 'contact_no', 'extension_no', 'email', 'website'];
        $address = ['address', 'map'];
        $parentField = self::PARENT_FIELD[$level];
        $required = self::REQUIRED_FIELDS[$level];

        foreach (self::fieldsFor($level) as $field) {
            if ($field === $parentField) {
                // The parent select is rendered separately at the top of
                // the Identitas group by the view.
                continue;
            }

            $group = in_array($field, $contact, true)
                ? 'Kontak'
                : (in_array($field, $address, true) ? 'Alamat' : 'Identitas');

            $groups[$group][] = [
                'name' => $field,
                'label' => $this->fieldLabel($field, $level),
                'type' => $this->fieldType($field),
                'required' => in_array($field, $required, true),
                'wide' => in_array($field, ['address', 'map', 'description'], true),
            ];
        }

        $result = [];
        foreach ($groups as $title => $fields) {
            if ($fields === [] && $title !== 'Identitas') {
                continue;
            }

            $result[] = ['title' => $title, 'fields' => $fields];
        }

        return $result;
    }

    private function fieldType(string $field): string
    {
        return match ($field) {
            'address', 'map', 'description' => 'textarea',
            'email' => 'email',
            default => 'text',
        };
    }

    private function fieldLabel(string $field, string $level): string
    {
        $levelLabel = self::LABELS[$level] ?? '';

        return match ($field) {
            'corporate_code', 'company_code', 'code' => 'Kode '.$levelLabel,
            'name' => 'Nama '.$levelLabel,
            'short_name' => 'Nama Singkat',
            'leader_name' => 'Nama Pimpinan',
            'lawyer_name' => 'Nama Kuasa Hukum',
            'business_unit_type_code' => 'Kode Tipe Business Unit',
            'description' => 'Deskripsi',
            'address' => 'Alamat',
            'map' => 'Peta / Koordinat',
            'telephone_no' => 'Telepon',
            'fax_no' => 'Faks',
            'contact_no' => 'Nomor Kontak',
            'extension_no' => 'Ekstensi',
            'email' => 'Email',
            'website' => 'Website',
            'tax_register_no' => 'NPWP / Tax Register',
            'insurance_no' => 'Nomor Asuransi',
            'epf_employer' => 'EPF Employer',
            'socso_employer' => 'SOCSO Employer',
            'labor_union' => 'Serikat Pekerja',
            default => $field,
        };
    }

    /**
     * @throws ModelNotFoundException
     */
    private function findOrFail(string $level, string $id): Model
    {
        // Same uuid trap as companyRules(): an empty id must never reach the
        // query. PostgreSQL rejects `where id = ''` outright, so what should
        // read as 'tidak ditemukan' would arrive as a 500 instead.
        if (trim($id) === '') {
            throw new ModelNotFoundException;
        }

        return match ($level) {
            'corporate' => Corporate::findOrFail($id),
            'company' => Company::findOrFail($id),
            'business-unit' => BusinessUnit::findOrFail($id),
            'production-line' => ProductionLine::findOrFail($id),
        };
    }

    private function findModel(string $level, string $id): ?Model
    {
        if (trim($id) === '') {
            return null;
        }

        return match ($level) {
            'corporate' => Corporate::find($id),
            'company' => Company::find($id),
            'business-unit' => BusinessUnit::find($id),
            'production-line' => ProductionLine::find($id),
            default => null,
        };
    }
}
