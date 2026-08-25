/**
 * pressingOperationalTargets — screen-042--form-pressing /
 * screen-046--data-preview-pressing. Static reference data for the
 * read-only "Target Operasional" table rendered below the Pressing Detail
 * grid on both screens.
 *
 * JUDGMENT CALL (agent, not spec-literal, following Threshing's precedent
 * at mobile/src/data/threshingOperationalTargets.ts): the entity-catalog's
 * `pressing-operational-target` entity is a real server-side table
 * (seeded/edited by Admin/Mill Management on web), but this MVP's mobile
 * app has no existing "pull a small read-only reference table down and
 * cache it locally" sync mechanism except for `station`/`mill_setting`.
 * Building a mechanism for 7 rarely-changing rows was judged out of
 * proportion to the value, matching the decision already made for
 * Threshing — these 7 rows are fixed operational-reference content copied
 * verbatim from the task-provided source (mirroring the paper log-sheet),
 * not user data. If Admin ever edits these values via the web Mills
 * Setting/Pressing Operational Target management flow (not in this task's
 * scope), this mobile constant will silently go stale until the next app
 * release updates it — documented here and in this screen's
 * known_issues/deferred list.
 *
 * Field names match entity-catalog's `pressing-operational-target` entity
 * exactly: parameter_metric / target_operating_range /
 * critical_trigger_action_limit (NOT Threshing's parameter /
 * standard_operational_target / action_plan_on_deviation naming — Pressing's
 * source log sheet uses different column headers).
 */
export interface PressingOperationalTargetRow {
  parameter_metric: string
  target_operating_range: string
  critical_trigger_action_limit: string
}

export const PRESSING_OPERATIONAL_TARGETS: PressingOperationalTargetRow[] = [
  {
    parameter_metric: 'Digester Temperature',
    target_operating_range: '90°C - 95°C',
    critical_trigger_action_limit: '< 85°C (Leads to poor oil liberation)',
  },
  {
    parameter_metric: 'Digester Fill Level',
    target_operating_range: '75% - 80% (Minimum 3/4 full)',
    critical_trigger_action_limit: '< 50% (Reduces retention time & friction)',
  },
  {
    parameter_metric: 'Screw Press Motor Current',
    target_operating_range: '35 - 45 Amperes',
    critical_trigger_action_limit: '> 50 Amps (Indicates choke or heavy load)',
  },
  {
    parameter_metric: 'Cone Hydraulic Pressure',
    target_operating_range: '45 - 55 Bar',
    critical_trigger_action_limit: '> 60 Bar (Increases nut breakage severely)',
  },
  {
    parameter_metric: 'Dilution Water Temperature',
    target_operating_range: '85°C - 90°C',
    critical_trigger_action_limit: '< 80°C (Causes poor oil-water separation)',
  },
  {
    parameter_metric: 'Nut Breakage Rate',
    target_operating_range: '< 10% to 12%',
    critical_trigger_action_limit: '> 15% (Adjust screw press cones backward)',
  },
  {
    parameter_metric: 'Press Cake Moisture',
    target_operating_range: '34% - 38%',
    critical_trigger_action_limit: '> 40% (Indicates insufficient pressing pressure)',
  },
]

export default PRESSING_OPERATIONAL_TARGETS
