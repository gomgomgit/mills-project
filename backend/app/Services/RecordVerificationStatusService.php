<?php

namespace App\Services;

use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Database\Eloquent\Model;

/**
 * RecordVerificationStatusService — pasangan BACA dari PATCH
 * /api/records/{stationType}/{id}/verification (audit 2026-10-04, SEDANG).
 *
 * Sync mobile hanya satu arah (push), jadi operator terus melihat "Belum
 * diperiksa" setelah Supervisor memverifikasi lewat web. mobile/src/services/
 * recordVerificationApi.ts pullVerificationStatus() memanggil ini untuk
 * menarik status terbaru record yang sudah tersinkron.
 *
 * Peta jenis stasiun → model TIDAK diduplikasi: dipinjam dari
 * RecordVerificationService::modelForStationType() (whitelist eksplisit yang
 * sama dengan jalur tulisnya).
 *
 * Cakupan mill memakai ScopesToActorMill::scopeQueryToActorMill() — trait yang
 * sama dengan Data Browser/Detail: Admin bebas, aktor lain dibatasi mill-nya,
 * aktor non-Admin tanpa mill gagal-tertutup 422. Id milik mill lain
 * (atau yang tidak ada) cukup TIDAK MUNCUL di hasil — tidak 403, supaya
 * keberadaan record mill lain tidak terkonfirmasi.
 */
class RecordVerificationStatusService
{
    use ScopesToActorMill;

    public function __construct(protected RecordVerificationService $verificationService) {}

    /** @return class-string<Model>|null */
    public function modelForStationType(string $stationType): ?string
    {
        return $this->verificationService->modelForStationType($stationType);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $ids
     * @return list<array{id: string, checked_by: string|null, checked_by_name: string|null, acknowledged_by: string|null, acknowledged_by_name: string|null}>
     */
    public function statusesFor(string $modelClass, array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $query = $modelClass::query()
            ->whereIn('id', $ids)
            ->with(['checkedBy:id,name', 'acknowledgedBy:id,name']);

        return $this->scopeQueryToActorMill($query)
            ->get(['id', 'station_id', 'checked_by', 'acknowledged_by'])
            ->map(fn (Model $record) => [
                'id' => $record->id,
                'checked_by' => $record->checked_by,
                'checked_by_name' => $record->checkedBy?->name,
                'acknowledged_by' => $record->acknowledged_by,
                'acknowledged_by_name' => $record->acknowledgedBy?->name,
            ])
            ->values()
            ->all();
    }
}
