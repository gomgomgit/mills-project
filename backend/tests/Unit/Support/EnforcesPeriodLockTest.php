<?php

/**
 * EnforcesPeriodLockTest — kunci Periode Pelaporan
 * (usecase-141--kunci-input-periode-tertutup).
 *
 * DUA LAPIS, DAN KEDUANYA PERLU:
 *
 *  1. PERILAKU guard-nya, diuji langsung atas trait lewat kelas anonim. Diuji
 *     terpisah dari service mana pun supaya kegagalannya menunjuk ke aturan
 *     periode, bukan tertukar dengan aturan validasi Sterilizer atau mill-scope.
 *
 *  2. STRUKTUR: ke-18 *RecordService benar-benar MEMANGGIL guard itu, di create
 *     MAUPUN update, dengan kode jenis stasiun yang ada di master. Lapis ini ada
 *     karena aturan lintas-layar yang dipasang 18 kali akan terlupakan pada
 *     service ke-19 — dan tanpa test struktural, kelupaan itu tidak menghasilkan
 *     satu pun kegagalan: service itu hanya diam-diam berhenti terkunci. Ini
 *     kelas kegagalan yang sama dengan tile REPORT_ROUTES yang hilang dan dengan
 *     enam spec browser yang merah empat hari tanpa ada yang tahu.
 *
 * Helper openPeriodFor()/openPeriodForStation() di tests/Pest.php SENGAJA tidak
 * dipakai di berkas ini: di sini ketiadaan periode justru keadaan yang diuji.
 */

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodClosedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Support\Concerns\EnforcesPeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    // Guard diuji lewat kelas anonim, bukan lewat salah satu dari 18 service:
    // yang jadi subjek di sini adalah trait-nya sendiri.
    $this->guard = new class
    {
        use EnforcesPeriodLock;

        public function check(string $stationType, string $millId, ?string $eventDate): void
        {
            $this->assertPeriodOpenForWrite($stationType, $millId, $eventDate);
        }
    };
});

/**
 * Satu periode pada sebuah mill dengan status per jenis stasiun yang diminta.
 *
 * @param  array<string, string>  $statusByType
 */
function lockPeriod(BusinessUnit $mill, string $name, string $start, string $end, array $statusByType): Period
{
    $period = Period::factory()
        ->forBusinessUnit($mill)
        ->named($name)
        ->range($start, $end)
        ->noStations()
        ->create();

    foreach ($statusByType as $type => $status) {
        PeriodStation::factory()->forPeriod($period)->stationType($type)->create(['status' => $status]);
    }

    return $period;
}

// ── LAPIS 1: perilaku ────────────────────────────────────────────────────────

it('menolak ketika mill belum punya satu pun periode, dan pesannya mengarahkan ke Admin', function () {
    try {
        $this->guard->check('sterilizer', $this->millA->id, '2026-10-15');
        $this->fail('seharusnya ditolak');
    } catch (PeriodClosedException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->errorCode())->toBe('PERIOD_CLOSED');
        expect($e->getMessage())->toContain('Belum ada Periode Pelaporan yang terbuka');
        expect($e->getMessage())->toContain('Hubungi Admin');
        // Tanggalnya disebut, supaya pengguna tahu baris mana yang ditolak.
        expect($e->getMessage())->toContain('15/10/2026');
    }
});

it('menolak ketika baris stasiun pada periode masih Draft, dan menyebut namanya', function () {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Draft->value]);

    try {
        $this->guard->check('sterilizer', $this->millA->id, '2026-10-15');
        $this->fail('seharusnya ditolak — Draft BUKAN terbuka');
    } catch (PeriodClosedException $e) {
        expect($e->getMessage())->toContain('belum dibuka');
        expect($e->getMessage())->toContain('Draft');
        expect($e->getMessage())->toContain('Okt 2026');
    }
});

it('menolak ketika baris stasiun pada periode sudah Tertutup, dan menyebut namanya', function () {
    lockPeriod($this->millA, 'Sep 2026', '2026-09-01', '2026-09-30', ['sterilizer' => PeriodStatus::Closed->value]);

    try {
        $this->guard->check('sterilizer', $this->millA->id, '2026-09-15');
        $this->fail('seharusnya ditolak');
    } catch (PeriodClosedException $e) {
        expect($e->getMessage())->toContain('sudah ditutup');
        expect($e->getMessage())->toContain('Sep 2026');
        expect($e->getMessage())->toContain('dibuka kembali');
    }
});

it('menerima ketika baris stasiun Terbuka dan tanggal di dalam rentang', function () {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    $this->guard->check('sterilizer', $this->millA->id, '2026-10-15');

    expect(true)->toBeTrue(); // tidak melempar
});

it('rentangnya INKLUSIF di kedua ujung', function (string $date) {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    $this->guard->check('sterilizer', $this->millA->id, $date);

    expect(true)->toBeTrue();
})->with(['2026-10-01', '2026-10-15', '2026-10-31']);

it('menolak tanggal di luar rentang periode terbuka, dan MENYEBUTKAN rentang yang berlaku', function (string $date) {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    try {
        $this->guard->check('sterilizer', $this->millA->id, $date);
        $this->fail("seharusnya ditolak untuk $date");
    } catch (PeriodClosedException $e) {
        expect($e->getMessage())->toContain('di luar periode yang terbuka');
        // Rentangnya disebut supaya pengguna tahu tanggal mana yang diterima,
        // tanpa harus meninggalkan form untuk mencarinya.
        expect($e->getMessage())->toContain('01/10/2026');
        expect($e->getMessage())->toContain('31/10/2026');
    }
})->with(['2026-09-30', '2026-11-01']);

it('status dinilai PER JENIS STASIUN: stasiun lain yang terbuka tidak membuka stasiun ini', function () {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', [
        'sterilizer' => PeriodStatus::Closed->value,
        'clarification' => PeriodStatus::Open->value,
    ]);

    // Clarification terbuka -> diterima.
    $this->guard->check('clarification', $this->millA->id, '2026-10-15');

    // Sterilizer tertutup di periode yang SAMA -> tetap ditolak.
    expect(fn () => $this->guard->check('sterilizer', $this->millA->id, '2026-10-15'))
        ->toThrow(PeriodClosedException::class);
});

it('periode terbuka milik mill lain tidak membuka pintu bagi mill ini', function () {
    lockPeriod($this->millB, 'Okt 2026 Mill B', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    try {
        $this->guard->check('sterilizer', $this->millA->id, '2026-10-15');
        $this->fail('seharusnya ditolak — periode mill lain bukan izin');
    } catch (PeriodClosedException $e) {
        // Pesannya tidak boleh menyebut periode mill lain sebagai "yang berlaku".
        expect($e->getMessage())->toContain('Belum ada Periode Pelaporan yang terbuka');
        expect($e->getMessage())->not->toContain('Mill B');
    }

    // Dan mill B sendiri tetap bisa menulis.
    $this->guard->check('sterilizer', $this->millB->id, '2026-10-15');
});

it('menolak tanggal kejadian yang kosong alih-alih menebak tanggal hari ini', function (?string $date) {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    try {
        $this->guard->check('sterilizer', $this->millA->id, $date);
        $this->fail('seharusnya ditolak');
    } catch (PeriodClosedException $e) {
        // Baris tanpa penanda waktu tidak bisa ditempatkan di periode mana pun;
        // menebak "hari ini" akan menyelundupkannya ke periode yang salah.
        expect($e->getMessage())->toContain('Tanggal kejadian data ini kosong');
    }
})->with([null, '', '   ']);

it('menerima penanda waktu lengkap, bukan hanya tanggal — jalur Weighbridge', function () {
    lockPeriod($this->millA, 'Okt 2026', '2026-10-01', '2026-10-31', ['weighbridge' => PeriodStatus::Open->value]);

    // record_datetime membawa jam; guard-nya menormalkan ke tanggal.
    $this->guard->check('weighbridge', $this->millA->id, '2026-10-31 23:59:00');

    expect(fn () => $this->guard->check('weighbridge', $this->millA->id, '2026-11-01 00:01:00'))
        ->toThrow(PeriodClosedException::class);
});

it('memilih periode terbuka yang TEPAT ketika satu mill punya beberapa periode', function () {
    lockPeriod($this->millA, 'Sep Tertutup', '2026-09-01', '2026-09-30', ['sterilizer' => PeriodStatus::Closed->value]);
    lockPeriod($this->millA, 'Okt Terbuka', '2026-10-01', '2026-10-31', ['sterilizer' => PeriodStatus::Open->value]);

    // Oktober terbuka -> diterima; September tertutup -> ditolak dengan sebab
    // yang menyebut periode September, bukan Oktober.
    $this->guard->check('sterilizer', $this->millA->id, '2026-10-15');

    try {
        $this->guard->check('sterilizer', $this->millA->id, '2026-09-15');
        $this->fail('seharusnya ditolak');
    } catch (PeriodClosedException $e) {
        expect($e->getMessage())->toContain('Sep Tertutup');
        expect($e->getMessage())->toContain('sudah ditutup');
    }
});

// ── LAPIS 2: struktur — tidak boleh ada service yang terlupakan ─────────────
//
// Keduanya MENGUMPULKAN pelanggaran lalu mengasersikan daftarnya kosong, bukan
// mengasersi satu per satu di dalam loop: dengan cara ini kegagalannya menyebut
// SELURUH service yang salah sekaligus, dan tidak berhenti di yang pertama.
// (Catatan API: expect()->toContain() bersifat variadic — argumen kedua dibaca
// sebagai nilai lain yang dicari, BUKAN sebagai pesan. Pola kumpulkan-lalu-
// asersikan menghindari jebakan itu sekaligus.)

it('ke-18 *RecordService memanggil guard pada create MAUPUN update', function () {
    $files = glob(app_path('Services/*RecordService.php'));

    expect(count($files))->toBe(18);

    $problems = [];

    foreach ($files as $file) {
        $name = basename($file, '.php');
        $source = file_get_contents($file);

        if (! str_contains($source, 'use EnforcesPeriodLock')) {
            $problems[] = "{$name}: tidak memakai trait EnforcesPeriodLock";
        }

        $cut = strpos($source, 'public function update(');

        if ($cut === false) {
            $problems[] = "{$name}: tidak punya update()";

            continue;
        }

        // Dipecah pada `public function update(` supaya kedua jalur diperiksa
        // sendiri-sendiri: satu pemanggilan di create tidak boleh menutupi
        // update yang kosong, dan itu persis kelalaian yang paling mungkin.
        $inCreate = substr_count(substr($source, 0, $cut), 'assertPeriodOpenForWrite(');
        $inUpdate = substr_count(substr($source, $cut), 'assertPeriodOpenForWrite(');

        if ($inCreate < 1) {
            $problems[] = "{$name}::create() tidak memanggil assertPeriodOpenForWrite()";
        }

        // DUA di update: tanggal lama dan tanggal baru.
        if ($inUpdate < 2) {
            $problems[] = "{$name}::update() memanggilnya {$inUpdate}x, harus 2x (tanggal lama DAN baru)";
        }
    }

    expect($problems)->toBe([]);
});

it('RecordVerificationService — jalur tulis KEEMPAT — juga memanggil guard', function () {
    // Verifikasi tidak lewat satu pun dari 18 *RecordService: ia punya service
    // dan route generiknya sendiri (PATCH /records/{stationType}/{id}/verification).
    // Jadi test struktural atas ke-18 service itu TIDAK menutupinya, dan jalur ini
    // sempat luput pada implementasi pertama — ketahuan hanya karena satu test
    // kontrak memakai endpoint yang salah dan memaksa saya membaca route-nya.
    $source = file_get_contents(app_path('Services/RecordVerificationService.php'));

    expect($source)->toContain('use EnforcesPeriodLock');
    expect(substr_count($source, 'assertPeriodOpenForWrite('))->toBeGreaterThanOrEqual(1);

    // Jenis stasiun WAJIB dibaca dari stasiun milik record, bukan dari segmen
    // {stationType} pada URL: yang terakhir datang dari request.
    expect($source)->toContain('$record->loadMissing(\'station\')');
});

it('setiap kode jenis stasiun yang dipakai guard ada di master station_types', function () {
    $master = \App\Models\StationType::query()->pluck('code')->all();

    // Kalau master-nya kosong di database test, asersi di bawah akan lolos
    // dengan sendirinya dan tidak membuktikan apa pun — jadi itu diasersi lebih
    // dulu.
    expect(count($master))->toBeGreaterThan(0);

    $problems = [];

    foreach (glob(app_path('Services/*RecordService.php')) as $file) {
        $name = basename($file, '.php');
        preg_match("/assertPeriodOpenForWrite\\('([a-z-]+)'/", file_get_contents($file), $matches);
        $code = $matches[1] ?? null;

        if ($code === null) {
            $problems[] = "{$name}: kode jenis stasiun tidak terbaca";

            continue;
        }

        // Kode yang salah tulis TIDAK akan pernah cocok dengan baris periode mana
        // pun, sehingga stasiun itu terkunci selamanya tanpa satu pun test gagal.
        if (! in_array($code, $master, true)) {
            $problems[] = "{$name}: kode '{$code}' tidak ada di station_types";
        }
    }

    expect($problems)->toBe([]);
});
