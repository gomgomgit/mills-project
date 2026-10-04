<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Services\SterilizerRecordService;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
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
    use GuardsRecordIdShape;
    use ScopesToActorMill;

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
        $this->productionLineOptions = $this->loadProductionLineOptions();

        if ($id === null) {
            $this->isEdit = false;
            $this->form['date'] = now()->format('Y-m-d');

            return;
        }

        $this->id = $id;
        $this->isEdit = true;

        if (! $this->isRecordIdShapeValid($id)) {
            // id bukan UUID tidak boleh sampai ke SQL (PostgreSQL: 22P02),
            // perlakukan sama dengan UUID yang tidak dikenal.
            $this->notFound = true;

            return;
        }

        try {
            $record = app(SterilizerRecordService::class)->getDetail($id);
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

        $this->form['sterilizer_id'] = $record['sterilizer_id'] ?? '';
        $this->form['note'] = $record['note'] ?? '';
        $this->form['date'] = $record['date'] ? Carbon::parse($record['date'])->format('Y-m-d') : '';

        $this->stationName = $record['station_name'] ?? null;
        $this->checked = filled($record['checked_by_name']);
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => collect($row)->only(array_merge(['id'], self::DETAIL_FIELDS))->all())
            ->toArray();
    }

    /**
     * loadProductionLineOptions() — feeds the Production Line-select on
     * create mode, SCOPED TO THE MILL OF THE AUTHENTICATED USER.
     *
     * 2026-09-28: the mill-scoping logic that used to be inlined here — the
     * only one of the 18 Form components that had it — now lives in
     * App\Support\Concerns\ScopesToActorMill::productionLineOptionsForActor(),
     * shared with the other 17 Form components AND with the 18
     * *RecordService write guards, so the dropdown can never again offer
     * what create()/update() will refuse. Behaviour is unchanged: Admin is
     * the only role not bound to one mill and deliberately keeps the full
     * cross-mill list; a non-Admin without a business_unit_id gets an empty
     * list rather than the whole table (and an actionable 422 the moment
     * they try to save).
     *
     * Cross-mill data-integrity guard, for the record: a Supervisor / Mill
     * Management / Operator is bound to exactly one mill
     * (users.business_unit_id) and a station record they log must belong to
     * that mill — the Production Line is what resolves the Station (see
     * SterilizerRecordService::create()), so an unscoped option list let
     * them silently write this mill's log sheet against ANOTHER mill's
     * Production Line, after which every mill-scoped read
     * (SterilizerReportService, Data Browser) correctly refuses to show it.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function loadProductionLineOptions(): array
    {
        return $this->productionLineOptionsForActor(auth()->user());
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
            $close = Carbon::createFromFormat('H:i', substr($row['close_door_time'], 0, 5));
            $open = Carbon::createFromFormat('H:i', substr($row['open_door_time'], 0, 5));
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
        } catch (HttpException|AuthorizationException $e) {
            // Sejak 2026-09-28 blok ini juga menangkap
            // CrossMillWriteDeniedException (403 — production line atau
            // record milik mill lain), supaya penolakan itu muncul sebagai
            // alert di layar, bukan halaman 403.
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
