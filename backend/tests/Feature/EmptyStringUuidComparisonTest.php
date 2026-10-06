<?php

/**
 * EmptyStringUuidComparisonTest — menjaga satu KELAS defek yang suite ini,
 * secara struktural, tidak bisa melihat (2026-10-06).
 *
 * ── DEFEKNYA ─────────────────────────────────────────────────────────────
 *
 * Properti Livewire yang dideklarasikan `public string $x = ''` dipakai apa
 * adanya di dalam perbandingan query terhadap kolom bertipe `uuid`. Ketika
 * user belum memilih apa pun, yang dibandingkan adalah STRING KOSONG:
 *
 *     ->where('corporate_id', $this->corporate_id)   // $this->corporate_id = ''
 *
 * PostgreSQL menolaknya dengan SQLSTATE[22P02] "invalid input syntax for type
 * uuid", dan Laravel menyajikannya sebagai HALAMAN 500 — user tidak pernah
 * melihat pesan validasi yang sebenarnya dibutuhkannya. Persis itu yang
 * terjadi pada Kelola Company: "Tambah Company tanpa memilih Corporate"
 * menghasilkan 500 di produksi, dan tidak ada yang menyadarinya.
 *
 * ── KENAPA SUITE INI BUTA TERHADAPNYA ────────────────────────────────────
 *
 * backend/phpunit.xml memaksa DB_CONNECTION=sqlite. SQLite MENERIMA
 * perbandingan '' dengan kolom uuid — ia tidak punya tipe uuid sama sekali,
 * hanya afinitas teks. Jadi setiap uji perilaku atas jalur ini LOLOS di
 * SQLite, sebelum maupun sesudah perbaikan, sementara produksi PostgreSQL
 * jatuh 500. Component test untuk skenario yang gagal itu HIJAU sepanjang
 * waktu; satu-satunya jaring hari ini adalah suite browser e2e-web, yang
 * berjalan di PostgreSQL dan tidak dijalankan setiap kali orang menyentuh
 * kode.
 *
 * Karena itu test ini TIDAK menguji perilaku — tidak ada gunanya, SQLite akan
 * selalu meluluskannya. Ia memindai SUMBER secara statis, sehingga ia bekerja
 * tanpa peduli basis data apa yang sedang dipakai.
 *
 * ── APA YANG DIPINDAI, DAN APA YANG TIDAK ────────────────────────────────
 *
 * Dipindai: setiap `->where('<kolom>', $this-><properti>)` di app/Livewire/
 * yang kolomnya dideklarasikan `uuid()`/`foreignUuid()` di migrasi DAN
 * propertinya dideklarasikan ber-default string kosong.
 *
 * Tipe kolom dibaca dari MIGRASI, bukan dari basis data, dan itu disengaja:
 * SQLite tidak dapat membedakan `uuid` dari `string` (keduanya menjadi
 * varchar), jadi membacanya dari basis data test justru akan membuat
 * pemindai ini ikut buta. Perlu diingat juga 20 kolom berakhiran `_id` di
 * skema ini BUKAN uuid melainkan varchar (presser_id, boiler_room_id, dan
 * sejenisnya — pengenal bisnis, bukan kunci), jadi aturan "berakhiran _id"
 * akan salah tangkap; hanya tipe dari migrasi yang benar.
 *
 * TIDAK dipindai: Service dan Controller. Keduanya menerima nilai yang sudah
 * melewati validasi, dan jalur yang rusak justru jalur SEBELUM validasi
 * selesai — properti komponen yang dibaca saat aturan validasi disusun.
 *
 * ── PENJAGA YANG DITERIMA ────────────────────────────────────────────────
 *
 * Perbandingan boleh ada selama ia dijaga, dan dua bentuk inilah yang sudah
 * dipakai di codebase ini:
 *
 *   ->when($this->x !== '', fn ($q) => $q->where('col', $this->x))
 *   ->where(fn ($q) => $this->x === '' ? $q->whereRaw('1 = 0') : $q->where(...))
 *
 * Yang dicari pemindai adalah pembandingan eksplisit properti itu terhadap
 * string kosong di sekitar baris yang sama. Bila Anda menambahkan bentuk
 * penjaga ketiga, tambahkan pengenalannya di sini — JANGAN melonggarkan
 * daftar pengecualian.
 */

use Illuminate\Support\Facades\File;

/**
 * Kolom yang dideklarasikan uuid()/foreignUuid() di seluruh migrasi.
 *
 * @return array<string, true>
 */
function uuidColumnsFromMigrations(): array
{
    $columns = [];

    foreach (File::glob(database_path('migrations/*.php')) as $file) {
        preg_match_all(
            '/->(?:uuid|foreignUuid)\(\s*[\'"]([a-z0-9_]+)[\'"]/',
            File::get($file),
            $matches,
        );

        foreach ($matches[1] as $column) {
            $columns[$column] = true;
        }
    }

    return $columns;
}

/**
 * Properti ber-default string kosong pada satu sumber komponen.
 *
 * @return array<string, true>
 */
function emptyStringProperties(string $source): array
{
    preg_match_all('/public\s+\??string\s+\$([a-zA-Z0-9_]+)\s*=\s*[\'"]{2}\s*;/', $source, $matches);

    return array_fill_keys($matches[1], true);
}

/**
 * Pelanggaran pada satu sumber: properti kosong dibandingkan dengan kolom
 * uuid tanpa penjaga.
 *
 * @param  array<string, true>  $uuidColumns
 * @return list<string>
 */
function emptyStringUuidViolations(string $source, string $label, array $uuidColumns): array
{
    $properties = emptyStringProperties($source);

    if ($properties === []) {
        return [];
    }

    $lines = preg_split('/\R/', $source);
    $violations = [];

    preg_match_all(
        '/->where\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*\$this->([a-zA-Z0-9_]+)/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
    );

    foreach ($matches as $match) {
        [$whole, $offset] = $match[0];
        $column = $match[1][0];
        $property = $match[2][0];

        if (! isset($uuidColumns[$column]) || ! isset($properties[$property])) {
            continue;
        }

        // Jendela penjaga: baris perbandingannya sendiri plus tiga baris di
        // atasnya. Kedua bentuk penjaga yang dipakai codebase ini menaruh
        // pembandingannya di dalam jangkauan itu — `when(...)` pada baris yang
        // sama, terner `=== ''` satu-dua baris di atasnya.
        $lineNumber = substr_count(substr($source, 0, $offset), "\n");
        $window = implode("\n", array_slice($lines, max(0, $lineNumber - 3), 4));

        $guarded = preg_match(
            '/\$this->'.preg_quote($property, '/').'\s*(?:!==|===)\s*[\'"]{2}/',
            $window,
        ) === 1;

        if (! $guarded) {
            $violations[] = "{$label}:".($lineNumber + 1)." — \$this->{$property} (default '') dibandingkan dengan kolom uuid '{$column}' tanpa penjaga string kosong";
        }
    }

    return $violations;
}

it('tidak ada properti Livewire ber-default string kosong yang dibandingkan dengan kolom uuid tanpa penjaga', function () {
    $uuidColumns = uuidColumnsFromMigrations();

    // Kalau pembacaan migrasi gagal, pemindai ini akan diam-diam meluluskan
    // segalanya. Angka ini memakukan bahwa ia benar-benar membaca sesuatu.
    expect(count($uuidColumns))->toBeGreaterThan(25);

    $files = File::allFiles(app_path('Livewire'));
    expect(count($files))->toBeGreaterThan(50);

    $violations = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        array_push($violations, ...emptyStringUuidViolations(
            File::get($file->getPathname()),
            $file->getFilename(),
            $uuidColumns,
        ));
    }

    expect($violations)->toBe([]);
});

it('pemindai benar-benar menangkap perbandingan tanpa penjaga (kontrol negatif)', function () {
    // Pola yang DULU menjatuhkan Kelola Company: properti ber-default kosong
    // masuk langsung ke scope keunikan, tanpa pemeriksaan apa pun.
    $bad = <<<'PHP'
        <?php
        class X {
            public string $corporate_id = '';

            protected function rules(): array
            {
                $rule = UniqueCaseInsensitive::on('companies', 'name')
                    ->where(fn ($query) => $query->where('corporate_id', $this->corporate_id));

                return [];
            }
        }
        PHP;

    $violations = emptyStringUuidViolations($bad, 'contoh', ['corporate_id' => true]);

    expect($violations)->toHaveCount(1);
    expect($violations[0])->toContain('corporate_id');
});

it('pemindai menerima kedua bentuk penjaga yang dipakai codebase ini', function () {
    $uuidColumns = ['corporate_id' => true, 'business_unit_id' => true];

    // Bentuk 1 — when() pada baris yang sama (Kelola Station).
    $whenGuard = <<<'PHP'
        <?php
        class X {
            public string $filterBusinessUnitId = '';

            protected function options(): array
            {
                return ProductionLine::query()
                    ->when($this->filterBusinessUnitId !== '', fn ($q) => $q->where('business_unit_id', $this->filterBusinessUnitId))
                    ->get();
            }
        }
        PHP;

    // Bentuk 2 — terner === '' dengan whereRaw (Kelola Company sesudah perbaikan).
    $ternaryGuard = <<<'PHP'
        <?php
        class X {
            public string $corporate_id = '';

            protected function rules(): array
            {
                $rule = UniqueCaseInsensitive::on('companies', 'name')
                    ->where(fn ($query) => $this->corporate_id === ''
                        ? $query->whereRaw('1 = 0')
                        : $query->where('corporate_id', $this->corporate_id));

                return [];
            }
        }
        PHP;

    expect(emptyStringUuidViolations($whenGuard, 'contoh', $uuidColumns))->toBe([]);
    expect(emptyStringUuidViolations($ternaryGuard, 'contoh', $uuidColumns))->toBe([]);
});

it('pemindai tidak menjaring kolom *_id yang BUKAN uuid', function () {
    // 20 kolom berakhiran _id di skema ini adalah varchar, bukan kunci:
    // presser_id, boiler_room_id, qc_inspector_id, dan sejenisnya. String
    // kosong pada kolom teks tidak pernah menjatuhkan PostgreSQL, jadi
    // menandainya hanya akan melatih orang mengabaikan pemindai ini.
    $source = <<<'PHP'
        <?php
        class X {
            public string $presser_id = '';

            protected function q(): array
            {
                return PressingRecord::query()->where('presser_id', $this->presser_id)->get();
            }
        }
        PHP;

    // uuidColumns sengaja TIDAK memuat presser_id — persis seperti hasil
    // pembacaan migrasi yang sebenarnya.
    expect(emptyStringUuidViolations($source, 'contoh', ['corporate_id' => true]))->toBe([]);
});
