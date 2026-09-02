<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\SterilizerRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormSterilizer — screen-126--form-sterilizer-web
 * (Livewire web page, routes `data.sterilizer.create`
 * /data/sterilizer/create and `data.sterilizer.edit`
 * /data/sterilizer/{id}/edit — same component class handles both
 * modes). Mirrors FormCpoDispatch's header/detail-row shape — event-log
 * pattern, detail rows are a free log added manually per sterilization
 * cycle (no fixed grid/column-count concept, no per-row time-slot
 * uniqueness).
 *
 * `duration_minutes` is NEVER an input field — it is computed server-side
 * by SterilizerRecordService and only ever displayed (see
 * rowDurationMinutes() below, used for the live preview before save).
 */
#[Layout('data.sterilizer-form')]
class FormSterilizer extends Component
{
    protected const FIELDS = ['production_line_id', 'sterilizer_id', 'date', 'note'];

    protected const DETAIL_FIELDS = [
        'sterilizer_no', 'close_door_time', 'peak_1_time', 'exhaust_1_time', 'peak_2_time',
        'exhaust_2_time', 'peak_3_time', 'exhaust_3_time', 'open_door_time',
        'number_of_cages', 'cages_status', 'checked_by_spv', 'remarks',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'sterilizer_id' => '',
        'date' => '',
        'note' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $detailRows = [];

    public bool $checked = false;

    public bool $acknowledged = false;

    public ?string $stationName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $productionLineOptions = [];

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $detailError = null;

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->productionLineOptions = ProductionLine::query()->orderBy('name')->get(['id', 'name'])->toArray();

        if ($id === null) {
            $this->isEdit = false;
            $this->form['date'] = now()->format('Y-m-d');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(SterilizerRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['sterilizer_id'] = $record['sterilizer_id'] ?? '';
        $this->form['note'] = $record['note'] ?? '';
        $this->form['date'] = $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('Y-m-d') : '';

        $this->stationName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => collect($row)->only(array_merge(['id'], self::DETAIL_FIELDS))->all())
            ->toArray();
    }

    public function addDetailRow(): void
    {
        $row = ['id' => null];

        foreach (self::DETAIL_FIELDS as $field) {
            $row[$field] = $field === 'checked_by_spv' ? false : '';
        }

        $this->detailRows[] = $row;
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    /**
     * rowDurationMinutes() — client-preview-only mirror of
     * SterilizerRecordService::computeDurationMinutes(). Purely
     * informational (display only, never submitted) — the server always
     * recomputes this value on save, per entity-catalog:
     * "duration_minutes ... BUKAN kolom yang diinput user secara
     * langsung".
     */
    public function rowDurationMinutes(int $rowIndex): ?int
    {
        $row = $this->detailRows[$rowIndex] ?? null;

        if ($row === null || empty($row['close_door_time']) || empty($row['open_door_time'])) {
            return null;
        }

        try {
            $close = \Illuminate\Support\Carbon::createFromFormat('H:i', substr($row['close_door_time'], 0, 5));
            $open = \Illuminate\Support\Carbon::createFromFormat('H:i', substr($row['open_door_time'], 0, 5));
        } catch (\Throwable) {
            return null;
        }

        $minutes = $close->diffInMinutes($open, false);

        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        return $minutes;
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

        $service = app(SterilizerRecordService::class);

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
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.sterilizer.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-sterilizer');
    }
}
