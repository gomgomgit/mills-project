<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\PressingOperationalTarget;
use App\Services\PressingRecordService;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormPressing — screen-058--form-pressing-web / "Form Pressing (Web)"
 * (Livewire web page, routes `data.pressing.create`
 * /data/pressing/create and `data.pressing.edit`
 * /data/pressing/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormThreshing
 * (screen-057) for header form shape + dual Checked/Acknowledged
 * self-attestation checkboxes AND (REVISED 2026-08-24, entity-catalog v12)
 * now also for the Pressing Detail grid itself — the user explicitly
 * rejected the original FIXED 24-row design (every canonical time-slot row
 * always present, no add/remove-row UI at all) as wasting screen space.
 * `detailRows` now uses the EXACT same dynamic add-row/remove-row pattern
 * as FormThreshing's Threshing Detail grid: `addDetailRow()`/
 * `removeDetailRow()`/`canAddRow()`/`availableTimeSlotOptions()` (the
 * canonical-order analogue of FormCagesTrack's `availableHourOptions()`,
 * floored by canonical-slot INDEX rather than raw hour, since Pressing's
 * slots wrap starting at 07:00). A brand-new draft starts with ZERO rows
 * (no longer pre-populated); an existing record's rows (with their `id`s)
 * are loaded verbatim in edit mode.
 *
 * Reuses PressingRecordService::create()/update() — the exact same
 * service methods the API controller
 * (App\Http\Controllers\Api\PressingRecordController::store()/update())
 * calls.
 *
 * Two deliberate divergences from Form Pressing mobile (screen-042), per
 * explicit product direction (same divergences as screen-057):
 *  - date defaults to now() but stays a genuinely editable input (mobile
 *    locks it).
 *  - Saved records are fully editable afterwards (mode edit) — there is no
 *    draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * sent as part of the create/update payload — per this screen's tech spec.
 */
#[Layout('data.pressing-form')]
class FormPressing extends Component
{
    use ScopesToActorMill;

    protected const FIELDS = ['production_line_id', 'presser_id', 'date', 'note'];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'presser_id' => '',
        'date' => '',
        'note' => '',
    ];

    /**
     * Dynamic add-row/remove-row grid (REVISED 2026-08-24, entity-catalog
     * v12) — mirrors FormThreshing's `$detailRows` exactly. Starts empty
     * for a brand-new draft; `id` is present for rows loaded from an
     * existing record (UPDATE target) and absent for rows added this
     * session (INSERT target).
     *
     * @var array<int, array{id: ?string, time_slot: ?string, digester_temp_c: mixed, digester_level_percent: mixed, press_motor_current_amps: mixed, cone_hydraulic_pressure_bar: mixed, dilution_water_temp_c: mixed, downtime_reason: mixed}>
     */
    public array $detailRows = [];

    public bool $checked = false;

    public bool $acknowledged = false;

    /** Read-only display for edit mode (production_line_id is immutable after create). */
    public ?string $businessUnitName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $productionLineOptions = [];

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $detailError = null;

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->productionLineOptions = $this->productionLineOptionsForActor(auth()->user());

        if ($id === null) {
            $this->isEdit = false;
            $this->form['date'] = now()->format('Y-m-d');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(PressingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException|ValidationException) {
            // ValidationException ikut ditangkap sejak 2026-09-28: aktor
            // terikat mill yang `users.business_unit_id`-nya kosong membuat
            // getDetail() gagal-tertutup 422 lewat
            // ScopesToActorMill::actorReadMillId(). Bagi aktor seperti itu
            // TIDAK ADA record yang terlihat sama sekali, jadi $notFound
            // memang keadaan yang benar — dan itu lebih baik daripada
            // halaman error 422 penuh. Pesan yang bisa ditindaklanjuti
            // ("Hubungi Admin") tetap sampai lewat Data Browser dan lewat
            // save di layar Form.
            $this->notFound = true;

            return;
        }

        $this->form['presser_id'] = $record['presser_id'] ?? '';
        $this->form['date'] = $record['date'] ?? '';
        $this->form['note'] = $record['note'] ?? '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'digester_temp_c' => $row['digester_temp_c'],
                'digester_level_percent' => $row['digester_level_percent'],
                'press_motor_current_amps' => $row['press_motor_current_amps'],
                'cone_hydraulic_pressure_bar' => $row['cone_hydraulic_pressure_bar'],
                'dilution_water_temp_c' => $row['dilution_water_temp_c'],
                'downtime_reason' => $row['downtime_reason'],
            ])
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = [
            'id' => null,
            'time_slot' => '',
            'digester_temp_c' => '',
            'digester_level_percent' => '',
            'press_motor_current_amps' => '',
            'cone_hydraulic_pressure_bar' => '',
            'dilution_water_temp_c' => '',
            'downtime_reason' => '',
        ];
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    /** "Tambah baris" is disabled once all 24 canonical slots are already used. */
    public function canAddRow(): bool
    {
        return count($this->detailRows) < 24;
    }

    /**
     * availableTimeSlotOptions() — the canonical-order analogue of
     * FormCagesTrack::availableHourOptions(): all 24 canonical slots
     * DIKURANGI (slot yang sudah dipakai baris manapun) DAN (slot yang
     * index-nya <= index slot TERTINGGI di antara baris lain yang sudah
     * terisi), dibandingkan lewat posisi index-nya di
     * PressingRecordService::canonicalTimeSlots() (bukan string
     * comparison — slot wrap mulai dari 07:00). Mirrors
     * FormThreshing::availableTimeSlotOptions() exactly.
     *
     * @return array<int, string>
     */
    public function availableTimeSlotOptions(int $rowIndex): array
    {
        $canonical = PressingRecordService::canonicalTimeSlots();
        $canonicalOrder = array_flip($canonical);

        $usedSlots = collect($this->detailRows)
            ->reject(fn ($row, $i) => $i === $rowIndex)
            ->pluck('time_slot')
            ->filter(fn ($slot) => $slot !== null && $slot !== '');

        $usedIndexes = $usedSlots->map(fn ($slot) => $canonicalOrder[$slot] ?? null)->filter(fn ($i) => $i !== null);
        $maxOtherIndex = $usedIndexes->max();

        return collect($canonical)
            ->reject(fn ($slot) => $usedSlots->contains($slot))
            ->reject(fn ($slot) => $maxOtherIndex !== null && $canonicalOrder[$slot] <= $maxOtherIndex)
            ->values()
            ->all();
    }

    public function save(): void
    {
        $this->errors_ = [];
        $this->detailError = null;
        $this->generalError = null;

        $data = $this->form;
        $data['checked'] = $this->checked;
        $data['acknowledged'] = $this->acknowledged;
        $data['details'] = collect($this->detailRows)
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'time_slot' => $row['time_slot'],
                'digester_temp_c' => $row['digester_temp_c'] !== '' ? $row['digester_temp_c'] : null,
                'digester_level_percent' => $row['digester_level_percent'] !== '' ? $row['digester_level_percent'] : null,
                'press_motor_current_amps' => $row['press_motor_current_amps'] !== '' ? $row['press_motor_current_amps'] : null,
                'cone_hydraulic_pressure_bar' => $row['cone_hydraulic_pressure_bar'] !== '' ? $row['cone_hydraulic_pressure_bar'] : null,
                'dilution_water_temp_c' => $row['dilution_water_temp_c'] !== '' ? $row['dilution_water_temp_c'] : null,
                'downtime_reason' => $row['downtime_reason'] !== '' ? $row['downtime_reason'] : null,
            ])
            ->all();

        $service = app(PressingRecordService::class);

        try {
            if ($this->isEdit) {
                $record = $service->update($this->id, $data, auth()->user());
            } else {
                $record = $service->create($data, auth()->user());
            }
        } catch (ValidationException $e) {
            $errors = $e->errors();

            if (isset($errors['details'])) {
                $this->detailError = $errors['details'][0];
                unset($errors['details']);
            }

            $this->errors_ = collect($errors)->map(fn ($messages) => $messages[0])->all();

            return;
        } catch (HttpException|AuthorizationException $e) {
            // Sejak 2026-09-28 blok ini juga menangkap
            // CrossMillWriteDeniedException (403 — production line atau
            // record milik mill lain), supaya penolakan itu muncul sebagai
            // alert di layar, bukan halaman 403.
            // e.g. NoActivePressingStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.pressing.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-pressing', [
            'operationalTargets' => PressingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
