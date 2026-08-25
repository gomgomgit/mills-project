<?php

namespace Database\Seeders;

use App\Models\ThreshingOperationalTarget;
use Illuminate\Database\Seeder;

/**
 * Seeds the 6 canonical Threshing Operational Target reference rows —
 * screen-041--form-threshing / screen-045--data-preview-threshing /
 * screen-053--detail-threshing-web / screen-057--form-threshing-web display
 * these read-only, below the Threshing Detail grid. Idempotent via
 * firstOrCreate() keyed on `parameter` (mirrors GradingParameterSeeder's
 * `name`-keyed idempotency), so re-running does not duplicate rows.
 *
 * Content copied verbatim from the paper log-sheet reference source
 * (task-provided) — not invented. Note "Bearing Temperature" has no
 * corresponding live-reading column on threshing_detail (no sensor field in
 * MVP) — it is a reference-only row, included here exactly as the source
 * log sheet shows it, per explicit product direction.
 */
class ThreshingOperationalTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            [
                'parameter' => 'FFB Throughput',
                'standard_operational_target' => 'As per mill capacity design (e.g., 30-60 MT/hr)',
                'action_plan_on_deviation' => 'Adjust feeder conveyor speed.',
            ],
            [
                'parameter' => 'Thresher Drum Speed',
                'standard_operational_target' => '21 - 23 RPM (optimal for separation)',
                'action_plan_on_deviation' => 'Inspect drive belt tension and gearbox alignment.',
            ],
            [
                'parameter' => 'Motor Current',
                'standard_operational_target' => 'Within motor rated full-load current (FLC)',
                'action_plan_on_deviation' => 'Check for drum overloading or wedged bunches.',
            ],
            [
                'parameter' => 'Bearing Temperature',
                'standard_operational_target' => 'Below 70°C (Check if >75°C)',
                'action_plan_on_deviation' => 'Lubricate bearings / check for mechanical wear.',
            ],
            [
                'parameter' => 'Unstripped Bunch Rate',
                'standard_operational_target' => 'Target: 0% (Action required if >2%)',
                'action_plan_on_deviation' => 'Verify autoclaved sterilization pressure and duration.',
            ],
            [
                'parameter' => 'Empty Bunch (EB) Oil Loss',
                'standard_operational_target' => 'Target: <0.50% on dry basis',
                'action_plan_on_deviation' => 'Check thresher drum bars and inner lifting paddles.',
            ],
        ];

        foreach ($targets as $index => $target) {
            ThreshingOperationalTarget::firstOrCreate(
                ['parameter' => $target['parameter']],
                [
                    'standard_operational_target' => $target['standard_operational_target'],
                    'action_plan_on_deviation' => $target['action_plan_on_deviation'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
