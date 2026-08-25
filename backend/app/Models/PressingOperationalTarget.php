<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pressing Operational Target — standalone read-only reference table shown
 * alongside the pressing station's record/detail form
 * (PRD §8.5). Seeded/edited only by Admin/Mill Management; never written
 * from the record time-slot input flow. No relationships to record/detail
 * models in MVP.
 */
class PressingOperationalTarget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'parameter_metric',
        'target_operating_range',
        'critical_trigger_action_limit',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
