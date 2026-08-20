<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Services\CagesTrackRecordService;
use App\Services\MillSettingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormCagesTrack — screen-024--form-cages-track-web / "Form Cages Track
 * (Web)" (Livewire web page, routes `data.cages-track.create`
 * /data/cages-track/create and `data.cages-track.edit`
 * /data/cages-track/{id}/edit — same component class handles both modes,
 * mode determined by whether $id was bound). Mirrors FormGrading
 * (screen-023) exactly for header form + dynamic detail grid shape, plus
 * FormWeighbridge (screen-022) for the dual Checked/Acknowledged
 * self-attestation checkboxes (unlike Grading, which only has
 * Acknowledged — this screen keeps both, per Form Cages Track mobile /
 * Detail Cages Track Web).
 *
 * Reuses CagesTrackRecordService::create()/update() — the exact same
 * service methods the API controller (App\Http\Controllers\Api\
 * CagesTrackRecordController::store()/update()) calls.
 *
 * Two deliberate divergences from Form Cages Track mobile (screen-012),
 * per explicit product direction (same divergences as screen-022/023):
 *  - date/tippler_start_time/tippler_stop_time each default to now() but
 *    stay genuinely editable inputs (mobile locks/freezes them).
 *  - Saved records are fully editable afterwards (mode edit) — there is
 *    no draft/pause/Clear concept on web; Simpan always results in
 *    status=saved.
 *
 * Grid column count (N = jumlah_cages) is resolved via
 * MillSettingService::getJumlahCages() — a direct in-process call, NOT a
 * separate REST endpoint, mirroring the query-langsung convention already
 * used for Business Unit/WB Card No/Quality Parameter dropdowns on
 * screen-022/023. total_cages/cages_remain are NOT bound as editable
 * inputs despite uiux-spec's 'web-form-input' convention (no disabled
 * fields) — see CagesTrackRecordService::upsertDetails()'s docblock for
 * why these are a deliberate exception (server-computed values, same
 * reasoning as screen-022's Net Weight / screen-023's UOM+Percentage).
 */
#[Layout('data.cages-track-form')]
class FormCagesTrack extends Component
{
    protected const FIELDS = [
        'business_unit_id',
        'cages_track_number',
        'date',
        'tippler_start_time',
        'tippler_stop_time',
        'cages_out',
        'cages_tipped',
        'note',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'business_unit_id' => '',
        'cages_track_number' => '',
        'date' => '',
        'tippler_start_time' => '',
        'tippler_stop_time' => '',
        'cages_out' => '',
        'cages_tipped' => '',
        'note' => '',
    ];

    /** @var array<int, array{id: ?string, tipped_hour: mixed, checked_cage_numbers: array<int, int>}> */
    public array $detailRows = [];

    public bool $checked = false;

    public bool $acknowledged = false;

    /** Read-only display for edit mode (business_unit_id is immutable after create). */
    public ?string $businessUnitName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $businessUnitOptions = [];

    /** N — jumlah kolom checklist cage, diresolve dari mill-setting milik Business Unit terpilih. */
    public int $jumlahCages = 0;

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $detailError = null;

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->businessUnitOptions = BusinessUnit::query()->orderBy('name')->get(['id', 'name'])->toArray();

        if ($id === null) {
            $this->isEdit = false;
            $now = now()->format('Y-m-d\TH:i');
            $this->form['date'] = now()->format('Y-m-d');
            $this->form['tippler_start_time'] = $now;
            $this->form['tippler_stop_time'] = $now;

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(CagesTrackRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        foreach (self::FIELDS as $field) {
            if ($field === 'business_unit_id') {
                continue;
            }
            $this->form[$field] = $record[$field] ?? '';
        }

        $this->form['date'] = $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('Y-m-d') : '';
        $this->form['tippler_start_time'] = $record['tippler_start_time']
            ? \Illuminate\Support\Carbon::parse($record['tippler_start_time'])->format('Y-m-d\TH:i')
            : '';
        $this->form['tippler_stop_time'] = $record['tippler_stop_time']
            ? \Illuminate\Support\Carbon::parse($record['tippler_stop_time'])->format('Y-m-d\TH:i')
            : '';

        $this->businessUnitName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['tipped_times'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'tipped_hour' => $row['tipped_hour'],
                'checked_cage_numbers' => $row['checked_cage_numbers'] !== ''
                    ? collect(explode(',', (string) $row['checked_cage_numbers']))->map(fn ($n) => (int) $n)->all()
                    : [],
            ])
            ->toArray();

        // Edit mode: resolve N from the record's own mill via its station
        // (business_unit_id is immutable and not directly on $this->form,
        // so it can't drive updatedFormBusinessUnitId() the way
        // create-mode does).
        $businessUnitId = Station::find($record['station_id'])?->business_unit_id;
        if ($businessUnitId !== null) {
            $this->jumlahCages = app(MillSettingService::class)->getJumlahCages($businessUnitId);
        }
    }

    /** Mode buat: BU dipilih -> resolve ulang N (jumlah_cages) dari mill-setting mill tsb. */
    public function updatedFormBusinessUnitId(): void
    {
        if (blank($this->form['business_unit_id'])) {
            $this->jumlahCages = 0;

            return;
        }

        $this->jumlahCages = app(MillSettingService::class)->getJumlahCages($this->form['business_unit_id']);
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = ['id' => null, 'tipped_hour' => '', 'checked_cage_numbers' => []];
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    public function canAddRow(): bool
    {
        return $this->jumlahCages > 0;
    }

    public function toggleCage(int $rowIndex, int $cageNumber): void
    {
        $current = collect($this->detailRows[$rowIndex]['checked_cage_numbers'] ?? []);

        $this->detailRows[$rowIndex]['checked_cage_numbers'] = $current->contains($cageNumber)
            ? $current->reject(fn ($n) => $n === $cageNumber)->values()->all()
            : $current->push($cageNumber)->values()->all();
    }

    /**
     * availableHourOptions() — jam 0-23 DIKURANGI (jam yang sudah dipakai
     * baris manapun) DAN (jam yang <= jam TERTINGGI di antara baris lain
     * yang sudah terisi) — mirrors Form Cages Track mobile's
     * dropdown-exclusion logic (entity-catalog constraint: ascending, no
     * duplicates). Uses MAX of other rows' hours (not literal array
     * index) so the rule holds regardless of which row is being computed
     * — rows are filled in insertion order in normal use, so "last added"
     * and "highest hour among other rows" coincide in practice.
     *
     * @return array<int, int>
     */
    public function availableHourOptions(int $rowIndex): array
    {
        $usedHours = collect($this->detailRows)
            ->reject(fn ($row, $i) => $i === $rowIndex)
            ->pluck('tipped_hour')
            ->filter(fn ($h) => $h !== null && $h !== '')
            ->map(fn ($h) => (int) $h);

        $maxOtherHour = $usedHours->max();

        return collect(range(0, 23))
            ->reject(fn ($hour) => $usedHours->contains($hour))
            ->reject(fn ($hour) => $maxOtherHour !== null && $hour <= $maxOtherHour)
            ->values()
            ->all();
    }

    public function rowTotalCages(int $rowIndex): int
    {
        return count($this->detailRows[$rowIndex]['checked_cage_numbers'] ?? []);
    }

    public function rowCagesRemain(int $rowIndex): int
    {
        return $this->jumlahCages - $this->rowTotalCages($rowIndex);
    }

    public function save(): void
    {
        $this->errors_ = [];
        $this->detailError = null;
        $this->generalError = null;

        $data = $this->form;
        $data['checked'] = $this->checked;
        $data['acknowledged'] = $this->acknowledged;
        $data['details'] = $this->detailRows;

        $service = app(CagesTrackRecordService::class);

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
        } catch (HttpException $e) {
            // e.g. NoActiveCagesTrackStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.cages-track.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-cages-track');
    }
}
