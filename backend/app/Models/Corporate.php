<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corporate — top of the org hierarchy: Corporate -> Company -> BusinessUnit -> Station.
 *
 * Constraint: name harus unik (enforced by unique index on `corporates.name`).
 */
class Corporate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
