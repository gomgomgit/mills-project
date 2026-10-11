<?php
/**
 * Membentuk keadaan data yang DIMINTA prasyarat kasus LP-W di stasiun wakil
 * (Kernel Plant, Mill A / Line 1). Tanpa ini delapan kasus LP- tidak dapat
 * dijalankan sama sekali — bukan karena sistemnya, karena keadaannya belum ada:
 *
 *   LP-W-03  kolom terisi TIDAK SAMA BANYAK antar kolom
 *   LP-W-04  satu kolom yang TIDAK PERNAH diisi sepanjang periode
 *   LP-W-05  (a) periode tanpa satu record pun  (b) periode berisi record tetapi
 *            seluruh kolom ukurnya kosong
 *   LP-W-06  periode yang tanggal mulainya di masa depan
 *   LP-W-11  jumlah slot per hari sangat tidak sama, dengan nilai berbeda jauh
 *   LP-W-12  (a) tidak satu slot pun mengisi downtime  (b) slot mengisi 0 menit
 *   LP-W-13  dua temuan yang hanya beda huruf besar-kecil
 *   LP-W-14  satu slot mengisi downtime DAN temuan; satu slot temuan saja
 *
 *     cd backend && php artisan tinker --env=e2e --execute="require base_path('../e2e-web/qa-run/siapkan-laporan.php');"
 *
 * HANYA basis data e2e.
 */

use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

$db = DB::connection()->getDatabaseName();
if (! str_ends_with($db, '_e2e')) {
    echo "TOLAK: basis data '{$db}' bukan *_e2e. Tidak ada yang diubah.\n";
    return;
}

$millA = \App\Models\BusinessUnit::where('name', 'Business Unit A')->firstOrFail();
$line = \App\Models\ProductionLine::where('business_unit_id', $millA->id)->orderBy('name')->firstOrFail();
$station = Station::where('production_line_id', $line->id)->where('type', 'kernel-plant')->firstOrFail();
$admin = \App\Models\User::where('username', 'admin')->firstOrFail();

/** Periode baru + seluruh jenis stasiunnya, status menyusul lewat UI. */
function periode(string $nama, string $mulai, string $selesai, string $buId, string $adminId): Period
{
    $ada = Period::where('business_unit_id', $buId)->where('name', $nama)->first();
    if ($ada) return $ada;
    $p = Period::create([
        'business_unit_id' => $buId, 'name' => $nama,
        'start_date' => $mulai, 'end_date' => $selesai, 'created_by' => $adminId,
    ]);
    foreach (Station::where('business_unit_id', $buId)->pluck('type')->unique() as $t) {
        PeriodStation::create(['period_id' => $p->id, 'station_type' => $t, 'status' => 'open']);
    }
    return $p;
}

// ── LP-W-06: periode masa depan ───────────────────────────────────────────
$depan = periode('QA Masa Depan', '2027-02-01', '2027-02-28', $millA->id, $admin->id);
echo "LP-W-06  periode masa depan  : {$depan->name} ({$depan->start_date}..{$depan->end_date})\n";

// ── LP-W-05(a): periode tanpa satu record pun ─────────────────────────────
$kosong = periode('QA Tanpa Record', '2026-06-01', '2026-06-30', $millA->id, $admin->id);
echo "LP-W-05a periode tanpa record: {$kosong->name}\n";

// ── LP-W-05(b): periode berisi record tetapi seluruh kolom ukur kosong ────
$kolomKosong = periode('QA Kolom Kosong', '2026-05-01', '2026-05-31', $millA->id, $admin->id);
if (KernelPlantRecord::where('production_line_id', $line->id)->whereBetween('date', ['2026-05-01', '2026-05-31'])->count() === 0) {
    $rec = KernelPlantRecord::factory()->create([
        'station_id' => $station->id, 'production_line_id' => $line->id,
        'created_by' => $admin->id, 'date' => '2026-05-10',
    ]);
    // Tiga slot, SELURUH kolom ukur dibiarkan null — penyebutnya terbentuk,
    // yang terisi nol. Itulah yang membedakan 0,0% dari tanda pisah.
    foreach (['07:00:00', '08:00:00', '09:00:00'] as $slot) {
        KernelPlantDetail::factory()->create([
            'kernel_plant_record_id' => $rec->id,
            'time_slot' => $slot,
        ]);
    }
    echo "LP-W-05b periode berisi record tanpa satu kolom ukur terisi: {$kolomKosong->name}\n";
} else {
    echo "LP-W-05b sudah ada\n";
}

// ── LP-W-03/04/11/12/13/14: satu periode dengan keadaan yang dirancang ────
$racik = periode('QA Racikan', '2026-04-01', '2026-04-30', $millA->id, $admin->id);
if (KernelPlantRecord::where('production_line_id', $line->id)->whereBetween('date', ['2026-04-01', '2026-04-30'])->count() === 0) {
    // Hari-1: HANYA 2 slot, nilai TINGGI. Hari-2: 20 slot, nilai RENDAH.
    // Rata-rata dari rata-rata harian akan jauh berbeda dari rata-rata seluruh
    // slot — itulah yang diuji LP-W-11.
    $slots = \App\Services\KernelPlantRecordService::canonicalTimeSlots();

    $hari1 = KernelPlantRecord::factory()->create([
        'station_id' => $station->id, 'production_line_id' => $line->id,
        'created_by' => $admin->id, 'date' => '2026-04-10',
    ]);
    foreach (array_slice($slots, 0, 2) as $i => $slot) {
        KernelPlantDetail::factory()->create([
            'kernel_plant_record_id' => $hari1->id,
            'time_slot' => $slot,
            'ripple_mill_1_amps' => 90,          // nilai TINGGI
            'ripple_mill_2_amps' => 88,
            'kernel_moisture_percent' => 9.5,
            // claybath_hydro_sg SENGAJA tidak pernah diisi → LP-W-04
            // downtime_minutes dibiarkan null pada hari ini → LP-W-12(a)
            'findings' => $i === 0 ? 'Bearing panas' : null,     // ejaan ke-1 → LP-W-13
        ]);
    }

    $hari2 = KernelPlantRecord::factory()->create([
        'station_id' => $station->id, 'production_line_id' => $line->id,
        'created_by' => $admin->id, 'date' => '2026-04-11',
    ]);
    foreach (array_slice($slots, 0, 20) as $i => $slot) {
        $isi = [
            'kernel_plant_record_id' => $hari2->id,
            'time_slot' => $slot,
            'ripple_mill_1_amps' => 12,          // nilai RENDAH
            'ripple_mill_2_amps' => 11,
        ];
        // Kolom kelembapan hanya diisi pada 3 dari 20 slot → penyebut antar
        // kartu BERBEDA, prasyarat LP-W-03.
        if ($i < 3) $isi['kernel_moisture_percent'] = 6.1;
        // Downtime 0 yang benar-benar tercatat → LP-W-12(b).
        if ($i < 4) $isi['downtime_minutes'] = 0;
        // Satu slot: downtime DAN temuan sekaligus → LP-W-14.
        if ($i === 5) { $isi['downtime_minutes'] = 45; $isi['findings'] = 'Ripple mill berhenti'; }
        // Satu slot: temuan SAJA, tanpa downtime → LP-W-14.
        if ($i === 6) $isi['findings'] = 'bearing panas';       // ejaan ke-2 → LP-W-13
        KernelPlantDetail::factory()->create($isi);
    }
    echo "LP-W-03/04/11/12/13/14 periode racikan: {$racik->name}"
       . " (hari-1 2 slot nilai tinggi, hari-2 20 slot nilai rendah;"
       . " claybath_hydro_sg tak pernah diisi; downtime null vs 0 vs 45;"
       . " temuan 'Bearing panas' dan 'bearing panas')\n";
} else {
    echo "LP-W-03/04/11/12/13/14 sudah ada\n";
}

// ── LP-W-12(a): periode BERDATA tetapi tidak satu slot pun mengisi downtime.
// "QA Kolom Kosong" tidak dapat dipakai untuk ini: tanpa satu slot terisi,
// laporan sengaja TIDAK menggambar blok downtime sama sekali (lihat komentar
// empty-state (e) pada view-nya), jadi yang teruji bukan "tanda pisah vs nol"
// melainkan ketiadaan bloknya.
$tanpaDt = periode('QA Tanpa Downtime', '2026-03-01', '2026-03-31', $millA->id, $admin->id);
if (KernelPlantRecord::where('production_line_id', $line->id)->whereBetween('date', ['2026-03-01', '2026-03-31'])->count() === 0) {
    $slots = \App\Services\KernelPlantRecordService::canonicalTimeSlots();
    $rec = KernelPlantRecord::factory()->create([
        'station_id' => $station->id, 'production_line_id' => $line->id,
        'created_by' => $admin->id, 'date' => '2026-03-12',
    ]);
    foreach (array_slice($slots, 0, 4) as $slot) {
        KernelPlantDetail::factory()->create([
            'kernel_plant_record_id' => $rec->id,
            'time_slot' => $slot,
            'ripple_mill_1_amps' => 28,
            'ripple_mill_2_amps' => 27,
            'kernel_moisture_percent' => 6.8,
            // downtime_minutes SENGAJA dibiarkan null pada SELURUH slot.
        ]);
    }
    echo "LP-W-12a periode berdata tanpa satu slot downtime: {$tanpaDt->name}\n";
} else {
    echo "LP-W-12a sudah ada\n";
}

echo "selesai.\n";
