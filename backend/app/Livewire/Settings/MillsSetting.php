<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Services\MillSettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * MillsSetting — screen-034--mills-setting / usecase-034--mills-setting
 * (Livewire web "Mills Setting", route name `mill-settings`,
 * /mill-settings).
 *
 * Reuses MillSettingService — the exact same service the API controller
 * (App\Http\Controllers\Api\MillSettingController) uses — so validation,
 * ownership-scoping, and business rules stay identical between the web
 * and API entry points.
 *
 * Unlike every other screen in App\Livewire\MasterData (a paginated
 * list + create/edit form), this screen edits exactly ONE MillSetting
 * row at a time (the selected mill's), plus a flat list of that mill's
 * stations for icon assignment — no pagination, no delete, no "Tambah"
 * action.
 *
 * Access control: route-level role gate ('auth' + 'role:admin,
 * mill_management' in routes/web.php) PLUS per-resource ownership
 * scoping enforced inside MillSettingService::checkAccess() on every
 * service call — Mill Management can only ever act on their own
 * business_unit_id. For a Mill Management user, $selectedBusinessUnitId
 * is fixed to their own business unit and the picker is hidden entirely
 * (mount() sets it and never lets it change); an Admin sees the picker
 * and must choose a mill before the form renders.
 */
#[Layout('settings.mill-settings')]
class MillsSetting extends Component
{
    use WithFileUploads;

    public bool $isAdmin = false;

    public string $selectedBusinessUnitId = '';

    public string $app_name = '';

    /**
     * Newly selected (not yet saved) uploads — Livewire
     * TemporaryUploadedFile, previewed via ->temporaryUrl(). Null means
     * "no new file chosen"; on save() a null value leaves the existing
     * stored file untouched.
     */
    public $logo = null;

    public $home_page_image = null;

    public ?string $existingLogoUrl = null;

    public ?string $existingHomePageImageUrl = null;

    /**
     * @var list<array{id: string, name: string, type: string, icon: ?string}>
     */
    public array $stations = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    /**
     * Extensions Livewire can safely call ->temporaryUrl() on — mirrors
     * the `mimes:jpg,jpeg,png` rule enforced server-side by
     * MillSettingService::update(). Guards the view's preview the same
     * way KelolaBusinessUnit's PREVIEWABLE_LOGO_EXTENSIONS does.
     *
     * @var list<string>
     */
    private const PREVIEWABLE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function mount(): void
    {
        $user = auth()->user();
        $this->isAdmin = $user->role === UserRole::Admin;

        if (! $this->isAdmin) {
            $this->selectedBusinessUnitId = (string) $user->business_unit_id;
            $this->loadData();
        }
    }

    /**
     * Admin's mill picker — reloads the form/station list for the newly
     * selected business unit. Mirrors KelolaStation's
     * updatedFilterBusinessUnitId() pattern (a wire:model.live-bound
     * property triggers an updated<Prop>() hook).
     */
    public function updatedSelectedBusinessUnitId(): void
    {
        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;

        if ($this->selectedBusinessUnitId === '') {
            $this->resetForm();

            return;
        }

        $this->loadData();
    }

    /**
     * Loads the selected mill's MillSetting (get-or-create-default, per
     * business_logic step 3) and its stations list into the component's
     * public properties.
     */
    protected function loadData(): void
    {
        $service = app(MillSettingService::class);

        try {
            $millSetting = $service->getOrCreate(auth()->user(), $this->selectedBusinessUnitId);
            $this->stations = $service->listStations(auth()->user(), $this->selectedBusinessUnitId);
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Mill tidak ditemukan.';
            $this->resetForm();

            return;
        } catch (AuthorizationException $e) {
            $this->formErrorMessage = $e->getMessage();
            $this->resetForm();

            return;
        }

        $this->app_name = $millSetting['app_name'];
        $this->existingLogoUrl = $millSetting['logo'];
        $this->existingHomePageImageUrl = $millSetting['home_page_image'];
        $this->logo = null;
        $this->home_page_image = null;
    }

    protected function resetForm(): void
    {
        $this->app_name = '';
        $this->existingLogoUrl = null;
        $this->existingHomePageImageUrl = null;
        $this->logo = null;
        $this->home_page_image = null;
        $this->stations = [];
    }

    /**
     * "Simpan" — business_logic step 4: validate → get-or-create → store
     * uploaded files → update. Client-side rules mirror
     * MillSettingService::update()'s Validator rules (defense in depth);
     * the service call is the source of truth.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;

        $this->validate([
            'app_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'home_page_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'home_page_image.mimes' => 'Gambar halaman utama harus berformat JPG atau PNG.',
            'home_page_image.max' => 'Ukuran gambar halaman utama maksimal 2MB.',
        ]);

        $service = app(MillSettingService::class);

        try {
            $service->update(
                auth()->user(),
                $this->selectedBusinessUnitId,
                [
                    'app_name' => $this->app_name,
                ],
                $this->logo,
                $this->home_page_image,
            );
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Mill tidak ditemukan.';

            return;
        } catch (AuthorizationException $e) {
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = 'Mills Setting berhasil disimpan.';
        $this->loadData();
    }

    /**
     * Per-station icon picker — fires immediately on selection (no
     * separate "Simpan" for this sub-section), mirrors
     * business_logic step 6. An empty string from the "Default" option
     * is normalized to null (reset to the type-default icon).
     */
    public function setStationIcon(string $stationId, string $icon): void
    {
        $this->formErrorMessage = null;
        $service = app(MillSettingService::class);

        try {
            $service->setStationIcon(
                auth()->user(),
                $this->selectedBusinessUnitId,
                $stationId,
                $icon !== '' ? $icon : null,
            );
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Station tidak ditemukan.';

            return;
        } catch (AuthorizationException $e) {
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            $this->formErrorMessage = $e->errors()['icon'][0] ?? 'Icon tidak valid.';

            return;
        }

        $this->stations = $service->listStations(auth()->user(), $this->selectedBusinessUnitId);
        $this->successMessage = 'Icon station berhasil disimpan.';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function iconOptions(): array
    {
        return array_map(
            fn (string $icon) => ['value' => $icon, 'label' => ucfirst($icon)],
            MillSettingService::SUPPORTED_ICONS,
        );
    }

    public function render()
    {
        $businessUnitOptions = $this->isAdmin
            ? BusinessUnit::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (BusinessUnit $bu) => ['id' => $bu->id, 'name' => $bu->name])
                ->all()
            : [];

        return view('livewire.settings.mills-setting', [
            'businessUnitOptions' => $businessUnitOptions,
            'iconOptions' => $this->iconOptions(),
            'previewableExtensions' => self::PREVIEWABLE_EXTENSIONS,
        ]);
    }
}
