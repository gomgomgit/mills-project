<?php

namespace App\Support\PeriodSplit;

/**
 * Hasil perencanaan {@see PeriodSplitConverter::plan()} — apa yang akan
 * ditulis migrasi 2026_09_26_000035 ke DB, dihitung penuh SEBELUM satu baris
 * pun disentuh.
 *
 * Objek ini sengaja dipisah dari eksekusinya supaya seluruh aturan
 * penggabungan, pemekaran cakupan NULL, dan de-duplikasi nama dapat diuji
 * tanpa DB sama sekali (lihat tests/Unit/Support/PeriodSplitConverterPlanTest).
 */
final class PeriodSplitPlan
{
    /**
     * @param  list<array{period_id: string, station_type: string, status: string, closed_by: string|null, closed_at: string|null, created_at: string|null, updated_at: string|null}>  $stationRows
     *                                                                                                                                                                                               Baris `period_stations` yang akan di-INSERT.
     * @param  list<array{id: string, from: string, to: string}>  $renames
     *                                                                      Periode yang namanya harus diubah supaya unique
     *                                                                      (business_unit_id, name) dapat dibuat migrasi 000040.
     * @param  list<string>  $deletedPeriodIds
     *                                          Baris `periods` yang lebur ke induk lain dan harus dihapus.
     * @param  list<string>  $survivorIds
     *                                     Baris `periods` yang bertahan sebagai induk.
     * @param  list<string>  $notes
     *                               Catatan untuk operator — setiap keputusan yang membuang
     *                               atau mengubah sesuatu WAJIB muncul di sini; tidak ada
     *                               yang boleh hilang diam-diam.
     */
    public function __construct(
        public readonly array $stationRows = [],
        public readonly array $renames = [],
        public readonly array $deletedPeriodIds = [],
        public readonly array $survivorIds = [],
        public readonly array $notes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->stationRows === []
            && $this->renames === []
            && $this->deletedPeriodIds === [];
    }
}
