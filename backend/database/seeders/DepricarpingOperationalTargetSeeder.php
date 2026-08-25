<?php

namespace Database\Seeders;

use App\Models\DepricarpingOperationalTarget;
use Illuminate\Database\Seeder;

/**
 * Seeds the 6 canonical Depricarping Operational Target reference rows —
 * screen-043--form-depricarping / screen-047--data-preview-depricarping /
 * screen-055--detail-depricarping-web / screen-059--form-depricarping-web
 * display these read-only, below the Depricarping Detail grid. Idempotent
 * via firstOrCreate() keyed on `parameter_metric` (mirrors
 * PressingOperationalTargetSeeder's/ThreshingOperationalTargetSeeder's
 * `parameter_metric`/`parameter`-keyed idempotency), so re-running does not
 * duplicate rows.
 *
 * STRUCTURAL DIFFERENCE FROM THRESHING/PRESSING: this reference table has
 * 4 columns (parameter_metric / target_range / critical_limit /
 * operational_consequence_justification) — one more than
 * Threshing's/Pressing's 3-column shape (no separate justification
 * column). Content copied verbatim from the task-provided reference
 * source — not invented.
 */
class DepricarpingOperationalTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            [
                'parameter_metric' => 'Fan Static Pressure',
                'target_range' => '40 - 50 mmH2O',
                'critical_limit' => '< 35 or > 55 mmH2O',
                'operational_consequence_justification' => 'Low pressure drops fibre early (heavy losses). High pressure sucks clean small nuts into the fibre cyclone.',
            ],
            [
                'parameter_metric' => 'Polishing Drum Speed',
                'target_range' => '20 - 24 RPM',
                'critical_limit' => '< 18 or > 26 RPM',
                'operational_consequence_justification' => 'Slower speeds fail to detach residual mesocarp fibre from nuts. Higher speeds cause premature mechanical wear.',
            ],
            [
                'parameter_metric' => 'Air Velocity (Aspirator)',
                'target_range' => '12 - 14 m/s',
                'critical_limit' => '< 10 or > 16 m/s',
                'operational_consequence_justification' => 'Controls the pneumatic separation gap. Must cleanly lift light fiber hulls while letting heavy polished nuts sink.',
            ],
            [
                'parameter_metric' => 'Fibre Moisture Content',
                'target_range' => '33% - 37%',
                'critical_limit' => '> 40%',
                'operational_consequence_justification' => 'High moisture reduces downstream boiler combustion efficiency and indicates poor press station performance.',
            ],
            [
                'parameter_metric' => 'Kernel Loss in Fibre',
                'target_range' => '< 0.50%',
                'critical_limit' => '> 1.00%',
                'operational_consequence_justification' => 'Direct operational revenue loss. Signifies an unstable pneumatic lifting balance or unstripped cake clumps.',
            ],
            [
                'parameter_metric' => 'Nut Silo Temperature',
                'target_range' => '60°C - 70°C',
                'critical_limit' => '< 55°C or > 75°C',
                'operational_consequence_justification' => 'Crucial for nut conditioning. Correct heat shrinks the kernel inside the shell, enabling high-efficiency cracking.',
            ],
        ];

        foreach ($targets as $index => $target) {
            DepricarpingOperationalTarget::firstOrCreate(
                ['parameter_metric' => $target['parameter_metric']],
                [
                    'target_range' => $target['target_range'],
                    'critical_limit' => $target['critical_limit'],
                    'operational_consequence_justification' => $target['operational_consequence_justification'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
