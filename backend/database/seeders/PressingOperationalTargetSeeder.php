<?php

namespace Database\Seeders;

use App\Models\PressingOperationalTarget;
use Illuminate\Database\Seeder;

/**
 * Seeds the 7 canonical Pressing Operational Target reference rows —
 * screen-042--form-pressing / screen-046--data-preview-pressing /
 * screen-054--detail-pressing-web / screen-058--form-pressing-web display
 * these read-only, below the Pressing Detail grid. Idempotent via
 * firstOrCreate() keyed on `parameter_metric` (mirrors
 * ThreshingOperationalTargetSeeder's `parameter`-keyed idempotency), so
 * re-running does not duplicate rows.
 *
 * Content copied verbatim from the task-provided reference source — not
 * invented. Note "Nut Breakage Rate" and "Press Cake Moisture" have no
 * corresponding live-reading column on pressing_detail (no sensor field in
 * MVP) — these are reference-only rows, included here exactly as the
 * source log sheet shows them, per explicit product direction (same
 * precedent as Threshing's "Bearing Temperature" row).
 */
class PressingOperationalTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            [
                'parameter_metric' => 'Digester Temperature',
                'target_operating_range' => '90°C - 95°C',
                'critical_trigger_action_limit' => '< 85°C (Leads to poor oil liberation)',
            ],
            [
                'parameter_metric' => 'Digester Fill Level',
                'target_operating_range' => '75% - 80% (Minimum 3/4 full)',
                'critical_trigger_action_limit' => '< 50% (Reduces retention time & friction)',
            ],
            [
                'parameter_metric' => 'Screw Press Motor Current',
                'target_operating_range' => '35 - 45 Amperes',
                'critical_trigger_action_limit' => '> 50 Amps (Indicates choke or heavy load)',
            ],
            [
                'parameter_metric' => 'Cone Hydraulic Pressure',
                'target_operating_range' => '45 - 55 Bar',
                'critical_trigger_action_limit' => '> 60 Bar (Increases nut breakage severely)',
            ],
            [
                'parameter_metric' => 'Dilution Water Temperature',
                'target_operating_range' => '85°C - 90°C',
                'critical_trigger_action_limit' => '< 80°C (Causes poor oil-water separation)',
            ],
            [
                'parameter_metric' => 'Nut Breakage Rate',
                'target_operating_range' => '< 10% to 12%',
                'critical_trigger_action_limit' => '> 15% (Adjust screw press cones backward)',
            ],
            [
                'parameter_metric' => 'Press Cake Moisture',
                'target_operating_range' => '34% - 38%',
                'critical_trigger_action_limit' => '> 40% (Indicates insufficient pressing pressure)',
            ],
        ];

        foreach ($targets as $index => $target) {
            PressingOperationalTarget::firstOrCreate(
                ['parameter_metric' => $target['parameter_metric']],
                [
                    'target_operating_range' => $target['target_operating_range'],
                    'critical_trigger_action_limit' => $target['critical_trigger_action_limit'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
