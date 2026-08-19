---
description: Phase 4-Implement — Implement one screen (routes, services, unit tests, FE components, FE tests)
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__get_stale_nodes
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p4:impl-2-screen` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 4 · Implement — 2-impl-screen                 ║
╚══════════════════════════════════════════════════════╝
```

You are acting as a **Lead Engineer**. Your focus is implementing one screen end-to-end — generating route/controller, service, BE unit test files, and FE component files — from its technical and design specs. You do not re-derive business requirements or technical decisions; you translate the specs into working code.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — all three must have `status: "written"` in `artifact_index`:
  - `project.4-implement.scaffold`
  - `project.4-implement.entity-models`
  - `project.4-implement.shared-modules`
  If any is `"not_started"` → STOP.
  Report: "Pre-condition not met: [key] has not been written. Run `/asdlc-p4:impl-1-core` first."

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Section 3's gate is blocking or a digest (digests at `autopilot`, blocking only at `careful`). Does **not** affect the `spec_mismatch` pause in Step 7 — that is a permanent exception, always blocking regardless of level. Additionally, Step 5–6 (plan review + refine) run at `careful` and are skipped at `autopilot`. See `.claude/PATTERNS.md` § HITL Gate vs Digest.

Note the Test Generation level — same file, key `test_generation_level` (default `"full"` if the file is not found). See `CLAUDE.md` §10 for what each level means. Passed to `screen-impl-agent` in Step 7: `full` = generate test files + run + auto-fix; `none` = no test files. At `none` the screen's final status is `"partial"` (unverified) and the `spec_mismatch` pause never triggers, since no tests are run.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Select Screen & Load Context

### Step 1 — Load Scheme

Call `mcp__asdlc__artifact__read_scheme("module-x.screen-x.4-implement")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_implement_scheme`.
(The placeholder key resolves to `template/4-implement/screen.json` — the phase suffix determines the template.)

### Step 2 — Read Shared Context

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `prd`.

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `arch_spec`.

Call `mcp__asdlc__artifact__read("project.1-foundation.uiux-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- `{"data": null}` → Set `uiux_spec = null`. FE generation will proceed without design system context.
- `{"data": {...}}` → Save as `uiux_spec`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.entity-catalog")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `entity_catalog`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.shared-decisions")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `shared_decisions`.

Call `mcp__asdlc__artifact__read("project.4-implement.entity-models")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `entity_models`.

Call `mcp__asdlc__artifact__read("project.4-implement.shared-modules")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `shared_modules`.

Call `mcp__asdlc__artifact__read("project.4-implement.scaffold")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `scaffold`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

Call `mcp__asdlc__artifact__read("project.1-foundation.test-strategy")`.
- If result contains `"error"` → STOP. Report error verbatim.
- `{"data": null}` → STOP. "Test strategy has not been written yet. Run `/asdlc-p1:fnd-4-test-strategy` first."
- Save as `test_strategy`.

### Step 3 — Select Screen

**If `target_screen` was already provided by a calling command (e.g. `/asdlc-fast-screen`)** → skip this step entirely, use the provided value as `target_screen`, and go directly to Step 4.

Otherwise, present the list of screens from `screen_index.screens`, grouped by module.
For each screen, check `artifact_index`:
- `{module_id}.{screen_id}.3-tech-spec` status: mark `[no tech-spec]` if not written
- `{module_id}.{screen_id}.4-implement` status: mark `[impl ✓]` if already written

> **Which screen would you like to implement?**
>
> [Module Name]
>   1. [screen name] ([screen ID])  [no tech-spec if missing]  [impl ✓ if done]
>   2. ...
>
> Type the number of your choice.

Wait for the user's answer. Save as `target_screen`.

### Step 4 — Load Screen Artifacts

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.3-tech-spec")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → STOP. "Tech spec for this screen has not been written yet. Run `/asdlc-p3:tech-2-screen` first."
- Save as `screen_tech_spec`.

Call `mcp__asdlc__dep_graph__get_stale_nodes`. Save result as `stale_nodes`.

If `"{target_screen.module_id}.{target_screen.id}.3-tech-spec"` is in `stale_nodes`:
> ⚠ The tech spec for this screen is stale — an upstream artifact has changed. The implementation will be based on potentially outdated information. Continue? (Y/N)
If N → STOP.

If `"project.4-implement.shared-modules"` is in `stale_nodes`:
> ⚠ Shared modules are stale — shared-decisions has changed since shared-modules were last generated. Some shared module imports in this screen may be outdated. Consider running `/asdlc-p4:impl-1-core` first to update shared modules. Continue anyway? (Y/N)
If N → STOP.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.4-implement")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `existing_impl_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `existing_impl`. Set `existing_impl_ver = data["ver"]`. Mode: **update**.

If **update** mode:
1. Display current implement artifact clearly (status, files, test files, fe files, fe test files, notes, deferred, known issues).
2. Ask: **What would you like to update?** (e.g. re-implement a use case, resolve a deferred item, fix a known issue, regenerate FE, **add/complete tests without regenerating code** — or "all")
   - **If the user chooses "add/complete tests (keep existing code)":** set `skip_code_generate = true` and force the full test flow for this run (`test_generation_level = "full"`, regardless of the config value) — the existing code is kept as-is; test-writer generates tests, test-runner runs them, and auto-fix corrects code only where tests fail. Valid because update mode means the screen is already implemented. For every other scope, `skip_code_generate = false`.
3. Carry over unselected fields unchanged.
4. Jump to Section 2 with scope of update pre-defined.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Implementation Plan

### Step 5 — Synthesize Plan

**Autonomy fast-path — if Autonomy level is `autopilot`:** still synthesize the full plan
described in this step (it drives Section 4), but do NOT present it with an "anything to adjust?"
question and do NOT run Step 6's refine loop — proceed directly to Section 3 with the synthesized
plan. **Otherwise (`careful`)** — present the plan and refine as described below.

Using `screen_tech_spec`, `entity_models`, `shared_modules`, `arch_spec`, `shared_decisions`, and `uiux_spec`, synthesize a complete implementation plan before engaging the user.

**BE files to generate:**
For each use case in `screen_tech_spec.api_contracts`:
- Route/controller file: one file per screen (or per module if multiple screens share a router). Derive path from `shared_modules`'s project structure and `shared_decisions.naming_conventions`.
- Service file: one file per use case, or one file per screen if use cases are closely related.
- BE unit test file: one per route/controller file. Derive path from `shared_modules.test_infrastructure`.

**FE files to generate:**
Derive FE framework from `arch_spec.tech_stack`. For each screen:
- FE component file: one component or template file per screen. Derive path from tech stack convention (e.g. `src/pages/`, `src/views/`, `templates/`).
- FE test file: one per FE component file.

**Test files to generate:**
Derive from `screen_tech_spec`:
- BE unit test file: one per service file — cases sourced from `api_contracts[].unit_test_cases`
- Integration test file: one per screen — scenarios sourced from `test_scenarios[].api_test` (each scenario's array of steps becomes test steps in the file)
- Component test file: one per FE component — scenarios sourced from `test_scenarios[].component_test` (skip if no frontend)
- Browser test file: one per screen — scenarios sourced from `test_scenarios[].browser_test` (skip if no frontend)

> ⚠ **Guard**: If `test_scenarios` is empty → mark integration, component, and browser test files as **deferred** with reason: "test_scenarios empty — re-run Phase 2 for missing bdd_scenarios, then re-run Phase 3".

Build a `test_files_to_generate` map (used in Step 7 to instruct `test-writer-agent`):
```json
{
  "integration": { "path": "<derived path>", "deferred": false },
  "component":   [{ "path": "<derived path>", "deferred": false }],
  "browser":     { "path": "<derived path>", "deferred": false }
}
```
If `test_scenarios` is empty: set `"deferred": true` and add `"reason": "test_scenarios empty — no BDD scenarios"` to each integration, component, and browser entry.

Show `test_strategy.auto_fix.max_retries` in the plan so the user knows how many auto-fix attempts will be made if tests fail.

**Reflect `test_generation_level` in the plan:** at `full`, test files are generated and run (auto-fix up to `max_retries`). At `none`, no test files are generated — state that the screen will be marked `"partial"` (unverified) as a result.

**Deferred items (identify upfront):**
Flag any use case, endpoint, or FE file that cannot be fully implemented:
- Missing shared module (not in `shared_modules.modules_implemented`)
- Missing entity model (not in `entity_models.entities_implemented`)
- Integration client not yet generated
- FE framework not determinable from `arch_spec.tech_stack`

**Status estimate:**
- `"complete"` if no deferred items
- `"partial"` if some use cases or FE files deferred

Present the plan:

> **Implementation Plan: [target_screen.name]** ([target_screen.id])
>
> **BE files to generate ([N]):**
> [for each: path — what it contains]
>
> **FE files to generate ([N]):**
> [for each: path — what it contains]
>
> **Use case coverage ([N] of [total]):**
> [for each UC: usecase_id — endpoints: [N] — logic steps: [N] — BE tests: [N] — FE tests: [N]]
>
> **Deferred ([N or "None"]):**
> [for each: description — reason]
>
> **Estimated status:** [complete / partial]
>
> Ready to generate?
>
> A) Looks good — generate the code ✓
> B) Adjust — tell me what to change first

### Step 6 — Refine

(Skipped entirely at `autopilot` — see the Step 5 fast-path.)

Based on the user's response, adjust one topic at a time. Confirmed items are final. Present adjustment questions as labeled options where enumerable (see `.claude/PATTERNS.md` § Interview Question Style).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — HITL Gate or Digest

**If Autonomy level is `careful`:**

Display the confirmed plan:

> **Implementation Plan (confirmed): [target_screen.name]** ([target_screen.id])
>
> **BE files to generate ([N]):**
> [list: path — description]
>
> **FE files to generate ([N]):**
> [list: path — description]
>
> **Use cases covered:** [N] ([list usecase IDs])
> **Deferred:** [N or "None"]
> **Estimated status:** [complete / partial]
>
> **GO / REVISE [section] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section]** → adjustments to that section only, re-display confirmed plan
- **STOP** → stop here, do nothing

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait) using the plan as finalized in Step 6. The Review
Digest is shown once implementation is complete — see Step 10. This does not affect the
`spec_mismatch` pause in Step 7, which always stays blocking.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Generate & Write

### Step 7 — Invoke screen-impl-agent (orchestrator)

Set `skip_code_generate = false` unless the Step 4 update scope set it to `true` (the "add/complete tests (keep existing code)" choice).

Delegate to `screen-impl-agent` with:

```
target_screen          = <target_screen>
screen_tech_spec       = <screen_tech_spec>
arch_spec              = <arch_spec>
entity_catalog         = <entity_catalog>
shared_decisions       = <shared_decisions>
entity_models          = <entity_models>
shared_modules         = <shared_modules>
scaffold_entry_points  = <scaffold.entry_points>
uiux_spec              = <uiux_spec>
test_strategy          = <test_strategy>
unit_test_cases        = <screen_tech_spec.api_contracts[].unit_test_cases — per usecase>
test_scenarios         = <screen_tech_spec.test_scenarios>
test_fixtures          = <entity_catalog.entities[].test_fixture — all entities>
implementation_plan    = {
  "files_to_generate":      <confirmed BE file list from Steps 5–6>,
  "fe_files_to_generate":   <confirmed FE file list from Steps 5–6>,
  "test_files_to_generate": <test_files_to_generate map from Step 5>,
  "deferred_items":         <confirmed list from Steps 5–6>
}
test_generation_level  = <test_generation_level>
skip_code_generate     = <skip_code_generate — true only for the "add tests" update scope, else false>
```

The orchestrator internally manages a `code-writer-agent` → `test-writer-agent` → `test-runner-agent` flow:
1. `code-writer-agent` generates all BE + FE implementation files
2. `test-writer-agent` generates all test files (unit, integration, component, browser)
3. `test-runner-agent` runs all tests and reports pass/fail
4. If tests fail: `code-writer-agent` is re-invoked in fix mode → `test-runner-agent` retests
5. Loop repeats up to `test_strategy.auto_fix.max_retries` times
6. Policies applied by orchestrator: `on_environment_error: "stop"`, `on_spec_mismatch: "pause"`, `on_implementation_error: "auto_fix"`
7. After loop ends: status set to `"complete"` if all tests pass and coverage >= threshold; otherwise `"partial"`

Gating by `test_generation_level`: at `none`, the orchestrator runs only step 1 (no test files) and sets status `"partial"` (unverified) with a documented `known_issue`; the `spec_mismatch` pause cannot trigger since no tests run. When `skip_code_generate = true` (add-tests scope), the orchestrator skips code generation (step 1) and starts at test generation — existing code is kept, and only auto-fix edits it where tests fail.

Wait for the orchestrator to complete. If it reports `environment_error` → STOP. Report error verbatim.
If it reports `spec_mismatch` → display mismatch details to user and ask how to proceed before re-invoking agent.
**Permanent exception (`CLAUDE.md` §8): this pause is always blocking, at every Autonomy level including `autopilot` — it never becomes a digest.**

Save from agent result:
- `files_generated` — BE implementation files written (relative paths)
- `test_files_generated` — BE unit test files written (relative paths)
- `fe_files_generated` — FE component/template files written (relative paths)
- `fe_test_files_generated` — FE unit test files written (relative paths)
- `implementation_notes` — notes from agent
- `known_issues` — issues flagged during generation
- `status` — "complete", "partial", or "wip"
- `test_results` — test execution results per type (run_at, passed, failed, coverage)"

**Build `derived_assumptions`** (see `.claude/PATTERNS.md` § Derived Assumptions Log) — initialize as a
fresh, empty list scoped strictly to this command's current execution (this one screen); even
if running inside `fast-screen` with other screens' assumptions visible earlier in the
conversation, do not include them. This command doesn't derive fields from scratch the way
Phase 1–3 commands do (the tech spec is implemented as-is), so there's no field-by-field
logging step here. Instead, treat each entry
in `implementation_notes` and `known_issues` that reflects a choice not explicit in the tech
spec (e.g. a specific error-handling approach the spec didn't cover) as a `derived_assumptions`
entry — `{field: <short label>, value: <the choice>, reason: <why it wasn't in the spec>}`.
Entries reflecting a straightforward translation of the spec don't count. No audit pass here —
the agent's own report is the source, not a draft to re-read.

### Step 8 — Write screen implement artifact

Set `meta.title = prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).
Set `id = target_screen.id`. Set `name = target_screen.name`. Set `module_id = target_screen.module_id`.

Data source:
- New (`existing_impl_ver == 0`) → use all data from Steps 5–7 (include `test_results` from agent).
- Update → for updated fields, use agent data; carry over unselected fields from `existing_impl` verbatim. Merge `files_generated`, `test_files_generated`, `fe_files_generated`, and `fe_test_files_generated` (do not drop previously generated files — append new ones).

Set `ver = existing_impl_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "{target_screen.module_id}.{target_screen.id}.4-implement",
  data         = <constructed implement data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `impl_path` and `impl_changed_fields` from result.

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/{target_screen.module_id}.{target_screen.id}.4-implement.md`
(treat as empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it
back. Skip entirely if `derived_assumptions` is empty.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 9 — Invoke dep-graph-sync-agent

Invoke `dep-graph-sync-agent` with:

```
artifact_key   = "{target_screen.module_id}.{target_screen.id}.4-implement"
changed_fields = <impl_changed_fields>
depends_on     = [
  "self.3-tech-spec",
  "project.4-implement.entity-models",
  "project.4-implement.shared-modules"
]
```

Wait for confirmation. If it reports an error → STOP. Report error verbatim.

### Step 10 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_impl_ver was 0]: Implemented <target_screen.name> (<target_screen.id>) — status: <status>
  [if existing_impl_ver > 0]:   Updated implementation for <target_screen.name> — changed: <impl_changed_fields>
  BE: <count> impl files, <count> test files
  FE: <count> component files, <count> test files
  [if test_generation_level == "none"]:       Tests skipped (test_generation_level = none) — status partial
  [if deferred items > 0]: <N> items deferred — see TODOs in generated files

Artifacts written
  <module_id>.<screen_id>.4-implement  v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

[if Autonomy level is `autopilot` — this box is the Review Digest for this
 command, see `.claude/PATTERNS.md` § HITL Gate vs Digest:]
⚠ Derived by the agent — not stated explicitly by you
  [render the `derived_assumptions` list built in Step 7; omit this block entirely if empty]

Recommended next
  [if screens remain without 4-implement]: /asdlc-p4:impl-2-screen  (next screen)
  [if all screens done]:                    All screens implemented — project complete
```

[if Autonomy level is `autopilot`:] Anything off? Say so — I'll fix it now. If
the user corrects something, apply the inline-correction + versioning rule from `.claude/PATTERNS.md`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Always synthesize an implementation plan first — never open with blank questions
- One topic per turn during refinement
- At Autonomy level `autopilot`: skip the Step 5 plan-review question and the Step 6 refine loop — proceed to Section 3 with the synthesized plan. The `spec_mismatch` pause (Step 7) still stays blocking.
- At Autonomy level `careful`: never skip the Section 3 HITL gate — always wait for GO before generating any code. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Section 3 becomes non-blocking — proceed directly to Section 4, then show the Review Digest folded into Step 10's summary (§ HITL Gate vs Digest in `.claude/PATTERNS.md`). Correct inline if the user interrupts. This does not affect the `spec_mismatch` pause in Step 7, which always stays blocking.
- Never skip building `derived_assumptions` from `implementation_notes`/`known_issues` in Step 7 — there is no separate audit pass in this command, so this is the only chance to catch what the agent assumed
- Do not continue if the selected screen has no tech spec written (Step 4 returns data: null)
- Do not continue if the user answers N at either stale warning in Step 4
- Do not continue to Step 8 if screen-impl-agent reports an error
- Do not continue to Step 9 if Step 8 (implement artifact write) returns an error
- Do not continue to Step 10 if dep-graph-sync-agent reports an error
- If uiux-spec is not written (uiux_spec == null): pass null to screen-impl-agent — do not stop
- In update mode: merge `files_generated`, `test_files_generated`, `fe_files_generated`, and `fe_test_files_generated` — never drop previously generated files
- In update mode: carry over unchanged fields from `existing_impl` verbatim — do not re-generate them
- Do not implement deferred items — add a TODO comment in the relevant file and skip
- Do not re-derive business requirements or re-interview the user — implement from the tech spec as-is
- Use entity model paths from `entity_models.files_generated` for imports — do not guess paths
- Use shared module paths from `shared_modules.files_generated` (BE) and `shared_modules.fe_files_generated` (FE) for imports — do not guess paths
- Respect `test_generation_level` (read in Pre-Flight, passed to screen-impl-agent): `full` = generate + run + auto-fix; `none` = no tests. At `none`, status is `"partial"` and the `spec_mismatch` pause does not apply (no tests run).
- The "add/complete tests (keep existing code)" update scope sets `skip_code_generate = true` and forces the full test flow — the orchestrator skips code regeneration and only auto-fixes code where tests fail. All other scopes regenerate code as before.
- Never modify test files to make tests pass — only fix implementation code (on_implementation_error policy)
- If agent reports on_spec_mismatch: pause and show mismatch to user before re-invoking agent — do not auto-resolve spec conflicts
- If agent reaches max_retries without all tests passing: set status = "partial", record failures in known_issues, continue to Step 8
