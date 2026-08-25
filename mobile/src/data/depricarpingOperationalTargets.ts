/**
 * depricarpingOperationalTargets — screen-043--form-depricarping /
 * screen-047--data-preview-depricarping. Static reference data for the
 * read-only "Target Operasional" table rendered below the Depricarping
 * Detail grid on both screens.
 *
 * JUDGMENT CALL (agent, not spec-literal, following Threshing/Pressing's
 * precedent at mobile/src/data/{threshing,pressing}OperationalTargets.ts):
 * the entity-catalog's `depricarping-operational-target` entity is a real
 * server-side table (seeded/edited by Admin/Mill Management on web), but
 * this MVP's mobile app has no existing "pull a small read-only reference
 * table down and cache it locally" sync mechanism except for
 * `station`/`mill_setting`. Building a mechanism for 6 rarely-changing rows
 * was judged out of proportion to the value, matching the decision already
 * made for Threshing/Pressing — these 6 rows are fixed operational-reference
 * content copied verbatim from the task-provided source (mirroring the
 * paper log-sheet), not user data. If Admin ever edits these values via the
 * web Mills Setting/Depricarping Operational Target management flow (not in
 * this task's scope), this mobile constant will silently go stale until the
 * next app release updates it — documented here and in this screen's
 * known_issues/deferred list.
 *
 * STRUCTURAL DIFFERENCE FROM THRESHING/PRESSING: this table has 4 columns
 * (parameter_metric / target_range / critical_limit /
 * operational_consequence_justification) — one more than
 * Threshing/Pressing's 3-column shape (parameter_metric /
 * target_operating_range / critical_trigger_action_limit, no separate
 * justification column). Field names match entity-catalog's
 * `depricarping-operational-target` entity exactly.
 */
export interface DepricarpingOperationalTargetRow {
  parameter_metric: string
  target_range: string
  critical_limit: string
  operational_consequence_justification: string
}

export const DEPRICARPING_OPERATIONAL_TARGETS: DepricarpingOperationalTargetRow[] = [
  {
    parameter_metric: 'Fan Static Pressure',
    target_range: '40 - 50 mmH2O',
    critical_limit: '< 35 or > 55 mmH2O',
    operational_consequence_justification:
      'Low pressure drops fibre early (heavy losses). High pressure sucks clean small nuts into the fibre cyclone.',
  },
  {
    parameter_metric: 'Polishing Drum Speed',
    target_range: '20 - 24 RPM',
    critical_limit: '< 18 or > 26 RPM',
    operational_consequence_justification:
      'Slower speeds fail to detach residual mesocarp fibre from nuts. Higher speeds cause premature mechanical wear.',
  },
  {
    parameter_metric: 'Air Velocity (Aspirator)',
    target_range: '12 - 14 m/s',
    critical_limit: '< 10 or > 16 m/s',
    operational_consequence_justification:
      'Controls the pneumatic separation gap. Must cleanly lift light fiber hulls while letting heavy polished nuts sink.',
  },
  {
    parameter_metric: 'Fibre Moisture Content',
    target_range: '33% - 37%',
    critical_limit: '> 40%',
    operational_consequence_justification:
      'High moisture reduces downstream boiler combustion efficiency and indicates poor press station performance.',
  },
  {
    parameter_metric: 'Kernel Loss in Fibre',
    target_range: '< 0.50%',
    critical_limit: '> 1.00%',
    operational_consequence_justification:
      'Direct operational revenue loss. Signifies an unstable pneumatic lifting balance or unstripped cake clumps.',
  },
  {
    parameter_metric: 'Nut Silo Temperature',
    target_range: '60°C - 70°C',
    critical_limit: '< 55°C or > 75°C',
    operational_consequence_justification:
      'Crucial for nut conditioning. Correct heat shrinks the kernel inside the shell, enabling high-efficiency cracking.',
  },
]

export default DEPRICARPING_OPERATIONAL_TARGETS
