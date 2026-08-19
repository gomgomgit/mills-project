<?php

namespace App\Models;

use App\Enums\Uom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grading Parameter — master data kategori kualitas grading (mis. Mentah,
 * Masak, Brondolan) yang dipilih per baris Grading Detail. 16 baris kanonis
 * di-seed via database/seeders/GradingParameterSeeder.php.
 */
class GradingParameter extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'uom',
        'sort_order',
    ];

    protected $casts = [
        'uom' => Uom::class,
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function gradingDetails(): HasMany
    {
        return $this->hasMany(GradingDetail::class);
    }
}
