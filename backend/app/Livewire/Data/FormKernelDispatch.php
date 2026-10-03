<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Services\KernelDispatchRecordService;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormKernelDispatch — screen-113--form-kernel-dispatch-web
 * (Livewire web page, routes `data.kernel-dispatch.create`
 * /data/kernel-dispatch/create and `data.kernel-dispatch.edit`
 * /data/kernel-dispatch/{id}/edit — same component class handles both
 * modes). Mirrors FormSolidWasteDisposal's header/detail-row shape, minus
 * the fixed grid/column-count concept — Kernel Dispatch's detail rows are
 * a free event log, added manually per occurrence (no per-row time-slot
 * uniqueness).
 */
#[Layout('data.kernel-dispatch-form')]
class FormKernelDispatch extends Component
{
    use ScopesToActorMill;

    protected const FIELDS = ['production_line_id', 'kernel_dispatch_id', 'date', 'note'];

    protected const DETAIL_FIELDS = [
        'event_date', 'shift', 'weighbridge_ticket_no', 'waybill_number', 'transporter_contractor',
        'vehicle_plate_no', 'driver_name', 'silo_source_id', 'destination_buyer',
        'gross_weight_mt', 'tare_weight_mt', 'kernel_moisture_percent', 'dirt_impurities_percent',
        'ffa_percent', 'broken_kernel_percent', 'security_seal_no_top', 'security_seal_no_bottom',
        'weighbridge_operator_id', 'remarks_gate_status', 'findings',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'production_line_id' => '',
        'kernel_dispatch_id' => '',
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
        $this->productionLineOptions = $this->productionLineOptionsForActor(auth()->user());

        if ($id === null) {
            $this->isEdit = false;
            $this->form['date'] = now()->format('Y-m-d');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        try {
            $record = app(KernelDispatchRecordService::class)->getDetail($id);
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

        $this->form['kernel_dispatch_id'] = $record['kernel_dispatch_id'] ?? '';
        $this->form['note'] = $record['note'] ?? '';
        $this->form['date'] = $record['date'] ? Carbon::parse($record['date'])->format('Y-m-d') : '';

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

        $service = app(KernelDispatchRecordService::class);

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
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.kernel-dispatch.detail', ['id' => $record['id']]), navigate: false);
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
        return view('livewire.data.form-kernel-dispatch');
    }
}
