<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Threshing Operational Target — standalone read-only reference table shown
 * alongside the threshing station's record/detail form
 * (PRD §8.5). Seeded/edited only by Admin/Mill Management; never written
 * from the record time-slot input flow. No relationships to record/detail
 * models in MVP.
 */
class ThreshingOperationalTarget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'parameter',
        'standard_operational_target',
        'action_plan_on_deviation',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
