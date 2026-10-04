<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveWeighbridgeStationException;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Support\AppTime;
use App\Support\Concerns\EnforcesPeriodLock;
use App\Support\Concerns\ScopesToActorMill;
use App\Support\ExportValue;
use App\Support\Pagination;
use App\Support\SheetWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * WeighbridgeRecordService — screen-016--data-browser-weighbridge-web /
 * usecase (Data Browser Weighbridge, web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * WeighbridgeRecordController) and the Livewire component (App\Livewire\
 * Data\DataBrowserWeighbridge), so filtering/pagination/export rules stay
 * identical between the two entry points (mirrors the AuthService pattern
 * used by screen-001/002/003/004).
 *
 * Filters accepted by both listRecords() and export(): 'date_from',
 * 'date_to' (both nullable date strings, filtered against
 * record_datetime), 'weighbridge_type' (nullable enum receive/dispatch),
 * 'business_unit_id' (nullable uuid, filtered via the
 * station->business_unit_id relationship).
 */
class WeighbridgeRecordService
{
    use EnforcesPeriodLock, ScopesToActorMill;

    /**
     * Row limit enforced on export() (business_logic step 5) — protects
     * against unbounded memory/time usage when streaming a CSV/XLSX for a
     * very large filtered dataset. Not specified as an exact number in the
     * tech-spec ("e.g. 50000 rows"); 50000 is used as a pragmatic MVP
     * ceiling — see implementation_notes.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query, paginate via the shared Pagination helper,
     * and return the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('record_datetime');

        // `productionLine` dimuat di sini, bukan lewat relasi `station`:
        // kolom line milik record sendiri adalah sumber kebenarannya.
        $paginator = $query->with('productionLine:id,name')->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (WeighbridgeRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic step 5-6: re-run the same filter query
     * (no pagination), enforce the row limit, generate a CSV (or
     * real .xlsx via App\Support\SheetWriter — see implementation_notes) body, and
     * return it as a StreamedResponse for download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('record_datetime');

        $total = $query->count();

        if ($total > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        try {
            $records = $query->with(['productionLine:id,name', 'checkedBy:id,name', 'acknowledgedBy:id,name'])->get();

            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($records, $format) {
                $handle = SheetWriter::open($format);

                // Judul kolom = label layar Detail Weighbridge (temuan audit
                // 2026-10-04 #8), termasuk Checked By / Acknowledged By seperti
                // ekspor stasiun lain. Status memakai label Indonesia
                // (ExportValue::status — sama dengan 17 ekspor lain), waktu
                // tanpa detik.
                $handle->row([
                    'Production Line',
                    'No. WB Card',
                    'Tipe Weighbridge',
                    'Tanggal & Waktu',
                    'No. Kendaraan',
                    'Nama Sopir',
                    'Estate/Supplier',
                    'Tujuan Muatan',
                    'Divisi',
                    'Blok',
                    'Berat Kotor (Gross Weight) (kg)',
                    'Berat Kosong (Tare Weight) (kg)',
                    'Berat Bersih (Net Weight) (kg)',
                    'Kuantitas (tandan)',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ]);

                foreach ($records as $record) {
                    /** @var WeighbridgeRecord $record */
                    $type = $record->weighbridge_type instanceof \BackedEnum ? $record->weighbridge_type->value : (string) $record->weighbridge_type;

                    $handle->row([
                        $record->productionLine?->name,
                        $record->wb_card_number,
                        $type === 'dispatch' ? 'Dispatch' : 'Receive',
                        optional($record->record_datetime)->format('Y-m-d H:i'),
                        $record->vehicle_number,
                        $record->driver_name,
                        $record->estate_supplier,
                        $record->destination,
                        $record->division,
                        $record->block,
                        $record->gross_weight,
                        $record->tare_weight,
                        $record->net_weight,
                        $record->quantity,
                        $record->checkedBy?->name,
                        $record->acknowledgedBy?->name,
                        ExportValue::status($record->status),
                    ]);
                }

                $handle->close();
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException;
        }
    }

    protected function exportRowLimitLabel(): string
    {
        return number_format(self::EXPORT_ROW_LIMIT);
    }

    /**
     * Resolves the Content-Type + filename for the requested export format.
     * format=excel is a real .xlsx file written by App\Support\SheetWriter
     * (temuan audit 2026-10-04 #1 — previously a CSV body under an xlsx name).
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "weighbridge-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "weighbridge-records_{$timestamp}.csv",
        ];
    }

    /**
     * buildFilteredQuery() — business_logic step 1-2: validate the date
     * range (date_from > date_to → INVALID_DATE_RANGE) then apply the
     * business_unit_id (via station->business_unit_id), weighbridge_type,
     * and record_datetime BETWEEN filters.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        // CAKUPAN MILL DULU, sebelum filter apa pun dibaca. Sampai
        // 2026-09-28 `business_unit_id` di sini datang mentah dari properti
        // Livewire Data Browser (default '') atau dari query string API,
        // dan nilai kosong berarti TANPA cakupan sama sekali — sehingga
        // Supervisor mana pun bisa melihat dan mengekspor record mill lain.
        // scopeFiltersToActorMill() MEMBUANG nilai kiriman klien untuk
        // aktor yang terikat mill dan menggantinya dengan mill aktor
        // sendiri, jadi mengirim mill lain lewat properti atau query string
        // tidak mengubah apa pun. Hanya Admin yang nilainya dipakai apa
        // adanya (kosong = semua mill, perilaku lama dipertahankan).
        //
        // Dipasang di buildFilteredQuery() karena listRecords() DAN
        // export() sama-sama lewat sini — satu titik untuk dua jalur baca.
        $filters = $this->scopeFiltersToActorMill($filters);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $weighbridgeType = $filters['weighbridge_type'] ?? null;
        $businessUnitId = $filters['business_unit_id'] ?? null;
        $productionLineId = $filters['production_line_id'] ?? null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw new InvalidDateRangeException;
        }

        $query = WeighbridgeRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        // PENYARINGAN PER LINE LANGSUNG DI TABEL RECORD, bukan lewat
        // whereHas('station', ...). Sejak 2026_09_28_000041 setiap tabel
        // record punya kolom `production_line_id` sendiri (NOT NULL,
        // di-snapshot dari stasiun saat create), jadi tidak perlu subquery
        // per halaman — dan, yang jauh lebih penting, nilainya PERMANEN:
        // stasiun yang kemudian dipindah ke line lain tidak menarik record
        // lamanya ikut pindah. Menyaring lewat station akan menyaring
        // menurut konfigurasi HARI INI, bukan menurut line tempat data itu
        // benar-benar dihasilkan.
        //
        // Nilainya sudah dijepit ke mill aktor oleh scopeFiltersToActorMill()
        // di atas: line milik mill lain sudah menjadi null di sana (jatuh ke
        // "semua line"), jadi baris ini tidak pernah bisa memperluas cakupan.
        if ($productionLineId) {
            $query->where('production_line_id', $productionLineId);
        }

        if ($weighbridgeType) {
            $query->where('weighbridge_type', $weighbridgeType);
        }

        if ($dateFrom) {
            $query->whereDate('record_datetime', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('record_datetime', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Fields accepted by both create() and update() request bodies —
     * everything except production_line_id (create-only, resolves
     * station_id via resolveActiveStationForActor(), never accepted on
     * update — the station, and with it the line and mill, cannot change
     * after the record is created) and checked/
     * acknowledged (role-gated booleans, handled separately below since
     * they map to checked_by/acknowledged_by, not stored verbatim).
     */
    protected const FORM_FIELDS = [
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

    /**
     * create() — screen-022--form-weighbridge-web business_logic steps
     * 1-4: validate required fields (destination required iff
     * weighbridge_type=dispatch) → resolve the sole active weighbridge
     * Station for production_line_id (2026-08-20: was business_unit_id —
     * Production Line inserted into the hierarchy between Business Unit
     * and Station, see entity-catalog v9) (422
     * NO_ACTIVE_WEIGHBRIDGE_STATION if none) → apply role-gated
     * checked/acknowledged → insert with
     * status=saved. net_weight is never accepted from $data — the
     * WeighbridgeRecord model's `saving` event always recomputes it from
     * gross/tare (see that model's docblock); this is why Net Weight
     * is rendered as read-only TEXT in the form (since 2026-10-04; it was
     * a disabled input, against the "web inputs are never disabled"
     * convention) — the value would be silently overwritten on save
     * regardless of what the UI sent, so exposing it as editable would be
     * misleading.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws NoActiveWeighbridgeStationException
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);

        $this->validateForm($attributes);

        $station = $this->resolveActiveStationForActor(
            $data['production_line_id'] ?? null,
            'weighbridge',
            $actor,
        );

        if ($station === null) {
            throw new NoActiveWeighbridgeStationException;
        }

        // KUNCI PERIODE (usecase-141) — sebelum satu baris pun ditulis, supaya
        // penolakan tidak menyisakan induk tanpa detail. Jenis stasiun dan mill
        // diambil dari stasiun yang SUDAH di-resolve, bukan dari request.
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['record_datetime'] ?? null, 'record_datetime', 'Tanggal & waktu');
        $this->assertPeriodOpenForWrite('weighbridge', $station->business_unit_id, $attributes['record_datetime'] ?? null);

        $attributes['station_id'] = $station->id;
        // Snapshot the line from the RESOLVED STATION, never from the
        // request: the client sends `production_line_id` only to SELECT the
        // station, and trusting it back would let a record store a line
        // different from its own station's — the class of bug the mill-scope
        // guard above just closed. Stored (not derived at read time) so that
        // moving this station to another line later cannot rewrite this
        // record's history. See 2026_09_28_000041.
        $attributes['production_line_id'] = $station->production_line_id;
        $attributes['status'] = 'saved';
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = WeighbridgeRecord::create($attributes);
        $record->load(['station.businessUnit', 'productionLine', 'checkedBy', 'acknowledgedBy']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-022--form-weighbridge-web business_logic steps
     * 5-7: validate id exists (404 if not) → validate required fields →
     * apply role-gated checked/acknowledged → update. business_unit_id/
     * station_id are never accepted here.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = WeighbridgeRecord::findOrFail($id);

        $this->assertRecordWritableByActor($record, $actor);

        $attributes = $this->normalizeFormFields($data);

        $this->validateForm($attributes);

        // KUNCI PERIODE (usecase-141) — DUA tanggal diperiksa, bukan satu.
        // Mengubah tanggal sebuah record berarti mengeluarkannya dari periode
        // lama dan memasukkannya ke periode baru, dan mengeluarkan satu baris
        // dari periode yang sudah ditutup menggeser angka laporannya sama
        // nyatanya dengan menambah baris ke dalamnya. Jadi kedua ujung
        // perpindahan harus berada di periode yang terbuka. Verifikasi
        // (checked/acknowledged) lewat jalur ini ikut terkunci, sesuai spec.
        $record->loadMissing('station');
        $periodLockMillId = $record->station->business_unit_id;

        $this->assertPeriodOpenForWrite('weighbridge', $periodLockMillId, optional($record->record_datetime)->toDateString());
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['record_datetime'] ?? null, 'record_datetime', 'Tanggal & waktu');
        $this->assertPeriodOpenForWrite('weighbridge', $periodLockMillId, $attributes['record_datetime'] ?? null);

        $this->applyVerification($attributes, $data, $actor);

        $record->update($attributes);
        $record->load(['station.businessUnit', 'productionLine', 'checkedBy', 'acknowledgedBy']);

        return $this->toDetailRow($record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFormFields(array $data): array
    {
        $attributes = [];

        foreach (self::FORM_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        // ZONA WAKTU: input ber-offset (mis. "...Z" dari mobile) dikonversi
        // ke jam WIB sebelum disimpan — Eloquent tidak mengonversinya.
        // Lihat App\Support\AppTime.
        $attributes['record_datetime'] = AppTime::normalizeClientDateTime($attributes['record_datetime']);

        // EMPTY STRING MUST BECOME NULL ON THE NULLABLE NUMERIC FIELDS, and
        // validation will NOT do it for us. `['nullable', 'numeric']` lets ''
        // through untouched — verified: Validator::make(['quantity' => ''],
        // ['quantity' => ['nullable', 'numeric']]) PASSES, because `nullable`
        // makes Laravel skip the other rules for an empty value. So without
        // this, '' reaches the INSERT.
        //
        // WHY THAT WAS INVISIBLE FOR SO LONG. PostgreSQL (dev and production)
        // refuses it outright — `quantity` and `tare_weight` are
        // `double precision`:
        //
        //   SQLSTATE[22P02] invalid input syntax for type double precision: ""
        //
        // while SQLite — which phpunit.xml forces for the whole suite — accepts
        // '' into a numeric column without a murmur. The web form binds every
        // untouched field to '' (App\Livewire\Data\FormWeighbridge::$form), so
        // EVERY save with the optional Netto/Quantity left blank returned a 500
        // in dev and production while the entire test suite stayed green. The
        // same shape as the ILIKE and the bare-where traps: green where it is
        // cheap to be green, broken where it matters.
        //
        // gross_weight is deliberately NOT in this list: it is `required`, and
        // `required` already rejects '' with the right message. Normalising it
        // would only swap one rejection for an identical one.
        foreach (['tare_weight', 'quantity'] as $numeric) {
            if (is_string($attributes[$numeric]) && trim($attributes[$numeric]) === '') {
                $attributes[$numeric] = null;
            }
        }

        if ($attributes['weighbridge_type'] !== 'dispatch') {
            $attributes['destination'] = null;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'wb_card_number' => ['required', 'string'],
            'weighbridge_type' => ['required', 'in:receive,dispatch'],
            'record_datetime' => ['required', 'date'],
            'vehicle_number' => ['required', 'string'],
            'driver_name' => ['required', 'string'],
            'estate_supplier' => ['required', 'string'],
            'destination' => [$attributes['weighbridge_type'] === 'dispatch' ? 'required' : 'nullable', 'string'],
            'division' => ['nullable', 'string'],
            'block' => ['nullable', 'string'],
            'gross_weight' => ['required', 'numeric'],
            'tare_weight' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'numeric'],
        ], [
            'wb_card_number.required' => 'WB Card Number wajib diisi.',
            'weighbridge_type.required' => 'Tipe Weighbridge wajib dipilih.',
            'record_datetime.required' => 'Tanggal & waktu wajib diisi.',
            'vehicle_number.required' => 'No. Kendaraan wajib diisi.',
            'driver_name.required' => 'Nama Supir wajib diisi.',
            'estate_supplier.required' => 'Estate/Supplier wajib diisi.',
            'destination.required' => 'Tujuan Muatan wajib diisi untuk tipe Dispatch.',
            'gross_weight.required' => 'Gross Weight wajib diisi.',
        ])->validate();
    }

    /**
     * Applies the role-gated checked/acknowledged self-attestation
     * checkboxes onto $attributes (by reference) — `checked=true` sets
     * checked_by to $actor->id ONLY when $actor is a Supervisor;
     * `acknowledged=true` sets acknowledged_by to $actor->id ONLY when
     * $actor is Mill Management. Any other role sending these booleans
     * is silently ignored (not an error — the fields simply are not
     * rendered for that role in the FE, per component_patterns 'form').
     * Unchecked ($false or absent) clears the corresponding *_by column.
     *
     * @param  array<string, mixed>  $attributes  by reference
     * @param  array<string, mixed>  $data  raw request payload
     */
    protected function applyVerification(array &$attributes, array $data, User $actor): void
    {
        if ($actor->role === UserRole::Supervisor) {
            $attributes['checked_by'] = ! empty($data['checked']) ? $actor->id : null;
        }

        if ($actor->role === UserRole::MillManagement) {
            $attributes['acknowledged_by'] = ! empty($data['acknowledged']) ? $actor->id : null;
        }
    }

    /**
     * getDetail() — screen-019--detail-weighbridge-web business_logic
     * steps 1-3: findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/checked_by/
     * acknowledged_by to display names, mirroring
     * DataPreviewWeighbridgeView.vue (mobile)'s field set/order so the
     * web detail screen presents the exact same record shape.
     */
    public function getDetail(string $id): array
    {
        // Cakupan mill diterapkan sebagai SCOPE QUERY, bukan cek 403
        // setelah row diambil: UUID milik mill lain jadi tidak ada sama
        // sekali, sehingga findOrFail() melempar ModelNotFoundException
        // yang semua pemanggil sudah tangani (API -> 404 NOT_FOUND, layar
        // Detail/Form Livewire -> state $notFound). Sampai 2026-09-28
        // jalur ini memuat record mill lain secara utuh bila UUID-nya
        // diketahui, dan 403 baru muncul saat save.
        $record = $this->scopeQueryToActorMill(
            WeighbridgeRecord::with(['station.businessUnit', 'productionLine', 'checkedBy', 'acknowledgedBy'])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a WeighbridgeRecord to the detail endpoint's success_schema —
     * every field, plus station_name/checked_by_name/acknowledged_by_name
     * resolved via the relations eager-loaded in getDetail() (raw uuids
     * are not useful on a human-facing read-only detail screen).
     */
    protected function toDetailRow(WeighbridgeRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            // Ditambahkan 2026-10-04 (additif): Form Weighbridge web mode edit
            // menampilkan Business Unit dan Production Line record ini. Line
            // dibaca dari KOLOM RECORD (snapshot saat create), mill dari
            // stasiunnya — record tidak menyimpan business_unit_id sendiri.
            'business_unit_name' => $record->station?->businessUnit?->name,
            'production_line_id' => $record->production_line_id,
            'production_line_name' => $record->productionLine?->name,
            'wb_card_number' => $record->wb_card_number,
            'weighbridge_type' => $record->weighbridge_type,
            'record_datetime' => optional($record->record_datetime)->toIso8601String(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'estate_supplier' => $record->estate_supplier,
            'destination' => $record->destination,
            'division' => $record->division,
            'block' => $record->block,
            'gross_weight' => $record->gross_weight,
            'tare_weight' => $record->tare_weight,
            'net_weight' => $record->net_weight,
            'quantity' => $record->quantity,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Maps a WeighbridgeRecord to the list endpoint's success_schema row
     * shape: { id, wb_card_number, weighbridge_type, record_datetime,
     * vehicle_number, driver_name, destination, net_weight, status }.
     */
    protected function toListRow(WeighbridgeRecord $record): array
    {
        return [
            'id' => $record->id,
            'wb_card_number' => $record->wb_card_number,
            'weighbridge_type' => $record->weighbridge_type,
            'record_datetime' => optional($record->record_datetime)->toIso8601String(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'destination' => $record->destination,
            'net_weight' => $record->net_weight,
            'production_line_name' => $record->productionLine?->name,
            'status' => $record->status?->value,
        ];
    }
}
