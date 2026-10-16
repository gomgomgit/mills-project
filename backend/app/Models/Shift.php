<?php

namespace App\Models;

use App\Services\ShiftService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shift — jadwal shift kerja PER PRODUCTION LINE
 * (screen-170--kelola-shift / usecase-176--kelola-shift, entity-catalog
 * entitas `shift`).
 *
 * MELEKAT PADA PRODUCTION LINE, BUKAN PADA MILL: satu mill dapat punya
 * belasan line dengan jadwal kerja yang berbeda-beda. Tumpang tindih antar
 * shift karena itu hanya diperiksa DI DALAM satu `production_line_id` — dua
 * line boleh punya shift pada jam yang sama persis, keduanya memang bekerja
 * bersamaan di line yang berbeda.
 *
 * JADWALNYA BERLAKU SAMA SETIAP HARI (keputusan pemilik produk 2026-10-15).
 * Tidak ada kolom hari dalam seminggu, tidak ada masa berlaku, dan tidak ada
 * pola bergilir — pertanyaan "jam sekian itu shift apa pada line ini" selalu
 * punya jawaban yang sama tanpa perlu mengetahui tanggalnya. Yang bergilir di
 * lapangan adalah REGU yang mengisi shift, bukan batas jamnya.
 *
 * `end_time` BOLEH LEBIH AWAL DARIPADA `start_time` — itu berarti shift
 * melewati tengah malam dan ditampilkan '23:00 — 07:00 +1'. KARENA ITU
 * JANGAN PERNAH MEMBANDINGKAN KEDUA KOLOM INI SEBAGAI STRING: '23:00' >
 * '07:00' sebagai string menyimpulkan rentangnya kosong, dan hasil salahnya
 * terlihat masuk akal. Seluruh perbandingan rentang hidup di
 * {@see ShiftService} pada SUMBU MENIT 0..1440 relatif 07:00
 * (awal hari produksi) — satu penghitung, dipakai controller API maupun
 * komponen Livewire.
 *
 * `start_time`/`end_time` DIBIARKAN SEBAGAI STRING, bukan di-cast ke
 * Carbon. Alasannya: PostgreSQL memulangkan kolom TIME sebagai 'HH:MM:SS'
 * sementara SQLite memulangkan apa pun yang tersimpan, dan meng-cast-nya ke
 * datetime akan menempelkan tanggal palsu yang lalu ikut dibandingkan.
 * ShiftService::minuteOfDay() menormalkan keduanya ke menit; itu satu-satunya
 * tempat format jam dibaca.
 *
 * PENGHAPUSAN ADALAH SOFT DELETE (keputusan pemilik produk 2026-10-16).
 * `deleted_at` terisi berarti shift DITUTUP: ia hilang dari daftar, dari
 * timeline, dari perhitungan cakupan, dari pemilih pada form, dan dari
 * himpunan pembanding tumpang tindih — tetapi barisnya tetap ada, karena
 * `shift_id` akan mendarat di kedelapan belas tabel record dan record lama
 * harus tetap dapat menyelesaikan rujukannya agar laporan historis tetap
 * terbaca.
 *
 * KEEMPAT JALUR YANG HIDUP DIKECUALIKAN OLEH GLOBAL SCOPE trait ini, bukan
 * oleh whereNull() yang ditebar di pemanggil — seluruhnya berangkat dari
 * Shift::query() (lihat ShiftService). SATU pengecualian yang TIDAK dijangkau
 * global scope: Rule::unique() membangun kueri Query Builder, bukan Eloquent,
 * sehingga ia menambahkan whereNull('deleted_at') sendiri di
 * ShiftService::validate() — dan partial unique index di
 * 2026_10_16_000001 menyepakatinya di sisi basis data.
 *
 * `deleted_at` ADALAH SATU-SATUNYA KOLOM YANG DI-CAST di model ini. $casts
 * tetap tidak dideklarasikan untuk start_time/end_time (alasannya di atas);
 * deleted_at memang timestamp sungguhan, jadi penanganan trait SoftDeletes
 * atasnya benar dan tidak menyentuh kolom jam sama sekali.
 */
class Shift extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'production_line_id',
        'name',
        'start_time',
        'end_time',
        'created_by',
        'updated_by',
    ];

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }
}
