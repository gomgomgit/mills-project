<?php

namespace App\Livewire\MasterData;

use App\Enums\UserRole;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\NotACandidateException;
use App\Exceptions\ShiftClosedException;
use App\Models\User;
use App\Services\ShiftScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * JadwalShift — screen-171--jadwal-shift / usecase-177--jadwal-shift.
 * Layar web Livewire di /master-data/shift-schedules, nama rute
 * 'master-data.shift-schedules'. Admin (dan role setara Admin), Supervisor,
 * dan Mill Management; Operator → 403.
 *
 * Memakai ShiftScheduleService — service yang SAMA dengan
 * App\Http\Controllers\Api\ShiftScheduleController — sehingga halaman dan API
 * tidak mungkin berbeda aturan. KOMPONEN INI TIDAK MENYIMPAN SATU ATURAN
 * BISNIS PUN: ia memanggil service lalu memetakan galatnya ke teks. Tidak ada
 * cermin aturan kelayakan calon, penutupan shift, maupun penimpaan di sini.
 *
 * GATE PERAN TIGA LAPIS. routes/web.php menjaga rutenya dengan 'auth' +
 * 'role:admin,supervisor,mill_management'; mount() di bawah memanggil
 * abort_unless() SENDIRI sebagai lapis kedua (role EFEKTIF, sehingga tujuh
 * role setara Admin lolos sebagai admin); ShiftScheduleService menegakkannya
 * sekali lagi bersama lingkup mill sebagai lapis ketiga.
 *
 * MILL TETAP UNTUK SUPERVISOR / MILL MANAGEMENT. Service menerbitkan
 * options.fixed=true bagi mereka; pemilih mill disembunyikan dan
 * $businessUnitId selalu dipaksa ke mill akunnya di render(), termasuk bila
 * query string-nya diubah tangan.
 *
 * PRODUCTION LINE WAJIB DIPILIH. Tidak ada "semua line": selama
 * $productionLineId kosong render() tidak memanggil grid() sama sekali. Mengganti
 * mill mengosongkan line; line basi (mis. URL dari mill lain) dikembalikan ke
 * kosong TANPA galat — pengguna tidak melakukan kesalahan, mereka berpindah.
 *
 * KONTEKS DI QUERY STRING. Mill, line, dan bulan di-URL-bind supaya "kembali
 * dari layar lain" memulihkan konteks yang sama.
 *
 * TIDAK ADA FIELD YANG DINONAKTIFKAN ATAU READONLY. Kolom pencarian dan
 * semua input tanggal adalah <input> asli: tanggal dapat diketik manual
 * maupun dipilih lewat picker. Penolakan datang dari service saat simpan.
 */
#[Layout('master-data.shift-schedules')]
class JadwalShift extends Component
{
    /** Pemilih Mill — URL-bound. Dipaksa tetap bagi Supervisor/Mill Management. */
    #[Url]
    public string $businessUnitId = '';

    /** Production Line — WAJIB; '' = belum dipilih (tidak ada grid). */
    #[Url]
    public string $productionLineId = '';

    /** Bulan pandang 'YYYY-MM' (zona waktu aplikasi). */
    #[Url]
    public string $month = '';

    // ── Tampilan + kartu tanggal (revisi v2) ───────────────────────────
    /**
     * 'calendar' | 'table'. BUKAN data tersimpan: hanya pilihan tampilan
     * sesi ini. Diingat per peramban lewat Alpine+localStorage di blade
     * (dibungkus try/catch); TIDAK PERNAH disimpan di server.
     */
    public string $viewMode = 'calendar';

    /** Tanggal (YYYY-MM-DD) yang kartunya terbuka; null = kartu tertutup. */
    public ?string $openDate = null;

    /** Shift yang bagian pengisinya terbuka lebih dulu di kartu. */
    public ?string $openShiftId = null;

    public string $panelSearch = '';

    /**
     * Pilihan per shift pada kartu: shiftId => user_id[]. Dilacak per shift
     * supaya menyimpan satu shift tidak menyentuh shift lain.
     *
     * @var array<string, list<string>>
     */
    public array $daySelected = [];

    /**
     * Isi tiap sel saat kartu dibuka (atau setelah disimpan) — dikirim
     * sebagai loaded_user_ids. shiftId => user_id[].
     *
     * @var array<string, list<string>>
     */
    public array $dayLoaded = [];

    public ?string $panelError = null;

    public ?string $panelNotice = null;

    /** Shift yang menghasilkan panelError/panelNotice terakhir. */
    public ?string $feedbackShiftId = null;

    /** Shift yang menunggu konfirmasi "kosongkan sel berisi banyak orang". */
    public ?string $confirmClearShiftId = null;

    /** Tanggal/shift tujuan yang menunggu konfirmasi membuang perubahan. */
    public ?string $pendingDate = null;

    public ?string $pendingShiftId = null;

    // ── Terapkan ke beberapa tanggal ───────────────────────────────────
    public bool $applyOpen = false;

    public string $applyShiftId = '';

    /** 'range' | 'dates' */
    public string $applyMode = 'range';

    public string $applyFrom = '';

    public string $applyTo = '';

    /** @var list<int> */
    public array $applyWeekdays = [1, 2, 3, 4, 5, 6, 7];

    /** Tanggal yang sedang diketik/dipilih untuk ditambahkan (mode 'dates'). */
    public string $applyDateToAdd = '';

    /** @var list<string> */
    public array $applyDates = [];

    public string $applySearch = '';

    /** @var list<string> */
    public array $applyUserIds = [];

    /** @var array<string, mixed>|null Hasil dry_run terakhir. */
    public ?array $applyPreview = null;

    /** True setelah pengguna diberi tahu sel akan ditimpa dan perlu menegaskan. */
    public bool $applyConfirming = false;

    public ?string $applyError = null;

    public ?string $applyResult = null;

    // ── Salin bulan sebelumnya ─────────────────────────────────────────
    /** @var array<string, mixed>|null Hasil dry_run salin. */
    public ?array $copyPreview = null;

    public ?string $copyResult = null;

    public ?string $copyError = null;

    /** @var list<string> Kunci 'shiftId|date' sel yang isinya dibuka penuh. */
    public array $expanded = [];

    public const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * LAPIS KEDUA gate peran, di atas middleware rutenya. Role EFEKTIF:
     * tujuh role setara Admin lolos sebagai admin; operator → 403.
     */
    public function mount(): void
    {
        abort_unless($this->isAllowed(), 403);

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = $this->currentMonth();
        }
    }

    protected function isAllowed(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return in_array($role, [UserRole::Admin->value, UserRole::Supervisor->value, UserRole::MillManagement->value], true);
    }

    protected function currentMonth(): string
    {
        return Carbon::now(config('app.timezone'))->format('Y-m');
    }

    // ───────────────────────────────────────────────────────────────────
    // Konteks: mill / line / bulan
    // ───────────────────────────────────────────────────────────────────

    public function updatedBusinessUnitId(): void
    {
        $this->productionLineId = '';
        $this->resetTransient();
    }

    public function updatedProductionLineId(): void
    {
        $this->resetTransient();
    }

    public function previousMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->shiftMonth(1);
    }

    public function thisMonth(): void
    {
        $this->month = $this->currentMonth();
        $this->resetTransient();
    }

    protected function shiftMonth(int $by): void
    {
        $base = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)
            ? Carbon::createFromFormat('!Y-m', $this->month, config('app.timezone'))
            : Carbon::now(config('app.timezone'))->startOfMonth();

        $this->month = $base->addMonthsNoOverflow($by)->format('Y-m');
        $this->resetTransient();
    }

    /** Bergantinya konteks menutup panel, form terapkan, dan hasil sebelumnya. */
    protected function resetTransient(): void
    {
        $this->closeDay();
        $this->applyOpen = false;
        $this->applyPreview = null;
        $this->applyConfirming = false;
        $this->applyError = null;
        $this->applyResult = null;
        $this->applyShiftId = '';
        $this->applyDates = [];
        $this->applyUserIds = [];
        $this->copyPreview = null;
        $this->copyResult = null;
        $this->copyError = null;
        $this->expanded = [];
    }

    public function toggleCell(string $key): void
    {
        if (in_array($key, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$key]));
        } else {
            $this->expanded[] = $key;
        }
    }

    // ───────────────────────────────────────────────────────────────────
    // Tampilan + kartu tanggal
    // ───────────────────────────────────────────────────────────────────

    public function setViewMode(string $mode): void
    {
        // Berpindah tampilan tidak menyimpan/membuang apa pun; kartu tetap
        // terbuka pada tanggal yang sama.
        if (in_array($mode, ['calendar', 'table'], true)) {
            $this->viewMode = $mode;
        }
    }

    /** True bila ada shift pada kartu yang pilihannya berbeda dari isi tersimpan. */
    public function isDirty(?string $shiftId = null): bool
    {
        $ids = $shiftId !== null ? [$shiftId] : array_keys($this->dayLoaded);

        foreach ($ids as $id) {
            $a = $this->daySelected[$id] ?? [];
            $b = $this->dayLoaded[$id] ?? [];
            sort($a);
            sort($b);
            if ($a !== $b) {
                return true;
            }
        }

        return false;
    }

    /**
     * Membuka kartu tanggal. Sel tabel memanggilnya dengan shift (shift itu
     * dibuka lebih dulu); kotak kalender tanpa shift. Pindah ke tanggal LAIN
     * saat ada perubahan belum tersimpan → konfirmasi inline dulu.
     */
    public function openDay(string $date, ?string $shiftId = null): void
    {
        if ($this->openDate !== null && $this->openDate !== $date && $this->isDirty()) {
            $this->pendingDate = $date;
            $this->pendingShiftId = $shiftId;

            return;
        }

        if ($this->openDate === $date) {
            // Tanggal yang sama: hanya ganti shift yang dibuka, pilihan dipertahankan.
            if ($shiftId !== null) {
                $this->openShiftId = $shiftId;
                $this->panelSearch = '';
            }

            return;
        }

        $this->loadDay($date, $shiftId);
    }

    public function confirmDiscard(): void
    {
        $date = $this->pendingDate;
        $shiftId = $this->pendingShiftId;
        $this->pendingDate = null;
        $this->pendingShiftId = null;

        if ($date !== null) {
            $this->loadDay($date, $shiftId);
        }
    }

    public function cancelDiscard(): void
    {
        $this->pendingDate = null;
        $this->pendingShiftId = null;
    }

    public function selectShift(string $shiftId): void
    {
        $this->openShiftId = $shiftId;
        $this->panelSearch = '';
        $this->confirmClearShiftId = null;
    }

    protected function loadDay(string $date, ?string $shiftId): void
    {
        $this->panelError = null;
        $this->panelNotice = null;
        $this->feedbackShiftId = null;
        $this->confirmClearShiftId = null;
        $this->pendingDate = null;
        $this->pendingShiftId = null;
        $this->panelSearch = '';

        if ($this->productionLineId === '') {
            return;
        }

        try {
            $grid = app(ShiftScheduleService::class)->grid($this->productionLineId, $this->month);
        } catch (Throwable $e) {
            $this->panelError = $this->messageFor($e);

            return;
        }

        // Tanggal harus berasal dari respons server, bukan dari klien.
        if (! in_array($date, array_column($grid['days'], 'date'), true)) {
            return;
        }

        $loaded = [];
        $firstLive = null;
        foreach ($grid['cells'] as $cell) {
            if ($cell['date'] !== $date || ! $cell['editable']) {
                continue;
            }
            $loaded[$cell['shift_id']] = array_column($cell['assignees'], 'user_id');
        }
        foreach ($grid['shifts'] as $shift) {
            if (isset($loaded[$shift['id']])) {
                $firstLive ??= $shift['id'];
            }
        }

        $this->openDate = $date;
        $this->dayLoaded = $loaded;
        $this->daySelected = $loaded;
        $this->openShiftId = $shiftId ?? $firstLive;
    }

    /** Menutup kartu TANPA menulis apa pun. */
    public function closeDay(): void
    {
        $this->openDate = null;
        $this->openShiftId = null;
        $this->panelSearch = '';
        $this->daySelected = [];
        $this->dayLoaded = [];
        $this->panelError = null;
        $this->panelNotice = null;
        $this->feedbackShiftId = null;
        $this->confirmClearShiftId = null;
        $this->pendingDate = null;
        $this->pendingShiftId = null;
    }

    /** Menyimpan SATU shift pada kartu; shift lain tidak disentuh. */
    public function saveShift(string $shiftId): void
    {
        $this->confirmClearShiftId = null;
        $this->persistShift($shiftId, $this->daySelected[$shiftId] ?? []);
    }

    /**
     * Mengosongkan sel. Sel berisi lebih dari satu orang meminta konfirmasi
     * inline lebih dulu (klik pertama hanya menampilkannya).
     */
    public function clearShift(string $shiftId): void
    {
        if (count($this->dayLoaded[$shiftId] ?? []) > 1 && $this->confirmClearShiftId !== $shiftId) {
            $this->confirmClearShiftId = $shiftId;
            $this->feedbackShiftId = $shiftId;
            $this->panelError = null;
            $this->panelNotice = null;

            return;
        }

        $this->confirmClearShiftId = null;
        $this->persistShift($shiftId, []);
    }

    public function confirmClear(string $shiftId): void
    {
        $this->confirmClearShiftId = $shiftId;
        $this->clearShift($shiftId);
    }

    public function cancelClear(): void
    {
        $this->confirmClearShiftId = null;
    }

    /**
     * @param  list<string>  $userIds
     */
    protected function persistShift(string $shiftId, array $userIds): void
    {
        if ($this->openDate === null || ! array_key_exists($shiftId, $this->dayLoaded)) {
            return;
        }

        $this->panelError = null;
        $this->panelNotice = null;
        $this->feedbackShiftId = $shiftId;

        try {
            $result = app(ShiftScheduleService::class)->saveCell([
                'shift_id' => $shiftId,
                'date' => $this->openDate,
                'user_ids' => array_values($userIds),
                'loaded_user_ids' => $this->dayLoaded[$shiftId],
            ]);
        } catch (Throwable $e) {
            // Kartu tetap terbuka dengan pilihan pengguna utuh.
            $this->panelError = $this->messageFor($e);

            return;
        }

        $saved = array_column($result['assignees'], 'user_id');
        $notice = 'Petugas sel berhasil disimpan.';

        if ($result['changed_by_others']) {
            // Daftar kosong ditangani eksplisit — bukan whereIn([]).
            $names = $result['previous_user_ids'] === []
                ? []
                : User::query()->whereIn('id', $result['previous_user_ids'])->orderBy('name')->pluck('name')->all();
            $notice = 'Sel telah diubah orang lain sejak Anda membukanya'
                .($names !== [] ? ' (isi sebelumnya: '.implode(', ', $names).')' : ' (sebelumnya kosong)')
                .'. Isian Anda tetap disimpan dan menggantikannya.';
        }

        // Hanya shift ini yang disegarkan.
        $this->dayLoaded[$shiftId] = $saved;
        $this->daySelected[$shiftId] = $saved;
        $this->panelNotice = $notice;
    }

    // ───────────────────────────────────────────────────────────────────
    // Terapkan ke beberapa tanggal
    // ───────────────────────────────────────────────────────────────────

    public function openApply(): void
    {
        $this->applyOpen = true;
        $this->applyError = null;
        $this->applyResult = null;
        $this->applyPreview = null;
        $this->applyConfirming = false;

        $start = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)
            ? Carbon::createFromFormat('!Y-m', $this->month, config('app.timezone'))
            : Carbon::now(config('app.timezone'))->startOfMonth();

        $this->applyFrom = $start->copy()->startOfMonth()->toDateString();
        $this->applyTo = $start->copy()->endOfMonth()->toDateString();
    }

    public function closeApply(): void
    {
        $this->applyOpen = false;
        $this->applyPreview = null;
        $this->applyConfirming = false;
        $this->applyError = null;
    }

    /**
     * Mengubah isian apa pun membatalkan hitungan dampak dan konfirmasi
     * sebelumnya: keduanya hanya berlaku untuk isian yang dihitung.
     */
    public function updated(string $name): void
    {
        if (in_array($name, ['applyShiftId', 'applyMode', 'applyFrom', 'applyTo', 'applyDateToAdd', 'applyUserIds'], true)
            || str_starts_with($name, 'applyWeekdays')
            || str_starts_with($name, 'applyUserIds')) {
            $this->applyPreview = null;
            $this->applyConfirming = false;
            $this->applyError = null;
        }
    }

    public function addApplyDate(): void
    {
        $date = trim($this->applyDateToAdd);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->applyError = 'Tanggal harus berformat YYYY-MM-DD.';

            return;
        }

        if (! in_array($date, $this->applyDates, true)) {
            $this->applyDates[] = $date;
            sort($this->applyDates);
        }

        $this->applyDateToAdd = '';
        $this->applyPreview = null;
        $this->applyConfirming = false;
        $this->applyError = null;
    }

    public function removeApplyDate(string $date): void
    {
        $this->applyDates = array_values(array_diff($this->applyDates, [$date]));
        $this->applyPreview = null;
        $this->applyConfirming = false;
    }

    /** @return array<string, mixed> */
    protected function applyPayload(bool $dryRun, bool $confirm): array
    {
        $payload = [
            'shift_id' => $this->applyShiftId,
            'month' => $this->month,
            'user_ids' => array_values($this->applyUserIds),
            'dry_run' => $dryRun,
            'confirm_overwrite' => $confirm,
        ];

        if ($this->applyMode === 'dates') {
            $payload['dates'] = $this->applyDates;
        } else {
            $payload['range'] = [
                'from' => $this->applyFrom,
                'to' => $this->applyTo,
                'weekdays' => array_map('intval', $this->applyWeekdays),
            ];
        }

        return $payload;
    }

    /** "Hitung dampak" — tidak menulis apa pun. */
    public function previewApply(): void
    {
        $this->applyError = null;
        $this->applyResult = null;
        $this->applyConfirming = false;

        try {
            $this->applyPreview = app(ShiftScheduleService::class)->apply($this->applyPayload(true, false));
        } catch (Throwable $e) {
            $this->applyPreview = null;
            $this->applyError = $this->messageFor($e);
        }
    }

    /**
     * "Terapkan". Selalu menghitung dampak LEBIH DAHULU (angka segar, bukan
     * yang lama). Bila ada sel berisi yang akan ditimpa dan pengguna belum
     * menegaskan, konfirmasi inline ditampilkan DAN penyimpanan belum
     * terjadi; baru pada klik kedua ("Ya, timpa dan simpan") ia menulis.
     */
    public function submitApply(): void
    {
        $this->applyError = null;
        $this->applyResult = null;
        $service = app(ShiftScheduleService::class);

        try {
            $preview = $service->apply($this->applyPayload(true, false));
            $this->applyPreview = $preview;

            if ($preview['cells_already_filled'] > 0 && ! $this->applyConfirming) {
                $this->applyConfirming = true;

                return;
            }

            $result = $service->apply($this->applyPayload(false, $this->applyConfirming));
        } catch (ConfirmationRequiredException $e) {
            $this->applyConfirming = true;
            $this->applyError = $e->getMessage();

            return;
        } catch (Throwable $e) {
            $this->applyError = $this->messageFor($e);

            return;
        }

        $this->applyConfirming = false;
        $this->applyPreview = null;
        $this->applyResult = sprintf(
            '%d sel diperbarui dari %d sel sasaran.%s',
            $result['written_cells'],
            $result['target_cells'],
            $result['outside_month_ignored'] > 0
                ? ' '.$result['outside_month_ignored'].' tanggal di luar bulan ini diabaikan.'
                : ''
        );
    }

    public function cancelApplyConfirm(): void
    {
        $this->applyConfirming = false;
    }

    // ───────────────────────────────────────────────────────────────────
    // Salin dari bulan sebelumnya
    // ───────────────────────────────────────────────────────────────────

    /** Langkah 1: hitung (dry_run), lalu tunggu konfirmasi pengguna. */
    public function previewCopy(): void
    {
        $this->copyError = null;
        $this->copyResult = null;
        $this->copyPreview = null;

        try {
            $result = app(ShiftScheduleService::class)->copyPrevious([
                'production_line_id' => $this->productionLineId,
                'month' => $this->month,
                'dry_run' => true,
            ]);
        } catch (Throwable $e) {
            $this->copyError = $this->messageFor($e);

            return;
        }

        if ($result['nothing_to_copy']) {
            $this->copyResult = 'Bulan '.$this->monthLabel($result['source_month']).' belum punya penugasan, tidak ada yang disalin.';

            return;
        }

        $this->copyPreview = $result;
    }

    /** Langkah 2: salin sungguhan. */
    public function confirmCopy(): void
    {
        $this->copyError = null;

        try {
            $result = app(ShiftScheduleService::class)->copyPrevious([
                'production_line_id' => $this->productionLineId,
                'month' => $this->month,
                'dry_run' => false,
            ]);
        } catch (Throwable $e) {
            $this->copyError = $this->messageFor($e);

            return;
        }

        $this->copyPreview = null;
        $this->copyResult = $this->copySentence($result);
    }

    public function cancelCopy(): void
    {
        $this->copyPreview = null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function copySentence(array $result): string
    {
        $skipped = array_sum($result['skipped']);

        return sprintf('%d terisi, %d dipertahankan, %d dilewati', $result['filled'], $result['kept'], $skipped);
    }

    // ───────────────────────────────────────────────────────────────────
    // Util
    // ───────────────────────────────────────────────────────────────────

    /**
     * Galat service → teks untuk pengguna. 403 TIDAK ditelan (dilempar
     * ulang): akses ditolak bukan sesuatu yang boleh disamarkan.
     */
    protected function messageFor(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            foreach ($e->errors() as $messages) {
                return (string) ($messages[0] ?? 'Validasi gagal.');
            }

            return 'Validasi gagal.';
        }

        if ($e instanceof ShiftClosedException || $e instanceof NotACandidateException || $e instanceof ConfirmationRequiredException) {
            return $e->getMessage();
        }

        if ($e instanceof ModelNotFoundException) {
            return 'Data tidak ditemukan, mungkin sudah dihapus.';
        }

        throw $e;
    }

    public function monthLabel(string $month): string
    {
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m)) {
            return $month;
        }

        return self::MONTH_NAMES[(int) $m[2]].' '.$m[1];
    }

    public function render()
    {
        $service = app(ShiftScheduleService::class);

        $millOptions = $service->businessUnitOptions();
        $fixed = $millOptions['fixed'];
        $mills = $millOptions['data'];

        // Supervisor/Mill Management: mill dipaksa ke miliknya.
        if ($fixed) {
            $this->businessUnitId = $mills[0]['id'] ?? '';
        }

        $lineOptions = [];
        if ($this->businessUnitId !== '') {
            try {
                $lineOptions = $service->productionLineOptions($this->businessUnitId)['data'];
            } catch (HttpExceptionInterface $e) {
                if ($e->getStatusCode() !== 403) {
                    throw $e;
                }
                // Mill dari URL yang bukan milik akun ini: kembali ke tanpa pilihan.
                $this->businessUnitId = '';
                $this->productionLineId = '';
            }
        }

        // Line basi (bukan milik mill terpilih) dikembalikan tanpa galat.
        if ($this->productionLineId !== '' && ! in_array($this->productionLineId, array_column($lineOptions, 'id'), true)) {
            $this->productionLineId = '';
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = $this->currentMonth();
        }

        $grid = null;
        $cellMap = [];
        $dayCard = null;
        $calendarLead = 0;
        $calendarTrail = 0;
        $applyCandidates = [];

        if ($this->productionLineId !== '') {
            $grid = $service->grid($this->productionLineId, $this->month);

            if (($grid['days'] ?? []) !== []) {
                // Kotak kosong depan = weekday tanggal 1 dikurangi 1 (Senin=1),
                // dari tanggal yang dikirim server; belakang melengkapi minggu.
                $first = Carbon::parse($grid['days'][0]['date'], config('app.timezone'));
                $calendarLead = $first->dayOfWeekIso - 1;
                $calendarTrail = (7 - (($calendarLead + count($grid['days'])) % 7)) % 7;
            }

            foreach ($grid['cells'] as $cell) {
                $cellMap[$cell['shift_id']][$cell['date']] = $cell;
            }

            // Tanggal kartu harus ada di respons server; bila tidak (mis. bulan
            // berganti), kartu dianggap tertutup.
            if ($this->openDate !== null) {
                $dayEntry = collect($grid['days'])->firstWhere('date', $this->openDate);

                if ($dayEntry === null) {
                    $this->closeDay();
                } else {
                    $items = [];
                    foreach ($grid['shifts'] as $shift) {
                        $cell = $cellMap[$shift['id']][$this->openDate] ?? null;
                        if ($cell === null) {
                            continue; // shift sudah ditutup sebelum tanggal ini
                        }
                        $items[] = ['shift' => $shift, 'cell' => $cell, 'live' => (bool) $cell['editable']];
                    }

                    $openId = $this->openShiftId;
                    $liveIds = array_column(array_filter($items, fn ($i) => $i['live']), 'shift');
                    $liveIds = array_column($liveIds, 'id');
                    $editing = $openId !== null && in_array($openId, $liveIds, true) ? $openId : ($liveIds[0] ?? null);

                    $editor = null;
                    if ($editing !== null) {
                        $q = trim($this->panelSearch);
                        $result = $service->candidates($this->productionLineId, $q !== '' ? $q : null, $this->openDate);

                        // Anggota sel yang kini bukan calon (nonaktif / role berubah)
                        // tetap ditampilkan agar bisa dikeluarkan.
                        $candidateIds = array_column($result['data'], 'user_id');
                        $current = $cellMap[$editing][$this->openDate]['assignees'] ?? [];
                        $extra = array_values(array_filter($current, fn ($a) => ! in_array($a['user_id'], $candidateIds, true)));

                        $editor = [
                            'shift_id' => $editing,
                            'candidates' => $result['data'],
                            'empty_reason' => $result['empty_reason'],
                            'extra' => $extra,
                        ];
                    }

                    $dayCard = [
                        'date' => $this->openDate,
                        'day' => $dayEntry,
                        'items' => $items,
                        'open_shift_id' => $openId,
                        'editor' => $editor,
                    ];
                }
            }

            if ($this->applyOpen) {
                $q = trim($this->applySearch);
                $applyCandidates = $service->candidates($this->productionLineId, $q !== '' ? $q : null, null);
            }
        }

        return view('livewire.master-data.jadwal-shift', [
            'fixed' => $fixed,
            'mills' => $mills,
            'lineOptions' => $lineOptions,
            'grid' => $grid,
            'cellMap' => $cellMap,
            'dayCard' => $dayCard,
            'calendarLead' => $calendarLead,
            'calendarTrail' => $calendarTrail,
            'applyCandidates' => $applyCandidates,
            'monthText' => $this->monthLabel($this->month),
            'millName' => collect($mills)->firstWhere('id', $this->businessUnitId)['name'] ?? null,
        ]);
    }
}
