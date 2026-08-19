<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corporate — top of the org hierarchy: Corporate -> Company -> BusinessUnit -> Station.
 *
 * Constraint: name harus unik (enforced by unique index on `corporates.name`).
 * `corporate_code` is also unique (entity-catalog v4 rework,
 * screen-027--kelola-corporate 3-tech-spec ver 2) — a separate business
 * identifier from both `id` (still the uuid primary key) and `name`.
 */
class Corporate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'corporate_code',
        'name',
        'short_name',
        'leader_name',
        'lawyer_name',
        'address',
        'telephone_no',
        'fax_no',
        'contact_no',
        'extension_no',
        'email',
        'website',
        'map',
        'tax_register_no',
        'insurance_no',
        'epf_employer',
        'socso_employer',
        'labor_union',
        'logo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
