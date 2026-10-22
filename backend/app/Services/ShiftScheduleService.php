<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\NotACandidateException;
use App\Exceptions\ShiftClosedException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ShiftScheduleService — screen-171--jadwal-shift / usecase-177--jadwal-shift
 * (Jadwal Shift: mengisi SEL shift × tanggal dengan orang, per Production
 * Line, per bulan).
 *
 * Dipakai BERSAMA oleh controller API (App\Http\Controllers\Api\
 * ShiftScheduleController) dan komponen Livewire (App\Livewire\MasterData\
 * JadwalShift), sama seperti ShiftService di screen-170. SEMUA aturan hidup
 * di sini — gate peran, lingkup mill, kelayakan calon, penutupan shift,
 * penimpaan, salin bulan — dan tidak ada salinannya di controller maupun
 * komponen, sehingga halaman dan API tidak mungkin berbeda hasil.
 *
 * ── DUA PEMBACAAN ROLE YANG SENGAJA BERBEDA ─────────────────────────────
 *
 * 1. GATE AKSES memakai `$user->role` — role EFEKTIF. Cast
 *    App\Casts\EffectiveUserRole memetakan tujuh role setara Admin
 *    (engineer, shift_supervisor, maintenance, mekanik, logistics, qa_qc,
 *    mill_admin) menjadi Admin, sehingga mereka lolos sebagai admin dan
 *    tidak terikat satu mill.
 * 2. KELAYAKAN CALON PESERTA memakai role yang DIPILIH
 *    (`getRawOriginal('role')` / kolom mentah di SQL), BUKAN `$user->role`.
 *    Seorang 'engineer' efektifnya Admin, tetapi ia bukan Operator atau
 *    Supervisor lapangan, jadi bukan calon. Membaca role efektif di sini
 *    akan diam-diam meloloskan atau menolak orang yang salah.
 *
 * ── PENUGASAN MELEKAT PADA TANGGAL MULAI SHIFT ───────────────────────────
 *
 * `work_date` shift lintas tengah malam adalah tanggal ia MULAI; tidak ada
 * logika memecah ke dua tanggal. Penugasan INFORMASIONAL — tidak memengaruhi
 * hak input record dan tidak menandai shift_id pada record.
 *
 * ── SHIFT TERTUTUP ───────────────────────────────────────────────────────
 *
 * Jalur HIDUP berangkat dari Shift::query() (global scope SoftDeletes
 * menyingkirkan baris tertutup). Baris tertutup SENGAJA dijangkau lewat
 * withTrashed()/onlyTrashed(): grid memuat kolomnya read-only untuk tanggal
 * sebelum `closed_from`, dan setiap tulis atas shift itu ditolak
 * 422 SHIFT_CLOSED. `closed_from` = tanggal `deleted_at` dalam zona waktu
 * aplikasi (config app.timezone), bukan tanggal mentah dari basis data.
 *
 * ── SQLITE vs POSTGRESQL (suite SQLite, produksi PostgreSQL) ─────────────
 *
 *  - work_date dibandingkan sebagai string 'YYYY-MM-DD' lewat whereBetween /
 *    whereIn, tidak pernah Carbon/where() biasa pada kolom date.
 *  - Pencarian nama memakai lower(name) LIKE lower(?) — bukan ILIKE.
 *  - Daftar kosong TIDAK PERNAH menjadi whereIn([]) / whereNotIn([]):
 *    cabang kosong ditangani eksplisit di PHP.
 *  - Satu kueri penugasan per bulan, satu kueri shift, satu kueri user;
 *    pengelompokan di PHP — tidak ada N+1 per sel.
 *
 * ── GALAT ────────────────────────────────────────────────────────────────
 *
 * Memakai kelas yang sama dengan ShiftService/UserService supaya
 * ApiExceptionHandler merendernya tanpa kode baru: abort(403) → FORBIDDEN,
 * ModelNotFoundException → NOT_FOUND, ValidationException →
 * VALIDATION_ERROR, ditambah tiga HttpException ber-HasErrorCode
 * (ShiftClosedException, NotACandidateException,
 * ConfirmationRequiredException).
 */
class ShiftScheduleService
{
    /** Role TERPILIH yang boleh ditugaskan ke sel (bukan role efektif). */
    public const CANDIDATE_ROLES = ['operator', 'supervisor'];

    /** Label hari Senin(1)..Minggu(7), sama dengan kolom weekday_label. */
    public const WEEKDAY_LABELS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /** Batas rentang apply (hari) — melindungi dari pengulangan tak wajar. */
    protected const MAX_RANGE_DAYS = 1830;

    // ───────────────────────────────────────────────────────────────────
    // Pemilih (options)
    // ───────────────────────────────────────────────────────────────────

    /**
     * businessUnitOptions() — GET /api/shift-schedules/business-units/options.
     * Admin (dan role setara): semua mill. Supervisor / Mill Management:
     * hanya mill miliknya, dan `fixed=true` supaya UI menyembunyikan pemilih.
     *
     * @return array{data: list<array{id: string, name: string}>, fixed: bool}
     */
    public function businessUnitOptions(): array
    {
        $user = $this->guard();
        $fixed = ! $this->isUnrestricted($user);

        $query = BusinessUnit::query()->orderBy('name');

        if ($fixed) {
            $buId = (string) $user->business_unit_id;

            if (! Str::isUuid($buId)) {
                return ['data' => [], 'fixed' => true];
            }

            $query->whereKey($buId);
        }

        return [
            'data' => $query->get(['id', 'name'])
                ->map(fn (BusinessUnit $businessUnit) => [
                    'id' => $businessUnit->id,
                    'name' => $businessUnit->name,
                ])
                ->all(),
            'fixed' => $fixed,
        ];
    }

    /**
     * productionLineOptions() — GET /api/shift-schedules/production-lines/options.
     * Line milik SATU mill; business_unit_id WAJIB (422 bila absen).
     * `live_shift_count` memberi tahu line yang belum punya shift sebelum
     * dipilih — dihitung dengan SATU kueri teragregasi.
     *
     * @return array{data: list<array{id: string, name: string, live_shift_count: int}>}
     *
     * @throws ValidationException
     */
    public function productionLineOptions(?string $businessUnitId): array
    {
        $user = $this->guard();

        $businessUnitId = $this->emptyToNull($businessUnitId);

        if ($businessUnitId === null) {
            throw ValidationException::withMessages([
                'business_unit_id' => 'Mill wajib dipilih terlebih dahulu.',
            ]);
        }

        $this->assertMillAllowed($user, (string) $businessUnitId);

        if (! Str::isUuid((string) $businessUnitId)) {
            return ['data' => []];
        }

        $lines = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $counts = $lines->isEmpty()
            ? collect()
            : Shift::query()
                ->whereIn('production_line_id', $lines->pluck('id')->all())
                ->selectRaw('production_line_id, count(*) as aggregate')
                ->groupBy('production_line_id')
                ->pluck('aggregate', 'production_line_id');

        return [
            'data' => $lines->map(fn (ProductionLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'live_shift_count' => (int) ($counts[$line->id] ?? 0),
            ])->all(),
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Grid satu bulan
    // ───────────────────────────────────────────────────────────────────

    /**
     * grid() — GET /api/shift-schedules. Grid satu bulan untuk SATU line:
     * kolom shift, baris tanggal, isi sel, dan ringkasan — seluruhnya
     * dihitung SERVER.
     *
     * production_line_id WAJIB; tidak ada mode 'semua line' (absen / 'all' /
     * bukan uuid → 422). Line tanpa shift hidup → state 'no_live_shift'
     * dengan shifts/cells kosong dan 200, bukan galat.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException|ModelNotFoundException
     */
    public function grid(?string $productionLineId, ?string $month = null): array
    {
        $user = $this->guard();

        $lineId = $this->requireLineId($productionLineId);
        $monthStart = $this->resolveMonth($month);
        $line = $this->resolveLine($user, $lineId);

        $days = $this->daysOf($monthStart);
        $firstDate = $days[0]['date'];
        $lastDate = $days[count($days) - 1]['date'];

        $shifts = $this->gridShifts($line, $firstDate);
        $liveCount = $shifts->where('closed', false)->count();

        $base = [
            'production_line' => [
                'id' => $line->id,
                'name' => $line->name,
                'business_unit_id' => $line->business_unit_id,
            ],
            'month' => $monthStart->format('Y-m'),
            'days' => $days,
        ];

        if ($liveCount === 0) {
            return $base + [
                'shifts' => [],
                'cells' => [],
                'summary' => $this->summary(0, 0),
                'state' => 'no_live_shift',
            ];
        }

        // SATU kueri penugasan untuk seluruh bulan; pengelompokan di PHP.
        $rows = $this->assignmentRows($shifts->pluck('id')->all(), $firstDate, $lastDate);

        $byCell = [];
        $shiftsOnDay = [];
        foreach ($rows as $row) {
            $byCell[$row['shift_id']][$row['date']][] = $row;
            $shiftsOnDay[$row['date']][$row['user_id']][$row['shift_id']] = true;
        }

        $cells = [];
        $total = 0;
        $filled = 0;

        foreach ($shifts as $shift) {
            foreach ($days as $day) {
                $date = $day['date'];

                // Sel pada/setelah tanggal penutupan tidak dikirim sama sekali.
                if ($shift['closed'] && $date >= $shift['closed_from']) {
                    continue;
                }

                $assignees = [];
                foreach ($byCell[$shift['id']][$date] ?? [] as $row) {
                    $assignees[] = [
                        'user_id' => $row['user_id'],
                        'name' => $row['name'],
                        'role_label' => $row['role_label'],
                        'is_active' => $row['is_active'],
                        'also_on_other_shift_today' => count($shiftsOnDay[$date][$row['user_id']] ?? []) >= 2,
                    ];
                }

                $total++;
                if ($assignees !== []) {
                    $filled++;
                }

                $cells[] = [
                    'shift_id' => $shift['id'],
                    'date' => $date,
                    'editable' => ! $shift['closed'],
                    'assignees' => $assignees,
                ];
            }
        }

        return $base + [
            'shifts' => $shifts->values()->all(),
            'cells' => $cells,
            'summary' => $this->summary($total, $filled),
            'state' => 'ready',
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Calon peserta
    // ───────────────────────────────────────────────────────────────────

    /**
     * candidates() — GET /api/shift-schedules/candidates. User AKTIF berperan
     * Operator atau Supervisor (role yang DIPILIH) pada mill dari line itu.
     * `q`: lower(name) LIKE (bukan ILIKE). `date`: tiap calon menyertakan
     * shift lain pada line itu di tanggal tersebut (penanda informasi).
     *
     * @return array{data: list<array<string, mixed>>, empty_reason: ?string}
     *
     * @throws ValidationException|ModelNotFoundException
     */
    public function candidates(?string $productionLineId, ?string $q = null, ?string $date = null): array
    {
        $user = $this->guard();

        $lineId = $this->requireLineId($productionLineId);

        $date = $this->emptyToNull($date);
        if ($date !== null && ! $this->isValidDate((string) $date)) {
            throw ValidationException::withMessages([
                'date' => 'Tanggal harus berformat YYYY-MM-DD.',
            ]);
        }

        $line = $this->resolveLine($user, $lineId);
        $q = $this->emptyToNull($q !== null ? trim($q) : null);

        $users = $this->candidateQuery((string) $line->business_unit_id, $q)->get();

        $onShifts = [];
        if ($date !== null && $users->isNotEmpty()) {
            $assigned = ShiftAssignment::query()
                ->join('shifts', 'shifts.id', '=', 'shift_assignments.shift_id')
                ->where('shifts.production_line_id', $line->id)
                // join() mentah melewati scope soft-delete: shift yang sudah ditutup bukan
                // "shift lain" tempat orang itu bisa bertugas, jadi dikecualikan eksplisit.
                ->whereNull('shifts.deleted_at')
                ->whereIn('shift_assignments.user_id', $users->pluck('id')->all())
                ->whereBetween('shift_assignments.work_date', [(string) $date, (string) $date])
                ->orderBy('shifts.name')
                ->get(['shift_assignments.user_id', 'shift_assignments.shift_id', 'shifts.name as shift_name']);

            foreach ($assigned as $row) {
                $onShifts[$row->user_id][] = [
                    'shift_id' => $row->shift_id,
                    'shift_name' => $row->shift_name,
                ];
            }
        }

        $data = $users->map(fn (User $candidate) => [
            'user_id' => $candidate->id,
            'name' => $candidate->name,
            'role_label' => $this->chosenRoleLabel($candidate),
            'already_on_shifts' => $onShifts[$candidate->id] ?? [],
        ])->all();

        $emptyReason = null;
        if ($data === []) {
            // Pencarian yang kosong tidak berarti mill tanpa calon.
            $emptyReason = ($q === null || ! $this->candidateQuery((string) $line->business_unit_id, null)->exists())
                ? 'no_candidates'
                : null;
        }

        return ['data' => $data, 'empty_reason' => $emptyReason];
    }

    // ───────────────────────────────────────────────────────────────────
    // Isi satu sel
    // ───────────────────────────────────────────────────────────────────

    /**
     * saveCell() — PUT /api/shift-schedules/cell. MENGGANTI isi SATU sel
     * dengan daftar user yang dikirim; daftar kosong = mengosongkan sel.
     *
     * Duplikat dilebur. SETIAP user BARU divalidasi sebagai calon, dan satu
     * saja yang gagal menolak SELURUH penyimpanan (semua-atau-tidak-sama-
     * sekali). User yang SUDAH ada di sel tidak divalidasi ulang — ia boleh
     * tetap ada walau kini nonaktif, dan mengeluarkannya selalu diizinkan.
     * Last-write-wins; `loaded_user_ids` hanya untuk melaporkan
     * changed_by_others.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException|ModelNotFoundException|ShiftClosedException|NotACandidateException
     */
    public function saveCell(array $data): array
    {
        $user = $this->guard();

        $valid = Validator::make($data, [
            'shift_id' => ['required', 'string', 'uuid'],
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['string', 'uuid'],
            'loaded_user_ids' => ['nullable', 'array'],
            'loaded_user_ids.*' => ['string'],
        ], $this->messages())->validate();

        if (! $this->isValidDate($valid['date'])) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid.']);
        }

        $shift = $this->resolveWritableShift($user, $valid['shift_id']);
        $buId = (string) $shift->productionLine->business_unit_id;

        $userIds = array_values(array_unique($valid['user_ids']));
        $loaded = array_key_exists('loaded_user_ids', $valid) && $valid['loaded_user_ids'] !== null
            ? array_values(array_unique($valid['loaded_user_ids']))
            : null;

        DB::transaction(function () use ($shift, $valid, $userIds, $loaded, $buId, $user, &$changedByOthers, &$previous) {
            $current = ShiftAssignment::query()
                ->where('shift_id', $shift->id)
                ->whereBetween('work_date', [$valid['date'], $valid['date']])
                ->pluck('user_id')
                ->all();

            $changedByOthers = $loaded !== null && $this->differs($loaded, $current);
            $previous = $changedByOthers ? array_values($current) : [];

            // Hanya user BARU yang divalidasi sebagai calon.
            $this->assertCandidates(array_values(array_diff($userIds, $current)), $buId);

            $this->writeCell($shift->id, $valid['date'], $userIds, $current, (string) $user->id);
        });

        return [
            'shift_id' => $shift->id,
            'date' => $valid['date'],
            'assignees' => $this->cellAssignees($shift->id, $valid['date']),
            'changed_by_others' => (bool) $changedByOthers,
            'previous_user_ids' => $previous,
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Terapkan ke banyak tanggal
    // ───────────────────────────────────────────────────────────────────

    /**
     * apply() — POST /api/shift-schedules/apply. Menerapkan satu daftar user
     * ke banyak tanggal pada SATU shift.
     *
     * Tepat satu dari `dates` atau `range`. Hanya tanggal di dalam `month`
     * yang dipakai; sisanya dihitung ke outside_month_ignored.
     * dry_run=true hanya menghitung (NOL tulis). Menimpa sel yang sudah
     * berisi tanpa confirm_overwrite=true → 409 CONFIRMATION_REQUIRED.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException|ModelNotFoundException|ShiftClosedException|NotACandidateException|ConfirmationRequiredException
     */
    public function apply(array $data): array
    {
        $user = $this->guard();

        $valid = Validator::make($data, [
            'shift_id' => ['required', 'string', 'uuid'],
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['string', 'date_format:Y-m-d'],
            'range' => ['nullable', 'array'],
            'range.from' => ['required_with:range', 'string', 'date_format:Y-m-d'],
            'range.to' => ['required_with:range', 'string', 'date_format:Y-m-d'],
            'range.weekdays' => ['nullable', 'array'],
            'range.weekdays.*' => ['integer', 'between:1,7'],
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['string', 'uuid'],
            'dry_run' => ['nullable', 'boolean'],
            'confirm_overwrite' => ['nullable', 'boolean'],
        ], $this->messages())->validate();

        $dryRun = filter_var($valid['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $confirm = filter_var($valid['confirm_overwrite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $hasDates = ! empty($valid['dates']);
        $hasRange = ! empty($valid['range']);

        // dates XOR range — keduanya atau tak satu pun → 422.
        if ($hasDates === $hasRange) {
            throw ValidationException::withMessages([
                'dates' => 'Isi tepat satu: daftar tanggal ATAU rentang tanggal.',
            ]);
        }

        $shift = $this->resolveWritableShift($user, $valid['shift_id']);
        $buId = (string) $shift->productionLine->business_unit_id;
        $monthStart = $this->resolveMonth($valid['month']);

        $requested = $hasDates
            ? array_values(array_unique($valid['dates']))
            : $this->expandRange($valid['range']);

        foreach ($requested as $date) {
            if (! $this->isValidDate($date)) {
                throw ValidationException::withMessages(['dates' => 'Tanggal '.$date.' tidak valid.']);
            }
        }

        $prefix = $monthStart->format('Y-m').'-';
        $targets = array_values(array_filter($requested, fn (string $d) => str_starts_with($d, $prefix)));
        sort($targets);
        $outside = count($requested) - count($targets);

        $userIds = array_values(array_unique($valid['user_ids']));
        $this->assertCandidates($userIds, $buId);

        $plan = $this->buildApplyPlan($shift->id, $targets, $userIds);

        $result = [
            'target_cells' => count($targets),
            'cells_already_filled' => $plan['already_filled'],
            'people_affected' => $plan['people_affected'],
            'outside_month_ignored' => $outside,
            'applied' => false,
            'written_cells' => 0,
        ];

        if ($dryRun) {
            return $result;
        }

        // Menimpa sel berisi (termasuk mengosongkannya) wajib dikonfirmasi.
        if ($plan['already_filled'] > 0 && ! $confirm) {
            throw new ConfirmationRequiredException(
                $plan['already_filled'].' sel sudah berisi dan akan ditimpa. Konfirmasi penimpaan untuk melanjutkan.'
            );
        }

        $written = 0;
        DB::transaction(function () use ($shift, $targets, $userIds, $user, &$written) {
            // Dibaca ULANG di dalam transaksi: rencana di atas hanya untuk
            // hitungan dan konfirmasi.
            $existing = $this->cellsOf($shift->id, $targets);

            foreach ($targets as $date) {
                $current = $existing[$date] ?? [];

                if ($this->differs($userIds, array_keys($current))) {
                    $written++;
                }

                $this->writeCell($shift->id, $date, $userIds, array_keys($current), (string) $user->id);
            }
        });

        $result['applied'] = true;
        $result['written_cells'] = $written;

        return $result;
    }

    // ───────────────────────────────────────────────────────────────────
    // Salin dari bulan sebelumnya
    // ───────────────────────────────────────────────────────────────────

    /**
     * copyPrevious() — POST /api/shift-schedules/copy-previous. Menyalin pola
     * bulan kalender sebelumnya (tanggal ke tanggal) ke bulan yang dibuka
     * untuk SATU line. Sel tujuan yang SUDAH berisi DIPERTAHANKAN (tidak
     * ditimpa), jadi tidak ada konfirmasi penimpaan.
     *
     * Satuan hitungan: filled dan kept dalam SEL tujuan; skipped.* dalam
     * entri penugasan sumber (satu orang pada satu sel) yang dilewati.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException|ModelNotFoundException
     */
    public function copyPrevious(array $data): array
    {
        $user = $this->guard();

        $valid = Validator::make($data, [
            'production_line_id' => ['required', 'string', 'uuid'],
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'dry_run' => ['nullable', 'boolean'],
        ], $this->messages())->validate();

        $dryRun = filter_var($valid['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $line = $this->resolveLine($user, $valid['production_line_id']);
        $target = $this->resolveMonth($valid['month']);
        $source = $target->copy()->subMonthNoOverflow();

        $result = [
            'source_month' => $source->format('Y-m'),
            'filled' => 0,
            'kept' => 0,
            'skipped' => ['closed_shift' => 0, 'inactive_or_not_candidate' => 0, 'missing_day' => 0],
            'nothing_to_copy' => false,
            'applied' => false,
        ];

        // Seluruh penugasan sumber untuk line ini — shift tertutup ikut
        // (join, bukan Eloquent, supaya global scope tidak menyingkirkannya).
        $sourceRows = ShiftAssignment::query()
            ->join('shifts', 'shifts.id', '=', 'shift_assignments.shift_id')
            ->where('shifts.production_line_id', $line->id)
            ->whereBetween('shift_assignments.work_date', [
                $source->copy()->startOfMonth()->toDateString(),
                $source->copy()->endOfMonth()->toDateString(),
            ])
            ->get([
                'shift_assignments.shift_id',
                'shift_assignments.user_id',
                'shift_assignments.work_date',
                'shifts.deleted_at as shift_deleted_at',
            ]);

        if ($sourceRows->isEmpty()) {
            $result['nothing_to_copy'] = true;
            $result['applied'] = ! $dryRun;

            return $result;
        }

        $buId = (string) $line->business_unit_id;
        $userIds = $sourceRows->pluck('user_id')->unique()->values()->all();
        $okUsers = $this->candidateQuery($buId, null)->whereIn('id', $userIds)->pluck('id')->flip();

        $targetDays = $target->daysInMonth;
        $targetPrefix = $target->format('Y-m').'-';
        $liveIds = Shift::query()->where('production_line_id', $line->id)->pluck('id')->flip();

        // Isi sel tujuan SEBELUM menulis: dasar untuk "kept".
        $targetFrom = $target->copy()->startOfMonth()->toDateString();
        $targetTo = $target->copy()->endOfMonth()->toDateString();
        $occupied = [];
        if ($liveIds->isNotEmpty()) {
            $existing = ShiftAssignment::query()
                ->whereIn('shift_id', $liveIds->keys()->all())
                ->whereBetween('work_date', [$targetFrom, $targetTo])
                ->get(['shift_id', 'work_date']);

            foreach ($existing as $row) {
                $occupied[$row->shift_id.'|'.substr((string) $row->work_date, 0, 10)] = true;
            }
        }

        $toInsert = [];   // "shiftId|date" => [userId, ...]
        $keptCells = [];

        foreach ($sourceRows as $row) {
            $day = (int) substr((string) $row->work_date, 8, 2);

            if ($day > $targetDays) {
                $result['skipped']['missing_day']++;

                continue;
            }

            if ($row->shift_deleted_at !== null || ! $liveIds->has($row->shift_id)) {
                $result['skipped']['closed_shift']++;

                continue;
            }

            if (! $okUsers->has($row->user_id)) {
                $result['skipped']['inactive_or_not_candidate']++;

                continue;
            }

            $key = $row->shift_id.'|'.$targetPrefix.sprintf('%02d', $day);

            if (isset($occupied[$key])) {
                $keptCells[$key] = true;

                continue;
            }

            $toInsert[$key][$row->user_id] = true;
        }

        $result['kept'] = count($keptCells);
        $result['filled'] = count($toInsert);

        if ($dryRun) {
            return $result;
        }

        DB::transaction(function () use ($toInsert, $user) {
            $batch = [];
            foreach ($toInsert as $key => $users) {
                [$shiftId, $date] = explode('|', $key);
                foreach (array_keys($users) as $userId) {
                    $batch[] = $this->newRow($shiftId, $userId, $date, (string) $user->id);
                }
            }

            foreach (array_chunk($batch, 200) as $chunk) {
                ShiftAssignment::query()->insertOrIgnore($chunk);
            }
        });

        $result['applied'] = true;

        return $result;
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal — akses
    // ───────────────────────────────────────────────────────────────────

    /**
     * LAPIS KETIGA gate peran. Dibaca dari `$user->role` (role EFEKTIF),
     * sehingga tujuh role setara Admin lolos sebagai admin. Operator →
     * abort(403) → FORBIDDEN, dan SEBELUM kueri apa pun — nol kueri
     * shift_assignments.
     */
    protected function guard(): User
    {
        $user = auth()->user();

        $role = $user === null
            ? null
            : ($user->role instanceof UserRole ? $user->role->value : (string) $user->role);

        abort_unless(
            in_array($role, [UserRole::Admin->value, UserRole::Supervisor->value, UserRole::MillManagement->value], true),
            403,
            'Anda tidak memiliki akses untuk aksi ini.'
        );

        return $user;
    }

    /** Admin (dan role setara, lewat role efektif) tidak terikat satu mill. */
    protected function isUnrestricted(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return $role === UserRole::Admin->value;
    }

    /** Supervisor / Mill Management hanya mill sendiri → selain itu 403. */
    protected function assertMillAllowed(User $user, string $businessUnitId): void
    {
        if ($this->isUnrestricted($user)) {
            return;
        }

        abort_unless(
            $user->business_unit_id !== null && (string) $user->business_unit_id === $businessUnitId,
            403,
            'Anda tidak memiliki akses untuk aksi ini.'
        );
    }

    /**
     * @throws ValidationException
     */
    protected function requireLineId(?string $productionLineId): string
    {
        $productionLineId = $this->emptyToNull($productionLineId);

        if ($productionLineId === null || strtolower((string) $productionLineId) === 'all') {
            throw ValidationException::withMessages([
                'production_line_id' => 'Production Line wajib dipilih; jadwal tidak dapat ditampilkan untuk semua line sekaligus.',
            ]);
        }

        if (! Str::isUuid((string) $productionLineId)) {
            throw ValidationException::withMessages([
                'production_line_id' => 'Production Line tidak valid.',
            ]);
        }

        return (string) $productionLineId;
    }

    /**
     * @throws ModelNotFoundException
     */
    protected function resolveLine(User $user, string $lineId): ProductionLine
    {
        $line = ProductionLine::query()->findOrFail($lineId);

        $this->assertMillAllowed($user, (string) $line->business_unit_id);

        return $line;
    }

    /**
     * Shift untuk TULIS: withTrashed (agar shift tertutup dikenali, bukan
     * "tidak ada"), 404 bila tidak ada, 422 SHIFT_CLOSED bila ditutup,
     * 403 bila milik mill lain.
     *
     * @throws ModelNotFoundException|ShiftClosedException
     */
    protected function resolveWritableShift(User $user, string $shiftId): Shift
    {
        $shift = Shift::withTrashed()->with('productionLine')->findOrFail($shiftId);

        $this->assertMillAllowed($user, (string) $shift->productionLine->business_unit_id);

        if ($shift->deleted_at !== null) {
            throw new ShiftClosedException(
                'Shift "'.$shift->name.'" sudah dihapus (ditutup), sehingga jadwalnya tidak dapat diubah.'
            );
        }

        return $shift;
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal — grid
    // ───────────────────────────────────────────────────────────────────

    /**
     * Awal bulan dalam zona waktu aplikasi; default bulan berjalan.
     *
     * @throws ValidationException
     */
    protected function resolveMonth(?string $month): Carbon
    {
        $tz = config('app.timezone');
        $month = $this->emptyToNull($month);

        if ($month === null) {
            return Carbon::now($tz)->startOfMonth();
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
            throw ValidationException::withMessages([
                'month' => 'Bulan harus berformat YYYY-MM.',
            ]);
        }

        return Carbon::createFromFormat('!Y-m', (string) $month, $tz)->startOfMonth();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function daysOf(Carbon $monthStart): array
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $days = [];

        for ($day = $monthStart->copy(); $day->month === $monthStart->month; $day->addDay()) {
            $weekday = $day->dayOfWeekIso;
            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $weekday,
                'weekday_label' => self::WEEKDAY_LABELS[$weekday],
                'is_weekend' => $weekday >= 6,
                'is_today' => $day->toDateString() === $today,
            ];
        }

        return $days;
    }

    /**
     * Kolom grid: shift HIDUP (Shift::query()) ditambah shift TERTUTUP yang
     * `closed_from`-nya jatuh sesudah tanggal 1 bulan itu. Terurut pada
     * sumbu menit-ke-dalam-hari yang SAMA dengan Kelola Shift.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function gridShifts(ProductionLine $line, string $firstDate): Collection
    {
        $live = Shift::query()->where('production_line_id', $line->id)->get();

        $closed = Shift::onlyTrashed()
            ->where('production_line_id', $line->id)
            ->get()
            ->filter(fn (Shift $shift) => $this->closedFrom($shift) > $firstDate);

        return $live->concat($closed)
            ->sortBy(fn (Shift $shift) => [ShiftService::offsetOnAxis((string) $shift->start_time), $shift->name])
            ->values()
            ->map(function (Shift $shift, int $position) {
                $isClosed = $shift->deleted_at !== null;
                $start = ShiftService::displayTime((string) $shift->start_time);
                $end = ShiftService::displayTime((string) $shift->end_time);

                return [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'crosses_midnight' => ShiftService::minuteOfDay((string) $shift->end_time)
                        < ShiftService::minuteOfDay((string) $shift->start_time),
                    'label' => $shift->name.' '.$shift->time_label,
                    'position' => $position,
                    'closed' => $isClosed,
                    'closed_from' => $isClosed ? $this->closedFrom($shift) : null,
                ];
            });
    }

    /**
     * Tanggal penutupan = tanggal `deleted_at` DALAM ZONA WAKTU APLIKASI,
     * bukan tanggal mentah: shift yang ditutup 18:30 UTC adalah tanggal
     * berikutnya di Asia/Jakarta.
     */
    protected function closedFrom(Shift $shift): string
    {
        return $shift->deleted_at
            ->copy()
            ->setTimezone(config('app.timezone'))
            ->toDateString();
    }

    /**
     * SATU kueri: penugasan semua kolom grid pada rentang bulan, di-join
     * users untuk nama/is_active/role terpilih.
     *
     * @param  list<string>  $shiftIds
     * @return list<array<string, mixed>>
     */
    protected function assignmentRows(array $shiftIds, string $from, string $to): array
    {
        if ($shiftIds === []) {
            return [];
        }

        return ShiftAssignment::query()
            ->join('users', 'users.id', '=', 'shift_assignments.user_id')
            ->whereIn('shift_assignments.shift_id', $shiftIds)
            ->whereBetween('shift_assignments.work_date', [$from, $to])
            ->orderBy('users.name')
            ->orderBy('shift_assignments.user_id')
            ->get([
                'shift_assignments.shift_id',
                'shift_assignments.user_id',
                'shift_assignments.work_date',
                'users.name as user_name',
                'users.is_active as user_is_active',
                'users.role as user_role',
            ])
            ->map(fn ($row) => [
                'shift_id' => $row->shift_id,
                'user_id' => $row->user_id,
                'date' => substr((string) $row->work_date, 0, 10),
                'name' => $row->user_name,
                'is_active' => (bool) $row->user_is_active,
                // Role TERPILIH dari kolom mentah, bukan role efektif.
                'role_label' => UserRole::tryFrom((string) $row->user_role)?->label() ?? (string) $row->user_role,
            ])
            ->all();
    }

    /**
     * @return array{total_cells: int, filled_cells: int, empty_cells: int, text: string}
     */
    protected function summary(int $total, int $filled): array
    {
        return [
            'total_cells' => $total,
            'filled_cells' => $filled,
            'empty_cells' => $total - $filled,
            'text' => $filled.' dari '.$total.' sel sudah terisi',
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal — calon peserta
    // ───────────────────────────────────────────────────────────────────

    /**
     * Himpunan calon: aktif, mill sama, role TERPILIH operator/supervisor.
     * `whereIn('role', …)` bekerja pada kolom mentah (query builder tidak
     * melewati cast), jadi 'engineer' (efektif Admin) tidak ikut.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    protected function candidateQuery(string $businessUnitId, ?string $q)
    {
        $query = User::query()
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', true)
            ->whereIn('role', self::CANDIDATE_ROLES)
            ->orderBy('name')
            ->orderBy('id');

        if ($q !== null) {
            // lower()+LIKE (bukan ILIKE); % dan _ pada masukan di-escape.
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->whereRaw("lower(name) like ? escape '\\'", [$needle]);
        }

        return $query;
    }

    /**
     * Alasan sebuah user BUKAN calon, atau null bila ia calon. Satu-satunya
     * definisi kelayakan untuk jalur tulis (PUT cell, apply).
     */
    protected function notCandidateReason(User $candidate, string $businessUnitId): ?string
    {
        if (! $candidate->is_active) {
            return 'nonaktif';
        }

        $chosen = (string) $candidate->getRawOriginal('role');

        if (! in_array($chosen, self::CANDIDATE_ROLES, true)) {
            return 'role '.(UserRole::tryFrom($chosen)?->label() ?? $chosen).' bukan Operator atau Supervisor';
        }

        if ((string) $candidate->business_unit_id !== $businessUnitId) {
            return 'bertugas di mill lain';
        }

        return null;
    }

    /**
     * Semua-atau-tidak-sama-sekali: satu user yang gagal menolak seluruhnya.
     * Daftar kosong dilewati eksplisit (bukan whereIn([])).
     *
     * @param  list<string>  $userIds
     *
     * @throws NotACandidateException
     */
    protected function assertCandidates(array $userIds, string $businessUnitId): void
    {
        if ($userIds === []) {
            return;
        }

        $found = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $problems = [];

        foreach ($userIds as $userId) {
            $candidate = $found->get($userId);

            if ($candidate === null) {
                $problems[] = $userId.' (user tidak ditemukan)';

                continue;
            }

            $reason = $this->notCandidateReason($candidate, $businessUnitId);

            if ($reason !== null) {
                $problems[] = $candidate->name.' ('.$reason.')';
            }
        }

        if ($problems !== []) {
            throw new NotACandidateException(
                'Tidak dapat menugaskan: '.implode('; ', $problems).'. Tidak ada perubahan yang disimpan.'
            );
        }
    }

    protected function chosenRoleLabel(User $candidate): string
    {
        return $candidate->assignedRole()->label();
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal — tulis
    // ───────────────────────────────────────────────────────────────────

    /**
     * Mengganti isi satu sel dari `$current` menjadi `$wanted`: hapus yang
     * tidak lagi diinginkan, sisipkan yang belum ada. Cabang kosong
     * ditangani eksplisit; insertOrIgnore + UNIQUE membuat penaruhan ulang
     * idempoten.
     *
     * @param  list<string>  $wanted
     * @param  list<string>  $current
     */
    protected function writeCell(string $shiftId, string $date, array $wanted, array $current, string $actorId): void
    {
        $remove = array_values(array_diff($current, $wanted));
        $add = array_values(array_diff($wanted, $current));

        if ($remove !== []) {
            ShiftAssignment::query()
                ->where('shift_id', $shiftId)
                ->whereBetween('work_date', [$date, $date])
                ->whereIn('user_id', $remove)
                ->delete();
        }

        if ($add !== []) {
            ShiftAssignment::query()->insertOrIgnore(
                array_map(fn (string $userId) => $this->newRow($shiftId, $userId, $date, $actorId), $add)
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function newRow(string $shiftId, string $userId, string $date, string $actorId): array
    {
        $now = Carbon::now()->toDateTimeString();

        return [
            'id' => (string) Str::uuid7(),
            'shift_id' => $shiftId,
            'user_id' => $userId,
            'work_date' => $date,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Isi sel sesudah penulisan.
     *
     * @return list<array<string, mixed>>
     */
    protected function cellAssignees(string $shiftId, string $date): array
    {
        return ShiftAssignment::query()
            ->join('users', 'users.id', '=', 'shift_assignments.user_id')
            ->where('shift_assignments.shift_id', $shiftId)
            ->whereBetween('shift_assignments.work_date', [$date, $date])
            ->orderBy('users.name')
            ->get(['shift_assignments.user_id', 'users.name as user_name', 'users.is_active as user_is_active'])
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'name' => $row->user_name,
                'is_active' => (bool) $row->user_is_active,
            ])
            ->all();
    }

    /**
     * Isi sel-sel pada tanggal-tanggal tertentu, SATU kueri.
     *
     * @param  list<string>  $dates
     * @return array<string, array<string, true>>  date => [user_id => true]
     */
    protected function cellsOf(string $shiftId, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $out = [];
        $rows = ShiftAssignment::query()
            ->where('shift_id', $shiftId)
            ->whereIn('work_date', $dates)
            ->get(['user_id', 'work_date']);

        foreach ($rows as $row) {
            $out[substr((string) $row->work_date, 0, 10)][$row->user_id] = true;
        }

        return $out;
    }

    /**
     * Hitungan untuk apply (dry_run maupun nyata): sel sasaran yang sudah
     * berisi, dan jumlah orang yang akan tergeser dari sel-sel itu.
     *
     * @param  list<string>  $targets
     * @param  list<string>  $userIds
     * @return array{already_filled: int, people_affected: int}
     */
    protected function buildApplyPlan(string $shiftId, array $targets, array $userIds): array
    {
        $existing = $this->cellsOf($shiftId, $targets);
        $alreadyFilled = 0;
        $affected = 0;

        foreach ($targets as $date) {
            $current = array_keys($existing[$date] ?? []);

            if ($current === []) {
                continue;
            }

            $alreadyFilled++;
            $affected += count(array_diff($current, $userIds));
        }

        return ['already_filled' => $alreadyFilled, 'people_affected' => $affected];
    }

    /**
     * Rentang → daftar tanggal, disaring hari dalam seminggu (1..7).
     *
     * @param  array<string, mixed>  $range
     * @return list<string>
     *
     * @throws ValidationException
     */
    protected function expandRange(array $range): array
    {
        $tz = config('app.timezone');
        $from = Carbon::createFromFormat('!Y-m-d', $range['from'], $tz);
        $to = Carbon::createFromFormat('!Y-m-d', $range['to'], $tz);

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'range.to' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.',
            ]);
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'range.to' => 'Rentang tanggal terlalu panjang.',
            ]);
        }

        $weekdays = array_map('intval', $range['weekdays'] ?? []);
        $dates = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($weekdays === [] || in_array($day->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal — kecil
    // ───────────────────────────────────────────────────────────────────

    /** Dua daftar id berbeda sebagai HIMPUNAN (urutan dan duplikat diabaikan). */
    protected function differs(array $a, array $b): bool
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        sort($a);
        sort($b);

        return $a !== $b;
    }

    protected function isValidDate(string $date): bool
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return false;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'shift_id.required' => 'Shift wajib dipilih.',
            'shift_id.uuid' => 'Shift tidak valid.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'user_ids.present' => 'Daftar petugas wajib dikirim (boleh kosong).',
            'user_ids.array' => 'Daftar petugas harus berupa daftar.',
            'user_ids.*.uuid' => 'Petugas tidak valid.',
            'month.required' => 'Bulan wajib diisi.',
            'month.regex' => 'Bulan harus berformat YYYY-MM.',
            'dates.*.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'range.from.date_format' => 'Tanggal awal harus berformat YYYY-MM-DD.',
            'range.to.date_format' => 'Tanggal akhir harus berformat YYYY-MM-DD.',
            'range.from.required_with' => 'Tanggal awal rentang wajib diisi.',
            'range.to.required_with' => 'Tanggal akhir rentang wajib diisi.',
            'range.weekdays.*.between' => 'Hari dalam seminggu harus 1 sampai 7.',
            'production_line_id.required' => 'Production Line wajib dipilih.',
            'production_line_id.uuid' => 'Production Line tidak valid.',
        ];
    }

    protected function emptyToNull(mixed $value): mixed
    {
        return $value === null || $value === '' ? null : $value;
    }
}
