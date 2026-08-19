<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Business Unit — belongs to a Company, has many Stations and Users.
 *
 * Constraint: code harus unik (enforced by unique index on `business_units.code`).
 */
class BusinessUnit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
