/**
 * kernelPlantOperationalTargets — screen-044--form-kernel-plant /
 * screen-048--data-preview-kernel-plant. Static reference data for the
 * read-only "Target Operasional" table rendered below the Kernel Plant
 * Detail grid on both screens.
 *
 * JUDGMENT CALL (agent, not spec-literal, following Threshing/Pressing/
 * Depricarping's precedent at
 * mobile/src/data/{threshing,pressing,depricarping}OperationalTargets.ts):
 * the entity-catalog's `kernel-plant-operational-target` entity is a real
 * server-side table (seeded/edited by Admin/Mill Management on web), but
 * this MVP's mobile app has no existing "pull a small read-only reference
 * table down and cache it locally" sync mechanism except for
 * `station`/`mill_setting`. Building a mechanism for 6 rarely-changing rows
 * was judged out of proportion to the value, matching the decision already
 * made for Threshing/Pressing/Depricarping — these 6 rows are fixed
 * operational-reference content copied verbatim from the task-provided
 * source, not user data. If Admin ever edits these values via the web
 * Mills Setting/Kernel Plant Operational Target management flow (not in
 * this task's scope), this mobile constant will silently go stale until the
 * next app release updates it — documented here and in this screen's
 * known_issues/deferred list.
 *
 * STRUCTURAL SHAPE MATCHES THRESHING/PRESSING (not Depricarping): this
 * table has 3 columns (equipment_parameter / target_benchmark /
 * corrective_action_plan) — same shape as Threshing's/Pressing's 3-column
 * table (parameter / standard_operational_target /
 * action_plan_on_deviation), NOT Depricarping's 4-column shape. Field names
 * match entity-catalog's `kernel-plant-operational-target` entity exactly.
 */
export interface KernelPlantOperationalTargetRow {
  equipment_parameter: string
  target_benchmark: string
  corrective_action_plan: string
}

export const KERNEL_PLANT_OPERATIONAL_TARGETS: KernelPlantOperationalTargetRow[] = [
  {
    equipment_parameter: 'Ripple Mill (Cracker)',
    target_benchmark: '20 - 25 Amps (Nut Breakage >95%)',
    corrective_action_plan: 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
  },
  {
    equipment_parameter: 'Claybath / Hydrocyclone',
    target_benchmark: 'Specific Gravity 1.18 - 1.24',
    corrective_action_plan: 'Verify calcium carbonate mixture if kernels float with shell.',
  },
  {
    equipment_parameter: 'Kernel Silo 1 & 2',
    target_benchmark: '70°C - 80°C (Top/Middle zones)',
    corrective_action_plan: 'Check heater elements/steam valves if temperature drops below 65°C.',
  },
  {
    equipment_parameter: 'Final Kernel Moisture',
    target_benchmark: '≤ 7.0% (Prevents mold growth)',
    corrective_action_plan: 'Increase retention time or adjust silo air flow rates.',
  },
  {
    equipment_parameter: 'Final Kernel Dirt',
    target_benchmark: '≤ 6.0% (Standard quality premium)',
    corrective_action_plan: 'Clean winnowing ducts or re-calibrate hydrocyclone settings.',
  },
  {
    equipment_parameter: 'Shell Bin Kernel Loss',
    target_benchmark: '≤ 1.5% (Maximized separation recovery)',
    corrective_action_plan: 'Reduce air velocity or inspect separator screen meshes.',
  },
]

export default KERNEL_PLANT_OPERATIONAL_TARGETS
