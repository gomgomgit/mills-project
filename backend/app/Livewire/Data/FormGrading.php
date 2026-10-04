<?php

namespace App\Livewire\Data;

use App\Enums\UserRole;
use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Models\GradingParameter;
use App\Models\Station;
use App\Models\WeighbridgeRecord;
use App\Services\GradingRecordService;
use App\Services\StationService;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FormGrading — screen-023--form-grading-web / "Form Grading (Web)"
 * (Livewire web page, routes `data.grading.create` /data/grading/create
 * and `data.grading.edit` /data/grading/{id}/edit — same component class
 * handles both modes, mode determined by whether $id was bound). Mirrors
 * FormWeighbridge (screen-022) exactly for the header form, plus a
 * dynamic Grading Detail grid (Add/Remove row, Quality Parameter
 * mutual-exclusion across rows, UOM/Percentage computed preview).
 *
 * Reuses GradingRecordService::create()/update() — the exact same service
 * methods the API controller (App\Http\Controllers\Api\
 * GradingRecordController::store()/update()) calls.
 *
 * Two deliberate divergences from Form Grading mobile (screen-011), per
 * explicit product direction (same divergences as screen-022):
 *  - `date` defaults to today() but stays a genuinely editable `date`
 *    input (mobile locks it after auto-set-once).
 *  - Saved records are fully editable afterwards (mode edit) — there is
 *    no draft/pause/lock concept on web; Simpan always results in
 *    status=saved.
 *
 * UOM and Percentage are NOT bound as editable inputs despite uiux-spec's
 * 'web-form-input' convention (no disabled fields) — see
 * GradingRecordService::upsertDetails()'s docblock for why these are a
 * deliberate exception (server-computed/snapshot values, same reasoning
 * as screen-022's Net Weight).
 */
#[Layout('data.grading-form')]
class FormGrading extends Component
{
    use GuardsRecordIdShape;
    use ScopesToActorMill;

    protected const FIELDS = [
        'production_line_id',
        'grading_number',
        'date',
        'weighbridge_record_id',
        'license_plate_no',
        'vehicle_code',
        'estate_supplier',
        'division',
        'netto',
        'quantity',
        'note',
    ];

    public ?string $id = null;

    public bool $isEdit = false;

    public bool $notFound = false;

    /** @var array<string, mixed> */
    public array $form = [
        'business_unit_id' => '',
        'production_line_id' => '',
        'grading_number' => '',
        'date' => '',
        'weighbridge_record_id' => '',
        'license_plate_no' => '',
        'vehicle_code' => '',
        'estate_supplier' => '',
        'division' => '',
        'netto' => '',
        'quantity' => '',
        'note' => '',
    ];

    /** @var array<int, array{id: ?string, grading_parameter_id: string, quantity: mixed}> */
    public array $detailRows = [];

    public bool $acknowledged = false;

    public ?string $businessUnitName = null;

    /** Nama Production Line record (mode edit, tampil sebagai teks). */
    public ?string $productionLineName = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $businessUnitOptions = [];

    /** @var array<int, array{id: string, name: string}> */
    public array $productionLineOptions = [];

    /** @var array<int, array{id: string, wb_card_number: string, vehicle_number: string, estate_supplier: string, division: ?string}> */
    public array $weighbridgeOptions = [];

    /** @var array<int, array{id: string, name: string, uom: string}> */
    public array $gradingParameterOptions = [];

    /** @var array<string, string> */
    public array $errors_ = [];

    public ?string $detailError = null;

    public ?string $generalError = null;

    public function mount(?string $id = null): void
    {
        $this->businessUnitOptions = $this->businessUnitOptionsForActor(auth()->user());
        $this->gradingParameterOptions = GradingParameter::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'uom'])
            ->map(fn (GradingParameter $p) => ['id' => $p->id, 'name' => $p->name, 'uom' => $p->uom->value])
            ->toArray();

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
            $record = app(GradingRecordService::class)->getDetail($id);
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

        foreach (self::FIELDS as $field) {
            if ($field === 'production_line_id') {
                continue;
            }
            $this->form[$field] = $record[$field] ?? '';
        }

        $this->form['date'] = $record['date'] ? Carbon::parse($record['date'])->format('Y-m-d') : '';
        // Sampai 2026-10-05 label "Business Unit" diisi NAMA STASIUN
        // (station_name) dan Production Line tidak tampil — bug yang sama
        // dengan FormWeighbridge (diperbaiki 2026-10-04).
        $this->businessUnitName = $record['business_unit_name'] ?? null;
        $this->productionLineName = $record['production_line_name'] ?? null;
        $this->acknowledged = filled($record['acknowledged_by_name']);

        $this->detailRows = collect($record['details'])
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'grading_parameter_id' => $row['grading_parameter_id'],
                'quantity' => $row['quantity'],
            ])
            ->toArray();

        // Edit mode: scope the WB Card No dropdown to the record's own
        // mill, resolved via its station (business_unit_id is immutable
        // and not directly on $this->form, so it can't drive
        // updatedFormBusinessUnitId() the way create-mode does).
        $businessUnitId = Station::find($record['station_id'])?->business_unit_id;
        if ($businessUnitId !== null) {
            $this->loadWeighbridgeOptions($businessUnitId);
        }
    }

    /** Mode buat: BU dipilih -> reset Production Line + muat ulang dropdown WB Card No scoped ke mill tsb. */
    public function updatedFormBusinessUnitId(): void
    {
        $this->form['production_line_id'] = '';
        $this->form['weighbridge_record_id'] = '';
        $this->loadProductionLineOptions();
        $this->loadWeighbridgeOptions($this->form['business_unit_id']);
    }

    /**
     * Mengunci `$form['business_unit_id']` ke mill aktor sebelum cascade
     * apa pun dibaca (2026-09-28). Dropdown mill-nya sudah disempitkan oleh
     * businessUnitOptionsForActor(), tapi properti Livewire-nya publik dan
     * bisa di-set ke mill mana pun lewat request yang dibuat-buat — tanpa
     * langkah ini, cascade-nya memuat production line (dan di Form Grading,
     * WB Card No) milik mill lain. Nilainya ditulis balik ke $form supaya
     * layarnya tidak menampilkan mill yang sudah dibuang.
     */
    protected function pinFormMillToActor(): ?string
    {
        $current = (string) ($this->form['business_unit_id'] ?? '');

        $pinned = $this->clampMillIdForActor(auth()->user(), $current !== '' ? $current : null);

        $this->form['business_unit_id'] = (string) ($pinned ?? '');

        return $pinned;
    }

    protected function loadProductionLineOptions(): void
    {
        $businessUnitId = $this->pinFormMillToActor();
        $this->productionLineOptions = app(StationService::class)
            ->productionLineOptions($businessUnitId);
    }

    protected function loadWeighbridgeOptions(?string $businessUnitId): void
    {
        $businessUnitId = $this->clampMillIdForActor(auth()->user(), $businessUnitId);

        if (blank($businessUnitId)) {
            $this->weighbridgeOptions = [];

            return;
        }

        $this->weighbridgeOptions = WeighbridgeRecord::query()
            ->whereHas('station', fn ($q) => $q->where('business_unit_id', $businessUnitId))
            ->orderByDesc('record_datetime')
            ->get(['id', 'wb_card_number', 'vehicle_number', 'estate_supplier', 'division'])
            ->map(fn (WeighbridgeRecord $r) => [
                'id' => $r->id,
                'wb_card_number' => $r->wb_card_number,
                'vehicle_number' => $r->vehicle_number,
                'estate_supplier' => $r->estate_supplier,
                'division' => $r->division,
            ])
            ->toArray();
    }

    /** WB Card No dipilih (baru/berganti) -> auto-isi ULANG license_plate_no/estate_supplier/division. */
    public function updatedFormWeighbridgeRecordId(): void
    {
        $selected = collect($this->weighbridgeOptions)->firstWhere('id', $this->form['weighbridge_record_id']);

        if ($selected === null) {
            return;
        }

        $this->form['license_plate_no'] = $selected['vehicle_number'];
        $this->form['estate_supplier'] = $selected['estate_supplier'];
        $this->form['division'] = $selected['division'] ?? '';
    }

    public function addDetailRow(): void
    {
        $this->detailRows[] = ['id' => null, 'grading_parameter_id' => '', 'quantity' => ''];
    }

    public function removeDetailRow(int $index): void
    {
        unset($this->detailRows[$index]);
        $this->detailRows = array_values($this->detailRows);
    }

    /**
     * availableParameterOptions() — dropdown options for one row = every
     * gradingParameterOptions entry MINUS the grading_parameter_id
     * selected by every OTHER row (the row's own current selection stays
     * visible in its own dropdown).
     *
     * @return array<int, array{id: string, name: string, uom: string}>
     */
    public function availableParameterOptions(int $rowIndex): array
    {
        $usedByOtherRows = collect($this->detailRows)
            ->reject(fn ($row, $i) => $i === $rowIndex)
            ->pluck('grading_parameter_id')
            ->filter()
            ->all();

        return collect($this->gradingParameterOptions)
            ->reject(fn ($param) => in_array($param['id'], $usedByOtherRows, true))
            ->values()
            ->all();
    }

    protected function parameterUom(string $gradingParameterId): ?string
    {
        return collect($this->gradingParameterOptions)->firstWhere('id', $gradingParameterId)['uom'] ?? null;
    }

    /** rowUom()/rowPercentage() — read-only preview computed client-visible via the same formula the server applies. */
    public function rowUom(int $rowIndex): ?string
    {
        $paramId = $this->detailRows[$rowIndex]['grading_parameter_id'] ?? null;

        return $paramId ? $this->parameterUom($paramId) : null;
    }

    public function rowPercentage(int $rowIndex): ?float
    {
        $uom = $this->rowUom($rowIndex);
        $quantity = $this->detailRows[$rowIndex]['quantity'] ?? null;

        if ($uom === null || ! is_numeric($quantity)) {
            return null;
        }

        $netto = is_numeric($this->form['netto']) ? (float) $this->form['netto'] : 0.0;
        $headerQuantity = is_numeric($this->form['quantity']) ? (float) $this->form['quantity'] : 0.0;

        if ($uom === 'kg') {
            return $netto > 0 ? round(((float) $quantity / $netto) * 100, 2) : 0.0;
        }

        return $headerQuantity > 0 ? round(((float) $quantity / $headerQuantity) * 100, 2) : 0.0;
    }

    public function save(): void
    {
        $this->errors_ = [];
        $this->detailError = null;
        $this->generalError = null;

        $data = $this->form;
        $data['acknowledged'] = $this->acknowledged;
        $data['details'] = $this->detailRows;

        $service = app(GradingRecordService::class);

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
            // e.g. NoActiveGradingStationException (422) — a single,
            // non-field-keyed condition, shown as a page-level alert
            // rather than an inline per-field error.
            $this->generalError = $e->getMessage();

            return;
        }

        $this->redirect(route('data.grading.detail', ['id' => $record['id']]), navigate: false);
    }

    public function isMillManagement(): bool
    {
        return auth()->user()?->role === UserRole::MillManagement;
    }

    public function render()
    {
        return view('livewire.data.form-grading');
    }
}
