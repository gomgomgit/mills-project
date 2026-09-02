<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Services\CpoDispatchRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormCpoDispatch — screen-114--form-cpo-dispatch-web
 * (Livewire web page, routes `data.cpo-dispatch.create`
 * /data/cpo-dispatch/create and `data.cpo-dispatch.edit`
 * /data/cpo-dispatch/{id}/edit — same component class handles both
 * modes). Mirrors FormKernelDispatch's header/detail-row shape, minus
 * the fixed grid/column-count concept — CPO Dispatch's detail rows are
 * a free event log, added manually per occurrence (no per-row time-slot
 * uniqueness).
 */
#[Layout('data.cpo-dispatch-form')]
class FormCpoDispatch extends Component
{
    protected const FIELDS = ['production_line_id', 'cpo_dispatch_id', 'date', 'note'];

    protected const DETAIL_FIELDS = [
        'event_date', 'shift', 'time_in', 'time_out', 'waybill_number', 'tanker_plate_no',
        'transport_company', 'driver_name', 'storage_tank_source', 'seal_no_top', 'seal_no_bottom',
        'gross_weight_mt', 'tare_weight_mt', 'ffa_percent', 'moisture_percent', 'impurities_percent',
        'dobi', 'destination_buyer', 'weighbridge_operator', 'findings',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'cpo_dispatch_id' => '',
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
            $record = app(CpoDispatchRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;

            return;
        }

        $this->form['cpo_dispatch_id'] = $record['cpo_dispatch_id'] ?? '';
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
            $row[$field] = '';
        }

        $this->detailRows[] = $row;
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    public function rowNetWeight(int $rowIndex): ?float
    {
        $row = $this->detailRows[$rowIndex] ?? null;

        if ($row === null || $row['gross_weight_mt'] === '' || $row['tare_weight_mt'] === '') {
            return null;
        }

        return (float) $row['gross_weight_mt'] - (float) $row['tare_weight_mt'];
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

        $service = app(CpoDispatchRecordService::class);

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

        $this->redirect(route('data.cpo-dispatch.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-cpo-dispatch');
    }
}
