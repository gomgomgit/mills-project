<?php

namespace Database\Seeders;

use App\Models\KernelPlantOperationalTarget;
use Illuminate\Database\Seeder;

/**
 * Seeds the 6 canonical Kernel Plant Operational Target reference rows —
 * screen-044--form-kernel-plant / screen-048--data-preview-kernel-plant /
 * screen-056--detail-kernel-plant-web / screen-060--form-kernel-plant-web
 * display these read-only, below the Kernel Plant Detail grid. Idempotent
 * via firstOrCreate() keyed on `equipment_parameter` (mirrors
 * ThreshingOperationalTargetSeeder's `parameter`-keyed idempotency), so
 * re-running does not duplicate rows.
 *
 * STRUCTURAL SHAPE MATCHES THRESHING/PRESSING (not Depricarping): this
 * reference table has 3 columns (equipment_parameter / target_benchmark /
 * corrective_action_plan) — same shape as Threshing's/Pressing's 3-column
 * table, NOT Depricarping's 4-column shape. Content copied verbatim from
 * the task-provided reference source — not invented.
 */
class KernelPlantOperationalTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            [
                'equipment_parameter' => 'Ripple Mill (Cracker)',
                'target_benchmark' => '20 - 25 Amps (Nut Breakage >95%)',
                'corrective_action_plan' => 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
            ],
            [
                'equipment_parameter' => 'Claybath / Hydrocyclone',
                'target_benchmark' => 'Specific Gravity 1.18 - 1.24',
                'corrective_action_plan' => 'Verify calcium carbonate mixture if kernels float with shell.',
            ],
            [
                'equipment_parameter' => 'Kernel Silo 1 & 2',
                'target_benchmark' => '70°C - 80°C (Top/Middle zones)',
                'corrective_action_plan' => 'Check heater elements/steam valves if temperature drops below 65°C.',
            ],
            [
                'equipment_parameter' => 'Final Kernel Moisture',
                'target_benchmark' => '≤ 7.0% (Prevents mold growth)',
                'corrective_action_plan' => 'Increase retention time or adjust silo air flow rates.',
            ],
            [
                'equipment_parameter' => 'Final Kernel Dirt',
                'target_benchmark' => '≤ 6.0% (Standard quality premium)',
                'corrective_action_plan' => 'Clean winnowing ducts or re-calibrate hydrocyclone settings.',
            ],
            [
                'equipment_parameter' => 'Shell Bin Kernel Loss',
                'target_benchmark' => '≤ 1.5% (Maximized separation recovery)',
                'corrective_action_plan' => 'Reduce air velocity or inspect separator screen meshes.',
            ],
        ];

        foreach ($targets as $index => $target) {
            KernelPlantOperationalTarget::firstOrCreate(
                ['equipment_parameter' => $target['equipment_parameter']],
                [
                    'target_benchmark' => $target['target_benchmark'],
                    'corrective_action_plan' => $target['corrective_action_plan'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
