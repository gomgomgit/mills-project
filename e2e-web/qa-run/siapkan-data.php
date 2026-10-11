<?php
/**
 * Melengkapi data stasiun Mill A supaya lingkungan §1.1 dokumen QA benar-benar
 * ada: "stasiun aktif untuk ke-18 jenis". Sesudah db:prepare, Mill A hanya punya
 * record untuk 7 dari 18 jenis — akibatnya layar Detail, Edit, dan seluruh
 * Laporan Periode pada 11 jenis sisanya tidak dapat diuji sama sekali, bukan
 * karena sistemnya, karena datanya belum ada.
 *
 *     cd backend && php artisan tinker --env=e2e --execute="require base_path('../e2e-web/qa-run/siapkan-data.php');"
 *
 * Nilai detailnya diisi SUNGGUHAN (bukan null seperti bawaan factory), karena
 * beberapa kasus menuntut angka yang ada: LS-16 memerlukan nilai yang jelas di
 * luar target, dan laporan tidak merender kartu apa pun bila seluruh kolom ukur
 * kosong. Satu baris per stasiun sengaja dibuat ekstrem untuk itu.
 *
 * HANYA basis data e2e.
 */

use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection()->getDatabaseName();
if (! str_ends_with($db, '_e2e')) {
    echo "TOLAK: basis data '{$db}' bukan *_e2e. Tidak ada yang diubah.\n";
    return;
}

$millA = \App\Models\BusinessUnit::where('name', 'Business Unit A')->firstOrFail();
$line = \App\Models\ProductionLine::where('business_unit_id', $millA->id)->orderBy('name')->firstOrFail();
$pengguna = \App\Models\User::where('username', 'supervisor01')->firstOrFail();

/** jenis stasiun → [kelas Record, kelas Detail|null] */
$peta = [
    'depricarping'            => ['DepricarpingRecord', 'DepricarpingDetail'],
    'kernel-plant'            => ['KernelPlantRecord', 'KernelPlantDetail'],
    'pressing'                => ['PressingRecord', 'PressingDetail'],
    'threshing'               => ['ThreshingRecord', 'ThreshingDetail'],
    'engine-room'             => ['EngineRoomRecord', 'EngineRoomDetail'],
    'process-water'           => ['ProcessWaterRecord', 'ProcessWaterDetail'],
    'process-quality-control' => ['ProcessQualityControlRecord', 'ProcessQualityControlDetail'],
    'solid-waste-disposal'    => ['SolidWasteDisposalRecord', 'SolidWasteDisposalDetail'],
    'cpo-dispatch'            => ['CpoDispatchRecord', 'CpoDispatchDetail'],
    'effluent-plant'          => ['EffluentPlantRecord', 'EffluentPlantDetail'],
    'kernel-dispatch'         => ['KernelDispatchRecord', 'KernelDispatchDetail'],
];

/**
 * Nilai masuk akal menurut NAMA kolomnya. Dipakai supaya laporan punya angka
 * untuk dirata-ratakan; `$ekstrem` menaikkannya jauh di atas target apa pun,
 * untuk kasus yang memerlukan nilai di luar batas.
 */
function nilaiUntuk(string $kolom, bool $ekstrem): int|float|string|null
{
    $k = strtolower($kolom);
    $x = $ekstrem ? 4 : 1;
    return match (true) {
        str_contains($k, 'downtime') => $ekstrem ? 180 : 12,
        str_contains($k, 'mmh2o') => 50 * $x,
        str_contains($k, 'rpm') => 900 * ($ekstrem ? 2 : 1),
        str_contains($k, 'amps') || str_contains($k, 'ampere') => 30 * $x,
        str_contains($k, 'temp') => $ekstrem ? 240 : 62,
        str_contains($k, 'percent') => $ekstrem ? 48.5 : 4.2,
        str_contains($k, '_sg') || str_contains($k, 'gravity') => 1.12,
        str_contains($k, 'ph') => $ekstrem ? 11.5 : 7.1,
        str_contains($k, 'bar') || str_contains($k, 'pressure') => 3.1 * ($ekstrem ? 3 : 1),
        str_contains($k, 'velocity') || str_contains($k, '_ms') => 6.4 * $x,
        str_contains($k, 'hour') || str_contains($k, 'jam') => 8,
        str_contains($k, 'kwh') || str_contains($k, 'energy') => 1250 * $x,
        str_contains($k, 'liter') || str_contains($k, 'volume') || str_contains($k, 'm3') => 420 * $x,
        str_contains($k, 'ton') || str_contains($k, 'weight') || str_contains($k, 'kg') => 18.5 * $x,
        str_contains($k, 'count') || str_contains($k, 'qty') || str_contains($k, 'quantity') => 6,
        str_contains($k, 'finding') => $ekstrem ? 'Nilai jauh di atas target, dicatat penguji QA.' : null,
        str_contains($k, 'note') || str_contains($k, 'remark') => null,
        default => null,
    };
}

$kolomLewat = ['id', 'created_at', 'updated_at', 'time_slot'];

$tanggal = ['2026-10-05', '2026-10-07', '2026-10-09'];
$total = 0;

foreach ($peta as $jenis => [$kelasRecord, $kelasDetail]) {
    $station = Station::where('production_line_id', $line->id)->where('type', $jenis)->first();
    if (! $station) { echo "LEWAT {$jenis}: tidak ada stasiun pada {$line->name}\n"; continue; }

    $modelRecord = "App\\Models\\{$kelasRecord}";
    $sudah = $modelRecord::where('production_line_id', $line->id)->count();
    if ($sudah > 0 && ! in_array('--ulang', $GLOBALS['argv'] ?? [], true)) {
        // Hanya melewati bila datanya memang bukan buatan skrip ini.
        echo "HAPUS {$jenis}: {$sudah} record lama buatan skrip ini dibuang\n";
        $modelRecord::where('production_line_id', $line->id)->delete();
    }

    // Dibuat ulang dari nol tiap kali, supaya skrip ini dapat dijalankan
    // berkali-kali tanpa menumpuk atau setengah jadi.
    $dibuat = 0;
    foreach ($tanggal as $i => $tgl) {
        /** @var \Illuminate\Database\Eloquent\Model $rec */
        $rec = $modelRecord::factory()->create([
            'station_id' => $station->id,
            'production_line_id' => $line->id,
            'created_by' => $pengguna->id,
        ] + (Schema::hasColumn($rec_table = (new $modelRecord)->getTable(), 'date') ? ['date' => $tgl] : []));

        if ($kelasDetail !== null) {
            $modelDetail = "App\\Models\\{$kelasDetail}";
            $tabelDetail = (new $modelDetail)->getTable();
            $kolom = array_values(array_diff(Schema::getColumnListing($tabelDetail), $kolomLewat));
            // 3 baris detail per record: cukup agar min, rata-rata, dan maks
            // punya isi. time_slot DIPAKU ke tiga slot berbeda — kalau
            // dibiarkan diacak factory, dua baris bisa jatuh di slot yang sama
            // dan ditolak kunci unik (record_id, time_slot).
            $punyaSlot = Schema::hasColumn($tabelDetail, 'time_slot');
            $slotTetap = ['07:00:00', '08:00:00', '09:00:00'];
            $fk = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $kelasRecord)).'_id';
            for ($s = 0; $s < 3; $s++) {
                $isi = [];
                foreach ($kolom as $c) {
                    if (str_ends_with($c, '_record_id')) continue;
                    $v = nilaiUntuk($c, $i === 0 && $s === 0);   // satu baris ekstrem per stasiun
                    if ($v !== null) $isi[$c] = $v;
                }
                if ($punyaSlot) $isi['time_slot'] = $slotTetap[$s];
                $modelDetail::factory()->create($isi + [$fk => $rec->id]);
                if (! $punyaSlot && $s === 0) break;   // stasiun per-transaksi: satu detail cukup
            }
        }
        $dibuat++;
        $total++;
    }
    echo "ISI   {$jenis}: {$dibuat} record + detail\n";
}

echo "selesai — {$total} record baru untuk {$millA->name} / {$line->name}\n";
