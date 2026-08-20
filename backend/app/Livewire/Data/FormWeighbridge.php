<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Services\StationService;
use App\Services\WeighbridgeRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormWeighbridge — screen-022--form-weighbridge-web / "Form Weighbridge
 * (Web)" (Livewire web page, routes `data.weighbridge.create` /data/weighbridge/create
 * and `data.weighbridge.edit` /data/weighbridge/{id}/edit — same component
 * class handles both modes, mode determined by whether $id was bound).
 *
 * Reuses WeighbridgeRecordService::create()/update() — the exact same
 * service methods the API controller (App\Http\Controllers\Api\
 * WeighbridgeRecordController::store()/update()) calls, mirroring the
 * DetailWeighbridge / KelolaCorporate pattern.
 *
 * Two deliberate divergences from Form Weighbridge mobile (screen-010),
 * per explicit product direction:
 *  - record_datetime defaults to now() but stays a genuinely editable
 *    `datetime-local` input (mobile locks it after auto-set-once).
 *  - Saved records are fully editable afterwards (mode edit) — there is
 *    no draft/pause/lock concept on web; Simpan always results in
 *    status=saved.
 *
 * Net Weight is NOT bound as an editable input despite uiux-spec's
 * 'web-form-input' convention (no disabled fields) — see
 * WeighbridgeRecordService::create()'s docblock for why this field is a
 * deliberate exception (the model's `saving` event always recomputes it
 * from gross/tare, so an editable field would silently discard the
 * user's own edit on save).
 */
#[Layout('data.weighbridge-form')]
class FormWeighbridge extends Component
{
    protected const FIELDS = [
        'production_line_id',
        'wb_card_number',
        'weighbridge_type',
        'record_datetime',
        'vehicle_number',
        'driver_name',
        'estate_supplier',
        'destination',
        'division',
        'block',
        'gross_weight',
        'tare_weight',
        'quantity',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'business_unit_id' => '',
        'production_line_id' => '',
        'wb_card_number' => '',
        'weighbridge_type' => 'receive',
        'record_datetime' => '',
        'vehicle_number' => '',
        'driver_name' => '',
        'estate_supplier' => '',
        'destination' => '',
        'division' => '',
        'block' => '',
        'gross_weight' => '',
        'tare_weight' => '',
        'quantity' => '',
    ];

    public bool $checked = false;

    public bool $acknowledged = false;

    /** Read-only display for edit mode (business_unit_id is immutable after create). */
    public ?string $businessUnitName = null;

    public ?string $stationName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $businessUnitOptions = [];

    /** @var array<int, array{id: string, name: string}> */
    public array $productionLineOptions = [];

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->businessUnitOptions = BusinessUnit::query()->orderBy('name')->get(['id', 'name'])->toArray();

        if ($id === null) {
            $this->isEdit = false;
            $this->form['record_datetime'] = now()->format('Y-m-d\TH:i');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(WeighbridgeRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        foreach (self::FIELDS as $field) {
            if ($field === 'production_line_id') {
                continue;
            }
            $this->form[$field] = $record[$field] ?? '';
        }

        $this->form['record_datetime'] = $record['record_datetime']
            ? \Illuminate\Support\Carbon::parse($record['record_datetime'])->format('Y-m-d\TH:i')
            : '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->stationName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);
    }

    /** Ganti tipe: buang tanggal & tujuan muatan lama, isi ulang default waktu saat ini (mengikuti pola mobile). */
    public function updatedFormWeighbridgeType(): void
    {
        $this->form['record_datetime'] = now()->format('Y-m-d\TH:i');
        $this->form['destination'] = '';
    }

    /**
     * Ganti Business Unit (create mode only): buang production_line_id
     * lama, muat ulang daftar Production Line untuk Business Unit yang
     * baru dipilih — mirrors KelolaStation::updatedBusinessUnitId()'s
     * cascading pattern exactly, via the same StationService::
     * productionLineOptions() query.
     */
    public function updatedFormBusinessUnitId(): void
    {
        $this->form['production_line_id'] = '';
        $this->loadProductionLineOptions();
    }

    protected function loadProductionLineOptions(): void
    {
        $businessUnitId = $this->form['business_unit_id'];
        $this->productionLineOptions = app(StationService::class)
            ->productionLineOptions($businessUnitId !== '' ? $businessUnitId : null);
    }

    public function save(): void
    {
        $this->errors_ = [];
        $this->generalError = null;

        $data = $this->form;
        $data['checked'] = $this->checked;
        $data['acknowledged'] = $this->acknowledged;

        $service = app(WeighbridgeRecordService::class);

        try {
            if ($this->isEdit) {
                $record = $service->update($this->id, $data, auth()->user());
            } else {
                $record = $service->create($data, auth()->user());
            }
        } catch (ValidationException $e) {
            $this->errors_ = collect($e->errors())->map(fn ($messages) => $messages[0])->all();

            return;
        } catch (HttpException $e) {
            // e.g. NoActiveWeighbridgeStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.weighbridge.detail', ['id' => $record['id']]), navigate: false);
    }

    public function isSupervisor(): bool
    {
        return auth()->user()?->role === UserRole::Supervisor;
    }

    public function isMillManagement(): bool
    {
        return auth()->user()?->role === UserRole::MillManagement;
    }

    public function render()
    {
        return view('livewire.data.form-weighbridge');
    }
}
