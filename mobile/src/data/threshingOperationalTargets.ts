/**
 * threshingOperationalTargets — screen-041--form-threshing /
 * screen-045--data-preview-threshing. Static reference data for the
 * read-only "Target Operasional" table rendered below the Threshing Detail
 * grid on both screens.
 *
 * JUDGMENT CALL (agent, not spec-literal): the entity-catalog's
 * `threshing-operational-target` entity is a real server-side table
 * (seeded/edited by Admin/Mill Management on web), but this MVP's mobile
 * app has no existing "pull a small read-only reference table down and
 * cache it locally" sync mechanism except for `station`/`mill_setting`
 * (see localSchema.ts's header comment — both of those ARE synced from the
 * server). Building a THIRD such mechanism for exactly 6 rarely-changing
 * rows, for this task alone, was judged out of proportion to the value —
 * these 6 rows are fixed operational-reference content copied verbatim
 * from the paper log-sheet source (task-provided), not user data. If Admin
 * ever edits these values via the web Mills Setting/Threshing Operational
 * Target management flow (not in this task's scope), this mobile constant
 * will silently go stale until the next app release updates it — documented
 * here and in this screen's known_issues/deferred list.
 */
export interface ThreshingOperationalTargetRow {
  parameter: string
  standard_operational_target: string
  action_plan_on_deviation: string
}

export const THRESHING_OPERATIONAL_TARGETS: ThreshingOperationalTargetRow[] = [
  {
    parameter: 'FFB Throughput',
    standard_operational_target: 'As per mill capacity design (e.g., 30-60 MT/hr)',
    action_plan_on_deviation: 'Adjust feeder conveyor speed.',
  },
  {
    parameter: 'Thresher Drum Speed',
    standard_operational_target: '21 - 23 RPM (optimal for separation)',
    action_plan_on_deviation: 'Inspect drive belt tension and gearbox alignment.',
  },
  {
    parameter: 'Motor Current',
    standard_operational_target: 'Within motor rated full-load current (FLC)',
    action_plan_on_deviation: 'Check for drum overloading or wedged bunches.',
  },
  {
    parameter: 'Bearing Temperature',
    standard_operational_target: 'Below 70°C (Check if >75°C)',
    action_plan_on_deviation: 'Lubricate bearings / check for mechanical wear.',
  },
  {
    parameter: 'Unstripped Bunch Rate',
    standard_operational_target: 'Target: 0% (Action required if >2%)',
    action_plan_on_deviation: 'Verify autoclaved sterilization pressure and duration.',
  },
  {
    parameter: 'Empty Bunch (EB) Oil Loss',
    standard_operational_target: 'Target: <0.50% on dry basis',
    action_plan_on_deviation: 'Check thresher drum bars and inner lifting paddles.',
  },
]

export default THRESHING_OPERATIONAL_TARGETS
