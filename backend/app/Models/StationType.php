<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Station Type (master data) — the kinds of station a mill can have.
 *
 * Added 2026-09-22 to replace the CHECK-constraint-plus-enum arrangement that
 * required a migration every time a station type was added. Adding a type is
 * now an INSERT here; `stations.type` and
 * `period_stations.station_type` both carry a foreign key to this table's
 * `code` column (`periods.station_type` did too until 2026-09-25, when the
 * column moved to `period_stations`).
 *
 * NOT TO BE CONFUSED WITH App\Enums\StationType, which still exists and is
 * still correct to use. The division is:
 *
 *   - This model  → the set of types is DATA. Use it to validate input
 *                   (Rule::exists), to fill dropdowns, and to render labels.
 *   - The enum    → individual types are CODE. Use it when logic names one
 *                   specific type, e.g. StationType::CagesTrack->value, so a
 *                   typo is a fatal error rather than a silent mismatch.
 *
 * The two must stay in sync. StationTypeCatalogTest asserts that every enum
 * case exists as a row here, so a case added to one without the other fails
 * the suite rather than surfacing in production.
 */
class StationType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Stations of this type. Joined on `code`, not on `id` — see the
     * create_station_types_table migration for why the FK points there.
     */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class, 'type', 'code');
    }

    /**
     * Baris period_stations yang menunjuk jenis stasiun ini — satu per periode
     * yang mengelolanya, beserta status tutup/bukanya.
     *
     * Sebelum 2026-09-25 relasi ini menunjuk `periods` langsung, dan sebuah
     * periode ber-`station_type` NULL berarti "mencakup semua jenis dan tidak
     * dimiliki jenis mana pun". Kolom itu — beserta seluruh konsep NULL =
     * semua-stasiun — sudah tidak ada: cakupan semua-stasiun kini dinyatakan
     * lewat adanya satu baris period_stations per jenis stasiun.
     */
    public function periodStations(): HasMany
    {
        return $this->hasMany(PeriodStation::class, 'station_type', 'code');
    }
}
