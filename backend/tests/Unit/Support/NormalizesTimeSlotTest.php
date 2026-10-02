<?php

/**
 * NormalizesTimeSlotTest — slot waktu detail stasiun
 * (cacat ditemukan 2026-10-02, 11 stasiun sekaligus).
 *
 * APA YANG RUSAK. `*_details.time_slot` adalah kolom `time` sungguhan, jadi
 * PostgreSQL mengembalikannya '07:00:00', sementara canonicalTimeSlots()
 * menyusun daftarnya dengan sprintf('%02d:00', ...) — '07:00'. Keduanya tidak
 * pernah cocok, sehingga MEMBUAT record berhasil (form mengirim '07:00')
 * tetapi MENGEDIT record yang sama lalu menyimpannya SELALU ditolak dengan
 * "Time-Slot ... harus salah satu dari 24 slot kanonis (07:00-06:00)".
 *
 * MENGAPA 4080 TEST YANG LOLOS TIDAK BERKATA APA-APA, dan mengapa berkas ini
 * ditulis seperti di bawah. Suite berjalan di SQLite, yang tidak punya tipe
 * time: kolomnya menyimpan string apa adanya, jadi perjalanan tulis-baca
 * mengembalikan '07:00' dan perbandingannya lolos. Produksi memakai PostgreSQL.
 * Karena itu test di sini MENYUAPKAN '07:00:00' SECARA EKSPLISIT alih-alih
 * mengandalkan perjalanan tulis-baca — supaya ia tetap gagal di SQLite kalau
 * normalisasinya dicabut. Mengandalkan round-trip akan menghasilkan test yang
 * hijau di CI dan tidak pernah menjaga apa pun.
 *
 * DUA LAPIS:
 *
 *  1. PERILAKU helper-nya, lewat kelas anonim yang memakai trait.
 *  2. STRUKTUR: ke-11 service yang punya grid time-slot benar-benar memakai
 *     trait itu DAN benar-benar melewatkan time_slot lewat helper-nya. Tanpa
 *     lapis ini, service ke-12 akan lupa dan kelupaannya tidak menghasilkan
 *     satu pun kegagalan — pola yang sama dengan test struktural di
 *     EnforcesPeriodLockTest.
 */

use App\Support\Concerns\NormalizesTimeSlot;
use Tests\TestCase;

// Seperti dua berkas Unit lain di direktori ini. Tanpa ini Pest menjalankan
// berkas sebagai test PHPUnit telanjang, yang pada PHP 8.5 melaporkan
// ReflectionMethod::setAccessible() sebagai deprecated satu kali per test —
// 11 baris kebisingan yang melatih orang mengabaikan peringatan.
uses(TestCase::class);

function timeSlotNormalizer(): object
{
    return new class
    {
        use NormalizesTimeSlot;

        public function call(mixed $value): mixed
        {
            return $this->canonicalTimeSlot($value);
        }
    };
}

/** Ke-11 service dengan grid detail ber-time_slot. Sterilizer TIDAK punya. */
function timeSlotServices(): array
{
    return [
        'BoilerRoom', 'Clarification', 'Depricarping', 'EffluentPlant', 'EngineRoom',
        'KernelPlant', 'Pressing', 'ProcessQualityControl', 'ProcessWater',
        'StorageTank', 'Threshing',
    ];
}

// ---------------------------------------------------------------------
// 1. Perilaku
// ---------------------------------------------------------------------

it('memotong detik dari nilai yang dikembalikan PostgreSQL', function () {
    expect(timeSlotNormalizer()->call('07:00:00'))->toBe('07:00');
});

it('memotong detik berpecahan yang bisa dikembalikan PostgreSQL', function () {
    expect(timeSlotNormalizer()->call('23:00:00.000000'))->toBe('23:00');
});

it('membiarkan nilai yang sudah kanonis apa adanya', function () {
    expect(timeSlotNormalizer()->call('06:00'))->toBe('06:00');
});

it('menormalkan seluruh 24 slot kanonis dari bentuk berdetiknya', function () {
    $normalizer = timeSlotNormalizer();

    foreach (App\Services\BoilerRoomRecordService::canonicalTimeSlots() as $slot) {
        expect($normalizer->call($slot.':00'))->toBe($slot);
    }
});

it('membiarkan null lewat tanpa diubah', function () {
    expect(timeSlotNormalizer()->call(null))->toBeNull();
});

it('membiarkan string kosong lewat tanpa diubah', function () {
    // '' harus tetap '' agar validator menolaknya dengan pesannya sendiri,
    // bukan diam-diam diubah menjadi slot yang sah.
    expect(timeSlotNormalizer()->call(''))->toBe('');
});

it('TIDAK menebak-nebak nilai yang tidak dikenali', function () {
    // Nilai ngawur harus sampai ke validator apa adanya dan ditolak di sana.
    // Menormalkannya menjadi sesuatu yang sah akan menyembunyikan input rusak.
    foreach (['pagi', '7:00', '25:00:00', '07-00', 'null'] as $garbage) {
        expect(timeSlotNormalizer()->call($garbage))->toBe($garbage);
    }
});

it('membiarkan nilai non-string lewat tanpa diubah', function () {
    expect(timeSlotNormalizer()->call(700))->toBe(700);
});

// ---------------------------------------------------------------------
// 2. Struktur
// ---------------------------------------------------------------------

it('ke-11 service dengan grid time-slot memakai trait NormalizesTimeSlot', function () {
    $problems = [];

    foreach (timeSlotServices() as $name) {
        $class = "App\\Services\\{$name}RecordService";

        if (! in_array(NormalizesTimeSlot::class, class_uses_recursive($class), true)) {
            $problems[] = "{$name}RecordService tidak memakai trait NormalizesTimeSlot";
        }
    }

    expect($problems)->toBe([]);
});

it('ke-11 service benar-benar melewatkan time_slot lewat canonicalTimeSlot()', function () {
    // Memakai trait tanpa memanggilnya tidak memperbaiki apa pun — itu kelupaan
    // yang tidak menghasilkan satu pun kegagalan, jadi ia diperiksa di sini.
    $problems = [];

    foreach (timeSlotServices() as $name) {
        $path = app_path("Services/{$name}RecordService.php");
        $source = file_get_contents($path);

        if (! str_contains($source, "'time_slot' => \$this->canonicalTimeSlot(")) {
            $problems[] = "{$name}RecordService menormalkan time_slot lewat trait tapi tidak memanggilnya di normalizeDetails()";
        }
    }

    expect($problems)->toBe([]);
});

it('canonicalTimeSlots() tiap service memakai bentuk H:i, bukan H:i:s', function () {
    // Separuh lain dari pasangan ini: kalau daftar kanonis suatu hari berubah
    // menjadi berdetik, normalisasi di atas justru akan MENYEBABKAN ketidakcocokan
    // alih-alih menghilangkannya. Dikunci dari kedua sisi.
    $problems = [];

    foreach (timeSlotServices() as $name) {
        $class = "App\\Services\\{$name}RecordService";

        if (! method_exists($class, 'canonicalTimeSlots')) {
            continue;
        }

        foreach ($class::canonicalTimeSlots() as $slot) {
            if (preg_match('/^\d{2}:\d{2}$/', $slot) !== 1) {
                $problems[] = "{$name}RecordService::canonicalTimeSlots() memuat '{$slot}', bukan bentuk H:i";

                break;
            }
        }
    }

    expect($problems)->toBe([]);
});
