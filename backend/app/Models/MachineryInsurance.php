<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MachineryInsurance — a child row of Machinery, brand-new for
 * screen-031--kelola-machinery / entity-catalog v4. Managed directly
 * inside the Machinery form with replace-all semantics on update — see
 * App\Services\MachineryService::update()'s docblock. No independent CRUD
 * screen, no controller/route of its own.
 */
class MachineryInsurance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'machinery_id',
        'ownership',
        'insurance_policy_no',
        'insurance_company',
        'insurance_expiry_date',
        'premium',
        'amount_insured',
    ];

    protected $casts = [
        'insurance_expiry_date' => 'date',
        'premium' => 'float',
        'amount_insured' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(Machinery::class);
    }
}
