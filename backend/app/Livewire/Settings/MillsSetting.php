<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Concerns\ValidatesUploadOnSelect;
use App\Models\BusinessUnit;
use App\Rules\RealImage;
use App\Services\MillSettingService;
use App\Services\StationService;
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
    use ValidatesUploadOnSelect;
    use WithFileUploads;

    public bool $isAdmin = false;

    public string $selectedBusinessUnitId = '';

    public string $app_name = '';

    /**
     * Write-through saving for the mobile app (2026-09-14). When on, a
     * successful save on a mobile device also pushes that record to the
     * server immediately instead of waiting for the operator to tap
     * "Sinkronisasi". The device keeps using its local database either
     * way — this does not turn offline support off.
     */
    public bool $immediate_sync_enabled = false;

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
     * Pilihan icon per station, di-bind ke x-searchable-select per baris
     * (`stationIcons.<id>`) — menggantikan <select wire:change> biasa
     * (temuan audit #15). Perubahan langsung disimpan lewat
     * updatedStationIcons() → setStationIcon(), sama seperti dulu.
     *
     * @var array<string, string>
     */
    public array $stationIcons = [];

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
            $this->syncStationIcons();
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
        $this->immediate_sync_enabled = (bool) ($millSetting['immediate_sync_enabled'] ?? false);
        $this->existingLogoUrl = $millSetting['logo'];
        $this->existingHomePageImageUrl = $millSetting['home_page_image'];
        $this->logo = null;
        $this->home_page_image = null;
    }

    protected function resetForm(): void
    {
        $this->app_name = '';
        $this->immediate_sync_enabled = false;
        $this->existingLogoUrl = null;
        $this->existingHomePageImageUrl = null;
        $this->logo = null;
        $this->home_page_image = null;
        $this->stations = [];
        $this->stationIcons = [];
    }

    /**
     * Aturan form — cermin MillSettingService::update(). app_name WAJIB
     * (business spec screen-034: "nama aplikasi tidak pernah kosong");
     * dulu nullable, sehingga mengosongkannya tetap berbuah "berhasil
     * disimpan" padahal nilai lama diam-diam dipertahankan.
     */
    protected function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048', new RealImage('Logo')],
            'home_page_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048', new RealImage('Gambar halaman utama')],
            'immediate_sync_enabled' => ['boolean'],
        ];
    }

    /**
     * Seluruh pesan berbahasa Indonesia (dulu sebagian jatuh ke pesan
     * bawaan Laravel: "The app name field must not be greater than 255
     * characters.").
     */
    protected function messages(): array
    {
        return [
            'app_name.required' => 'Nama aplikasi wajib diisi.',
            'app_name.string' => 'Nama aplikasi harus berupa teks.',
            'app_name.max' => 'Nama aplikasi maksimal 255 karakter.',
            'logo.file' => 'Logo harus berupa file gambar.',
            'logo.mimes' => 'Logo harus berformat JPG atau PNG.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'home_page_image.file' => 'Gambar halaman utama harus berupa file gambar.',
            'home_page_image.mimes' => 'Gambar halaman utama harus berformat JPG atau PNG.',
            'home_page_image.max' => 'Ukuran gambar halaman utama maksimal 2MB.',
            'immediate_sync_enabled.boolean' => 'Pilihan sinkronisasi langsung tidak valid.',
        ];
    }

    /**
     * File dicek SAAT DIPILIH (bukan baru saat Simpan): file yang bukan
     * gambar sungguhan langsung ditolak dan dibuang dari form, jadi tidak
     * ada pratinjau rusak dan tidak ada yang bisa tersimpan.
     */
    public function updatedLogo(): void
    {
        $this->validateUploadNow('logo');
    }

    public function updatedHomePageImage(): void
    {
        $this->validateUploadNow('home_page_image');
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

        $this->validate();

        $service = app(MillSettingService::class);

        try {
            $service->update(
                auth()->user(),
                $this->selectedBusinessUnitId,
                [
                    'app_name' => $this->app_name,
                    'immediate_sync_enabled' => $this->immediate_sync_enabled,
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
        $this->syncStationIcons();
        $this->successMessage = 'Icon station berhasil disimpan.';
    }

    protected function syncStationIcons(): void
    {
        $this->stationIcons = collect($this->stations)
            ->mapWithKeys(fn (array $station) => [$station['id'] => (string) ($station['icon'] ?? '')])
            ->all();
    }

    /**
     * Hook pemilih icon per baris: kunci = id station.
     */
    public function updatedStationIcons(mixed $value, string $stationId): void
    {
        $this->setStationIcon($stationId, (string) $value);

        if ($this->formErrorMessage !== null) {
            // Gagal → kembalikan pilihan ke nilai tersimpan.
            $this->syncStationIcons();
        }
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
            'typeLabels' => StationService::typeLabels(),
        ]);
    }
}
