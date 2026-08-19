<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company — belongs to a Corporate, has many Business Units.
 *
 * Constraints: `name` unik dalam satu corporate (enforced by unique index
 * on `companies.(corporate_id, name)`, UNCHANGED by the entity-catalog v4
 * rework) and `company_code` unik secara global (enforced by a plain
 * unique index on `companies.company_code`, added by the entity-catalog
 * v4 rework, screen-028--kelola-company 3-tech-spec ver 2,
 * docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid) — two
 * independent uniqueness rules coexisting on this table simultaneously.
 * See App\Services\CompanyService::validateCompany() for how both are
 * enforced together.
 */
class Company extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'corporate_id',
        'company_code',
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
        'last_update',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_update' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Corporate::class);
    }

    public function businessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class);
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
