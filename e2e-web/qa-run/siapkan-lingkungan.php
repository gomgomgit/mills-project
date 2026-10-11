<?php
/**
 * Menyiapkan lingkungan uji yang DIMINTA DOKUMEN QA (§1.1 dan §1.2) di basis
 * data e2e. Dijalankan sesudah `npm run db:prepare`:
 *
 *     cd backend && php artisan tinker --env=e2e < ../e2e-web/qa-run/siapkan-lingkungan.php
 *
 * Dokumen mewajibkan 6 akun, dan dua di antaranya tidak dibuat seeder mana pun:
 * SPV-B (supervisor Mill B, untuk menguji data Mill A tidak terlihat) dan NOMILL
 * (supervisor tanpa mill, sengaja cacat). Tanpa keduanya, LS-03, LS-04, dan LS-05
 * tidak dapat dijalankan sama sekali — bukan karena sistemnya, karena akunnya
 * belum ada. Juga memastikan Mill B punya record pada tanggal yang sama dengan
 * Mill A, prasyarat LS-03.
 *
 * HANYA basis data e2e. Tidak pernah dijalankan terhadap basis data dev.
 */

use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$db = DB::connection()->getDatabaseName();
if (! str_ends_with($db, '_e2e')) {
    echo "TOLAK: basis data '{$db}' bukan *_e2e. Tidak ada yang diubah.\n";
    return;
}

$millA = BusinessUnit::where('name', 'Business Unit A')->firstOrFail();
$millB = BusinessUnit::where('name', 'Business Unit B')->firstOrFail();

function akun(string $username, string $role, ?string $buId): void
{
    $u = User::where('username', $username)->first();
    $data = [
        'name' => strtoupper($username),
        'username' => $username,
        'password_hash' => Hash::make('Passw0rd!'),
        'role' => $role,
        'business_unit_id' => $buId,
        'is_active' => true,
    ];
    if ($u) {
        $u->forceFill($data)->save();
        echo "  ubah  {$username}\n";
    } else {
        User::create($data);
        echo "  baru  {$username}\n";
    }
}

echo "Akun (§1.2):\n";
akun('supervisor-b', 'supervisor', $millB->id);     // SPV-B
akun('supervisor-nomill', 'supervisor', null);      // NOMILL

// LS-03 menuntut kedua mill punya record pada TANGGAL YANG SAMA. Tanpa itu,
// "jumlahnya berbeda" bisa saja karena tanggalnya berbeda, bukan karena
// isolasi mill bekerja — dan kasusnya tidak membuktikan apa pun.
$lineA = ProductionLine::where('business_unit_id', $millA->id)->orderBy('name')->firstOrFail();
$lineB = ProductionLine::where('business_unit_id', $millB->id)->orderBy('name')->firstOrFail();

$tanggalA = DB::table('weighbridge_records')
    ->where('production_line_id', $lineA->id)
    ->orderByDesc('record_datetime')
    ->value('record_datetime');

if ($tanggalA === null) {
    echo "LEWAT: Mill A belum punya record weighbridge — LS-03 tidak dapat disiapkan di sini.\n";
} else {
    $hari = substr((string) $tanggalA, 0, 10);
    $sudah = DB::table('weighbridge_records')
        ->where('production_line_id', $lineB->id)
        ->whereBetween('record_datetime', ["{$hari} 00:00:00", "{$hari} 23:59:59"])
        ->count();
    echo "LS-03: Mill A punya record pada {$hari}; Mill B punya {$sudah} pada hari itu.\n";
    if ($sudah === 0) {
        $contoh = (array) DB::table('weighbridge_records')
            ->where('production_line_id', $lineA->id)
            ->whereBetween('record_datetime', ["{$hari} 00:00:00", "{$hari} 23:59:59"])
            ->first();
        unset($contoh['id'], $contoh['created_at'], $contoh['updated_at']);
        $contoh['production_line_id'] = $lineB->id;
        $contoh['wb_card_number'] = 'QA-B-' . substr(md5($hari), 0, 6);
        // Nama estate HARUS khas Mill B: LS-03 memeriksa bahwa nama milik satu
        // mill tidak pernah tercetak di sesi mill lain, jadi nilainya harus
        // dapat dibedakan.
        if (array_key_exists('estate_supplier', $contoh)) {
            $contoh['estate_supplier'] = 'Estate Khusus Mill B';
        }
        $contoh['id'] = (string) \Illuminate\Support\Str::uuid();
        $contoh['created_at'] = now();
        $contoh['updated_at'] = now();
        DB::table('weighbridge_records')->insert($contoh);
        echo "  tambah 1 record Mill B pada {$hari}\n";
    }
}

echo "Production Line: Mill A=" . ProductionLine::where('business_unit_id', $millA->id)->count()
   . " line, Mill B=" . ProductionLine::where('business_unit_id', $millB->id)->count() . " line"
   . "  (LS-08 memerlukan satu mill ber-1 line dan satu ber-2 line)\n";
echo "selesai.\n";

// ── Periode untuk Mill B ───────────────────────────────────────────────────
// LS-08 langkah 4 menuntut angka laporan MUNCUL pada mill yang hanya punya satu
// Production Line. Tanpa periode, laporan mill itu tidak akan pernah
// menampilkan angka — bukan karena aturan pemilihan line, tetapi karena tidak
// ada periode yang bisa dipilih. Periodenya disalin dari bentuk periode Mill A.
$acuan = \App\Models\Period::with('stations')->where('business_unit_id', $millA->id)->first();
if ($acuan === null) {
    echo "LEWAT periode Mill B: Mill A pun tidak punya periode acuan.\n";
} elseif (\App\Models\Period::where('business_unit_id', $millB->id)->exists()) {
    echo "Periode Mill B: sudah ada.\n";
} else {
    $baru = \App\Models\Period::create([
        'business_unit_id' => $millB->id,
        'name' => $acuan->name,
        'start_date' => $acuan->start_date,
        'end_date' => $acuan->end_date,
        'created_by' => \App\Models\User::where('username', 'admin')->value('id'),
    ]);   // status TIDAK diisi di sini: sejak 2026-09-25 status ada per jenis
          // stasiun di period_stations, dan Period menolak pengisiannya.
    foreach ($acuan->stations as $st) {
        \App\Models\PeriodStation::create([
            'period_id' => $baru->id,
            'station_type' => is_object($st->station_type) ? $st->station_type->value : $st->station_type,
            'status' => 'draft',
        ]);
    }
    echo "Periode Mill B: dibuat '{$baru->name}' dengan {$acuan->stations->count()} jenis stasiun.\n";
}
echo "selesai (periode).\n";

// ── Production Line TANPA stasiun aktif (WB-FRM-07, MB-FORM-15) ───────────
// Kedua kasus itu menguji penolakan "line ini tidak punya stasiun <X> aktif".
// Kedua line Business Unit A buatan seeder punya ke-18 stasiun, jadi tanpa
// line ini keduanya mustahil dijalankan — bukan karena sistemnya, karena
// keadaannya tidak ada. Line ini sengaja dibiarkan KOSONG: tidak satu pun
// stasiun dibuat untuknya.
//
// Ditaruh di Business Unit A karena akun OPR-A dan SPV-A terikat mill itu —
// line di mill lain tidak akan pernah muncul sebagai pilihan bagi mereka.
// Tidak merusak LS-08: kasus itu hanya menuntut Business Unit B punya TEPAT
// satu line, dan B tidak disentuh di sini.
$millA2 = BusinessUnit::where('name', 'Business Unit A')->first();
if ($millA2 === null) {
    echo "LEWAT line tanpa stasiun: Business Unit A tidak ditemukan.\n";
} elseif (ProductionLine::where('business_unit_id', $millA2->id)->where('name', 'QA Line Tanpa Stasiun')->exists()) {
    echo "Line tanpa stasiun: sudah ada.\n";
} else {
    $line = ProductionLine::create([
        'business_unit_id' => $millA2->id,
        'name' => 'QA Line Tanpa Stasiun',
        'code' => 'QA-LINE-KOSONG',
        'description' => 'Dipakai WB-FRM-07 dan MB-FORM-15: line tanpa stasiun aktif.',
        'created_by' => User::where('username', 'admin')->value('id'),
    ]);
    echo "Line tanpa stasiun: dibuat '{$line->name}' (0 stasiun).\n";
}
echo "selesai (line tanpa stasiun).\n";

// ── Mill tanpa Production Line + Operator di dalamnya (MB-HOME-04) ────────
// Kasus itu memeriksa bahwa Station List memberi ARAHAN, bukan layar kosong,
// ketika mill si Operator belum punya satu line pun. Mill A dan B keduanya
// punya line, jadi tanpa fixture ini kasusnya mustahil dijalankan.
//
// Mill ini sengaja TIDAK diberi line, dan akunnya TIDAK dipakai kasus lain —
// kasus isolasi mill (LS-03/05/09) memakai SPV-A dan SPV-B, bukan akun ini.
$company = \App\Models\Company::query()->orderBy('name')->first();
if ($company === null) {
    echo "LEWAT mill tanpa line: tidak ada Company sebagai induk.\n";
} else {
    $millKosong = BusinessUnit::where('code', 'QA-MILL-NOLINE')->first();
    if ($millKosong === null) {
        $millKosong = BusinessUnit::create([
            'company_id' => $company->id,
            'name' => 'QA Mill Tanpa Line',
            'code' => 'QA-MILL-NOLINE',
        ]);
        echo "Mill tanpa line: dibuat '{$millKosong->name}'.\n";
    } else {
        echo "Mill tanpa line: sudah ada.\n";
    }
    $jmlLine = ProductionLine::where('business_unit_id', $millKosong->id)->count();
    echo "  line pada mill itu: {$jmlLine} (harus 0)\n";
    akun('operator-noline', 'operator', $millKosong->id);   // OPR-NOLINE
}
echo "selesai (mill tanpa line).\n";
