<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MachineryTaxPurchase — a child row of Machinery, brand-new for
 * screen-031--kelola-machinery / entity-catalog v4. Managed directly
 * inside the Machinery form with replace-all semantics on update — see
 * App\Services\MachineryService::update()'s docblock. No independent CRUD
 * screen, no controller/route of its own.
 */
class MachineryTaxPurchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'machinery_id',
        'purchase_date',
        'purchase_cost',
        'policy_type',
        'contact_name',
        'contact_phone',
        'contact_fax',
        'contact_email',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function machinery(): BelongsTo
    {
        return $this->belongsTo(Machinery::class);
    }
}
