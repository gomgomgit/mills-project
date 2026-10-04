<?php

namespace App\Services;

use App\Enums\Uom;
use App\Models\GradingParameter;

/**
 * GradingParameterService — sumber baca master Quality Parameter untuk
 * aplikasi mobile (audit 2026-10-04, KRITIS).
 *
 * Mobile sebelumnya hanya punya baris buatan `default-grading-parameter-N`
 * hasil seed lokal; id itu ikut terkirim ke POST /api/grading-records dan
 * ditolak PostgreSQL (22P02 → 500). mobile/src/services/
 * gradingParameterSync.ts kini menarik daftar ini untuk memetakan baris
 * lokal ke id ASLI server berdasarkan nama.
 *
 * Master ini global (tidak terikat mill) — sama seperti di Form Grading web —
 * jadi tidak ada penyaringan mill di sini.
 */
class GradingParameterService
{
    /**
     * @return list<array{id: string, name: string, uom: string|null, sort_order: int}>
     */
    public function listForMobile(): array
    {
        return GradingParameter::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'uom', 'sort_order'])
            ->map(fn (GradingParameter $parameter) => [
                'id' => $parameter->id,
                'name' => $parameter->name,
                'uom' => $parameter->uom instanceof Uom ? $parameter->uom->value : $parameter->uom,
                'sort_order' => (int) $parameter->sort_order,
            ])
            ->values()
            ->all();
    }
}
