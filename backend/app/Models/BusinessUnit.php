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
 * Constraint: code harus unik secara global (enforced by unique index on
 * `business_units.code`, pre-existing since
 * 2025_01_15_000003_create_business_units_table.php). `name` has NO
 * uniqueness rule at all on this entity — a deliberate divergence from
 * both Corporate (`name` globally unique) and Company (`name` unique
 * within `corporate_id`) — see App\Services\BusinessUnitService::validate().
 *
 * Entity-catalog v4 rework (screen-029--kelola-business-unit 3-tech-spec
 * ver 2, docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid): gained
 * the full identity/contact/legal-employment field set, a `logo` upload,
 * and `created_by`/`updated_by` audit columns — mirrors Corporate/Company's
 * same rework (2026_08_19_000003_add_fields_to_business_units_table.php).
 */
class BusinessUnit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'business_unit_type_code',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    /**
     * productionLines() — added 2026-08-20 (entity-catalog v9, Production
     * Line inserted between Business Unit and Station). `stations()` above
     * is DELIBERATELY left untouched — Station still keeps a denormalized
     * `business_unit_id` (see Station's own docblock), so this direct
     * relationship stays valid/queryable even though Production Line is
     * now Station's real hierarchical parent.
     */
    public function productionLines(): HasMany
    {
        return $this->hasMany(ProductionLine::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
