<?php

/**
 * SqliteDivergenceTest — jalur yang BERBEDA PERILAKU antara SQLite dan
 * PostgreSQL, dijalankan di PostgreSQL (2026-10-07).
 *
 * Setiap test di sini LOLOS di SQLite apa pun keadaan kodenya — itulah
 * sebabnya ia ada di irisan ini dan bukan di tests/Feature. Menyalinnya ke
 * suite utama tidak akan menambah perlindungan apa pun; ia hanya akan
 * terlihat seperti perlindungan.
 *
 * Empat kelas divergensi yang sudah berbiaya di proyek ini:
 *
 *   1. '' pada kolom uuid    — SQLite menerima, PostgreSQL SQLSTATE[22P02]
 *   2. LIKE tanpa ESCAPE     — PostgreSQL berdefault backslash, SQLite tidak
 *                              punya default sama sekali
 *   3. ILIKE                 — hanya ada di PostgreSQL
 *   4. whereDate             — diterjemahkan berbeda per driver
 *
 * Nomor 1 juga dijaga pemindai statis (tests/Feature/EmptyStringUuidComparisonTest).
 * Keduanya saling melengkapi, bukan duplikat: pemindai menangkap pola yang
 * belum pernah dijalankan siapa pun, test ini menangkap perilaku yang pola
 * statisnya terlihat benar.
 */

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\MasterDataTreeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── 1. '' pada kolom uuid ────────────────────────────────────────────────

it('menolak Company tanpa Corporate sebagai galat VALIDASI, bukan 22P02', function () {
    // Defek nyata yang pernah hidup di produksi (Kelola Company, diperbaiki
    // 2026-10-06): aturan unik nama ber-scope corporate_id dievaluasi dengan
    // $this->corporate_id = '', dan '' masuk ke perbandingan kolom uuid.
    //
    // Di SQLite ini LOLOS dengan maupun tanpa perbaikan — ia menerima ''
    // pada kolom bertipe uuid. Hanya di sini bedanya terlihat.
    $corporate = Corporate::factory()->create();

    // Nama ini SUDAH dipakai di bawah corporate lain. Itu yang membuat aturan
    // unik ber-scope benar-benar dievaluasi — tanpa baris ini, query-nya bisa
    // saja tidak pernah berjalan dan test lolos karena sebab yang salah.
    Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Nama Terpakai']);

    $service = app(CompanyService::class);

    // Yang BOLEH terjadi: ValidationException ("Corporate wajib dipilih").
    // Yang TIDAK boleh: QueryException 22P02, yaitu HTTP 500 di produksi.
    try {
        $service->create(['company_code' => 'PGT-001', 'name' => 'PT Nama Terpakai']);
        $this->fail('seharusnya ditolak validasi karena corporate_id kosong');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('corporate_id');
    }
});

it('membuktikan PostgreSQL memang menolak perbandingan "" dengan kolom uuid', function () {
    // Kontrol positif untuk test di atas. Tanpa ini, test sebelumnya bisa
    // lolos karena alasan yang salah — mis. validasi gagal sebelum query
    // apa pun berjalan, di mesin yang sebenarnya menerima '' juga.
    // SEKALI SAJA, dan ini penting: kueri yang gagal MENGABORSI transaksi
    // milik RefreshDatabase, sehingga panggilan kedua apa pun hanya akan
    // menjawab 25P02 "current transaction is aborted" — bukan 22P02 yang
    // sedang dibuktikan. Versi pertama test ini memanggilnya dua kali dan
    // gagal karena sebab itu, bukan karena produknya.
    try {
        DB::table('companies')->where('corporate_id', '')->count();
        $this->fail('PostgreSQL seharusnya menolak perbandingan "" dengan kolom uuid');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('22P02');
    }
});

// ── 2. LIKE tanpa ESCAPE ─────────────────────────────────────────────────

it('memperlakukan % di kata kunci sebagai huruf biasa, bukan wildcard', function () {
    // MasterDataTreeService::matchColumns() menulis ESCAPE '\' secara
    // eksplisit justru untuk ini. PostgreSQL berdefault backslash sementara
    // SQLite tidak punya default sama sekali, jadi tanpa klausa itu kedua
    // mesin memperlakukan kata kunci ber-% secara berbeda — dan yang terlihat
    // di suite bukan yang terjadi di produksi.
    $corporate = Corporate::factory()->create(['name' => 'PT Uji Escape']);
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Uji Escape Mill']);

    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill 100% Murni']);
    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Biasa Saja']);

    $board = app(MasterDataTreeService::class)->board('100%', 1, 20);

    // Kalau % diperlakukan sebagai wildcard, '100%' akan mencocokkan dua-duanya
    // (atau nol, tergantung mesin). Yang benar: tepat satu, yang namanya
    // memang memuat karakter % itu.
    expect($board['meta']['total'])->toBe(1);
    expect($board['data'][0]['name'])->toBe('Mill 100% Murni');
});

it('memperlakukan _ di kata kunci sebagai huruf biasa, bukan wildcard satu-karakter', function () {
    // _ adalah wildcard LIKE yang lebih mudah terlewat daripada %, karena ia
    // sering muncul di kode yang dibuat manusia (BU_A, PL_01).
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id]);

    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill BU_A']);
    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill BUxA']);

    $board = app(MasterDataTreeService::class)->board('BU_A', 1, 20);

    // Tanpa ESCAPE, 'BU_A' juga mencocokkan 'BUxA' — _ cocok ke satu karakter
    // apa pun. Yang benar: tepat satu.
    expect($board['meta']['total'])->toBe(1);
    expect($board['data'][0]['name'])->toBe('Mill BU_A');
});

// ── 3. lower() + LIKE, bukan ILIKE ───────────────────────────────────────

it('mencocokkan tanpa peduli huruf besar-kecil memakai lower()+LIKE yang jalan di kedua mesin', function () {
    // ILIKE akan membuat test ini lolos DI SINI dan menggagalkan suite utama.
    // lower()+LIKE lolos di keduanya — dan itulah satu-satunya bentuk yang
    // boleh dipakai. Test ini membuktikan bentuk portabel itu benar-benar
    // bekerja di PostgreSQL, bukan hanya di SQLite.
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id]);
    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Huruf Besar']);

    $service = app(MasterDataTreeService::class);

    expect($service->board('HURUF BESAR', 1, 20)['meta']['total'])->toBe(1);
    expect($service->board('huruf besar', 1, 20)['meta']['total'])->toBe(1);
    expect($service->board('HuRuF bEsAr', 1, 20)['meta']['total'])->toBe(1);
});

// ── 4. '' dinormalkan menjadi null, bukan disimpan sebagai '' ────────────

it('menyimpan kode Production Line kosong sebagai NULL, sehingga dua line tanpa kode tidak bentrok', function () {
    // ProductionLineService::emptyToNull() mengubah '' menjadi null sebelum
    // pemeriksaan unik. Di SQLite '' dan '' bentrok sama persis seperti di
    // PostgreSQL, jadi sisi "bentrok"-nya memang sama — yang berbeda adalah
    // apa yang tersimpan, dan kolom NOT NULL/numerik di tempat lain akan
    // menolak '' di PostgreSQL sementara SQLite menerimanya.
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id]);
    $mill = BusinessUnit::factory()->create(['company_id' => $company->id]);

    $service = app(\App\Services\ProductionLineService::class);
    $service->create(['business_unit_id' => $mill->id, 'name' => 'Line Satu', 'code' => '']);
    $service->create(['business_unit_id' => $mill->id, 'name' => 'Line Dua', 'code' => '']);

    $codes = ProductionLine::query()->where('business_unit_id', $mill->id)->pluck('code');

    expect($codes)->toHaveCount(2);
    expect($codes->filter(fn ($code) => $code !== null))->toBeEmpty();
});

// ── 5. whereDate ─────────────────────────────────────────────────────────

it('whereDate membandingkan BAGIAN TANGGAL, termasuk baris di ujung hari', function () {
    // whereDate() dirender berbeda per driver (::date di PostgreSQL,
    // strftime di SQLite). Baris pada 23:50 dan 00:10 adalah yang paling
    // mudah salah bila seseorang menggantinya dengan where() biasa.
    $mill = BusinessUnit::factory()->create([
        'company_id' => Company::factory()->create([
            'corporate_id' => Corporate::factory()->create()->id,
        ])->id,
    ]);

    // periods.created_by NOT NULL — ditemukan oleh irisan ini sendiri pada
    // percobaan pertama. SQLite menerima insert tanpa kolom itu di beberapa
    // konfigurasi; PostgreSQL menolaknya dengan 23502.
    DB::table('periods')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'business_unit_id' => $mill->id,
        'name' => 'Periode Uji whereDate',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'created_by' => User::factory()->create()->id,
        'created_at' => '2026-03-15 23:50:00',
        'updated_at' => '2026-03-15 23:50:00',
    ]);

    expect(DB::table('periods')->whereDate('created_at', '2026-03-15')->count())->toBe(1);
    expect(DB::table('periods')->whereDate('created_at', '2026-03-16')->count())->toBe(0);
});
