---
description: Phase 3-Tech-Spec — Deep-dive technical specification for one screen
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

You are running the `asdlc-p3:tech-2-screen` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 3 · Tech Spec — 2-screen                      ║
╚══════════════════════════════════════════════════════╝
```

You are acting as a **Solution Architect**. Your perspective is technical — you translate business requirements into precise API contracts, data operations, and business logic. You reference the entity catalog, arch spec, and shared decisions throughout. You do not re-interview business requirements — you derive technical structure from them.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — all three must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.arch-spec`
  - `project.3-tech-spec.entity-catalog`
  - `project.3-tech-spec.shared-decisions`
  If any is `"not_started"` → STOP.
  Report: "Pre-condition not met: [key] has not been written. Run [command] first."
  (arch-spec → `/asdlc-p1:fnd-2-arch-spec`
   entity-catalog + shared-decisions → `/asdlc-p3:tech-1-core`)

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means; see `.claude/PATTERNS.md` § HITL Gate vs Digest. Both Step 7c (test scenario confirm) and Section 3 (main gate) digest at `autopilot`, blocking only at `careful`. Additionally, Step 6 (refine loop) runs at `careful` and is skipped at `autopilot`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Select Screen & Load Context

### Step 1 — Load Schemes

Call `mcp__asdlc__artifact__read_scheme("project.3-tech-spec.api-index")` — save as `api_index_scheme`.
Call `mcp__asdlc__artifact__read_scheme("module-x.screen-x.3-tech-spec")` — save as `screen_tech_scheme`.
(The placeholder key resolves to `template/3-tech-spec/screen.json` — the phase suffix determines the template, not the module/screen IDs.)

### Step 2 — Read All Context

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `arch_spec`.

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `prd`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.entity-catalog")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `entity_catalog`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.shared-decisions")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `shared_decisions`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.actor-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `actor_index`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.api-index")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `api_index_existing_ver = 0`. Set `api_index = {"endpoints": [], "meta": {}, "ver": 0}`.
- `{"data": {...}}` → Save as `api_index`. Set `api_index_existing_ver = data["ver"]`.

Detect `is_api_system`:
- `true` if `arch_spec.system_type` contains any of: "Web", "SPA", "PWA", "API", "REST" (case-insensitive)
- `false` otherwise (mobile native, desktop, CLI, etc.)

### Step 3 — Select Screen

**If `target_screen` was already provided by a calling command (e.g. `/asdlc-fast-screen`)** → skip this step entirely, use the provided value as `target_screen`, and go directly to Step 4.

Otherwise, present the list of screens from `screen_index.screens`, grouped by module.
For each screen, check `artifact_index`:
- `{module_id}.{screen_id}.2-business-spec` status: mark `[no biz-spec]` if not written
- `{module_id}.{screen_id}.3-tech-spec` status: mark `[tech ✓]` if already written

> **Which screen would you like to specify technically?**
>
> [Module Name]
>   1. [screen name] ([screen ID])  [no biz-spec if missing]  [tech ✓ if done]
>   2. ...
>
> Type the number of your choice.

Wait for the user's answer. Save the selected screen as `target_screen`.

### Step 4 — Load Screen Artifacts

Construct artifact keys using `target_screen.module_id` and `target_screen.id`.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.2-business-spec")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → STOP. "Business spec for this screen has not been written yet. Run `/asdlc-p2:bus-2-screen` first."
- Save as `screen_biz_spec`.

For each `usecase_id` in `screen_biz_spec.usecase_ids`, attempt:
`mcp__asdlc__artifact__read("project.2-business-spec.usecases.{usecase_id}")`
Collect results as `usecase_artifacts` (keyed by usecase_id). Skip null results silently.

Call `mcp__asdlc__dep_graph__get_stale_nodes` and check if `"{target_screen.module_id}.{target_screen.id}.2-business-spec"` is in the result.
If stale:
> ⚠ The business spec for this screen is stale — an upstream artifact has changed since it was last written. The tech spec will be based on potentially outdated information. Continue? (Y/N)
If N → STOP.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.3-tech-spec")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `existing_tech_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `existing_tech_spec`. Set `existing_tech_ver = data["ver"]`. Mode: **update**.

If **update** mode:
1. Display current tech spec clearly.
2. Ask: **Which sections do you want to update?** (e.g. api_contracts, actor_permissions — or "all")
3. Initialize `derived_assumptions = []` before conducting any of the steps below (Section 3's
   audit pass references it, so it must exist even if Step 5 itself is skipped for unselected
   sections).
4. For each selected section, conduct the relevant steps from Section 2.
5. Carry over unselected sections unchanged.
6. Skip to Section 3 (HITL Gate or Digest) with updated data pre-filled.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Draft & Refine

### Step 5 — Synthesize Draft

**Autonomy fast-path — if Autonomy level is `autopilot`:** still perform the synthesis and
`derived_assumptions` logging described in this step (the draft is needed to write the artifact),
but do NOT present the draft with a "how does this look?" question and do NOT run Step 6's refine
loop — treat the synthesized draft as accepted and proceed directly to Step 7. **Otherwise
(`careful`)** — present the draft and refine as described below.

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution (this one screen). Even if this command is running inside a
sequencer (`fast-screen`) and other screens'/commands' assumptions are still visible earlier in
the conversation, do not include them — those belong to different artifacts and are already
logged under their own files (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — Step 6's refine loop and any later REVISE must not re-run this line and wipe out
entries already logged.

Using all loaded context, synthesize a complete first-cut tech spec for this screen. Everything
in this step is technical derivation, not something the business spec states directly — log
each significant choice (route format, auth requirement, business-logic translation, edge-case
handling) to `derived_assumptions` as you derive it, unless it's a mechanical, unambiguous
translation (e.g. "GET" for a read action) that no reasonable alternative would change.

**Route:**
Derive from `screen_biz_spec.name` and `shared_decisions.naming_conventions.api_endpoints`.
For non-web platforms: use a descriptive view identifier (e.g. `OrderDetailView`).

**Auth requirement:**
Derive from `screen_biz_spec.actors`, `screen_biz_spec.business_rules`, and `shared_decisions.auth.mechanism`.
Examples: "public", "authenticated", "role: admin".

**Actor permissions:**
For each actor in `screen_biz_spec.actors`, derive:
- `can_access`: based on business rules and actor roles
- `conditions`: any additional conditions from business rules (e.g. "only owns the resource")

**API contracts (only if `is_api_system = true`):**
For each usecase_id in `screen_biz_spec.usecase_ids`:
- Load from `usecase_artifacts[usecase_id]` if available; otherwise derive from `screen_biz_spec.available_actions` and `screen_biz_spec.business_rules`
- Derive endpoint(s): HTTP method + path from the usecase's main_flow + action semantics
  - Read/list → GET · Create → POST · Replace → PUT · Partial update → PATCH · Remove → DELETE
- Request shape: derive from `main_flow` input steps + `preconditions`
- Response shape: derive from `postconditions` + `screen_biz_spec.information_displayed`
- Business logic: translate `main_flow` steps to backend pseudo-steps using arrow notation
  - Format: `"1. Validate request → if invalid: return 400 VALIDATION_ERROR"`, `"2. Check ownership → if not owner: return 403 FORBIDDEN"`, etc.
- Data operations: identify which entities from `entity_catalog` are touched and how
- Edge cases: translate `alternative_flows` + `screen_biz_spec.edge_cases` to technical handling
  - Format: `{ condition: "Email already registered", handling: "Return 409 CONFLICT with code EMAIL_ALREADY_EXISTS" }`
- Business rules applied: list rules from `usecase.business_rules` that are enforced in this logic

**Shared entities:**
Identify entities referenced by more than one usecase in this screen.

**Screen dependencies:**
Identify screens or external services this screen depends on at runtime.

**Implementation notes:**
Note anything specific — caching, concurrency, external calls, file uploads, complex queries, security concerns.

Present the full draft:

> **Tech Spec Draft: [Screen Name]**
>
> **Route:** [route]  **Auth:** [auth_requirement]
>
> **Actor Permissions:**
> [for each: actor name — ✅ can access (conditions) / ❌ no access]
>
> **[UC Name]** ([usecase_id])
> `[METHOD /path]` — [description]
> Request: [brief summary of key params/body]
> Response: [brief summary of success shape]
> Logic ([N] steps): [numbered list]
> Data ops: [entity: operation, ...]
> Edge cases: [N] | Rules applied: [N]
>
> [repeat for each UC]
>
> **Shared entities:** [list or "none"]
> **Screen dependencies:** [list or "none"]
> **Implementation notes:** [list or "none"]
>
> How does this look?
>
> A) Looks right — accept and continue ✓
> B) Adjust — tell me what's off, missing, or needs more detail

### Step 6 — Refine

(Skipped entirely at `autopilot` — see the Step 5 fast-path.)

Based on the user's response, refine section by section or UC by UC — one topic per turn.

**Question style — present options, not open text.** For any question whose answer is enumerable or a confirm/adjust, present labeled options (`A)`/`B)`/`C)` …, recommended first + `✓`, always with a final `Other — [describe]`) so the user answers with a letter — see `.claude/PATTERNS.md` § Interview Question Style. Derive options from context; keep free-text only for genuinely open inputs (naming, free lists, describing a correction).

For each UC flagged by the user:
- API endpoint: method, path, auth required
- Request: path params, query params, body schema with field-level validation (type, required, constraints)
- Response: success schema shape, error codes per scenario
- Performance: cache strategy, rate limit
- Business logic: ordered steps with conditional branches
- Data operations: entity, operation type, description
- Edge case handling: condition + technical resolution
- Business rules applied: confirm list

For actor permissions: cross-check with `screen_biz_spec.available_actions` — actor_ids per action inform who can call which endpoints.

Sections the user has confirmed are final — do not revisit unless explicitly requested.

### Step 7 — Schema Coverage Check

Review `screen_tech_scheme._tracked`. For each tracked field, verify the current draft has a non-empty value.

If any tracked field is empty and cannot be derived from context or discussion, ask the user — one field at a time.

If `is_api_system = false`: set `api_contracts = []` — do not ask for API details.

If all tracked fields are populated, proceed to Section 2b.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2b — Test Scenario Derivation & Confirm

### Step 7b — Delegate to test-spec-writer-agent

Delegate to `test-spec-writer-agent` with:
```
api_contracts     = <api_contracts from Steps 5–6>
usecase_artifacts = <usecase_artifacts from Step 4>
arch_spec         = <arch_spec from Step 2>
screen_draft      = <current screen draft>
is_api_system     = <is_api_system from Step 2>
```

Wait for result. Save:
- `result.unit_test_cases_map` — apply to each api_contract: set `api_contracts[i].unit_test_cases = unit_test_cases_map[usecase_id]`
- `result.test_scenarios` — save as `test_scenarios`
- `result.warn` — if not null, display warning to user before proceeding

### Step 7c — Confirm Test Scenarios or Digest

**If Autonomy level is `careful`:**

Display the derived test data for user review:

> **Test Scenarios — [Screen Name]**
>
> **Unit Test Cases**
> [for each api_contract:]
> Usecase: [usecase_name]
>   [for each unit_test_case:]
>   · [description]
>     Given: [given]
>     Expect: [expect]
>
> **Integration / API Test Scenarios**
> [for each test_scenario:]
> · [scenario_ref] ([usecase_id])
>   API: [method] [path] → [expected_status] [expected_error_code or "—"]
>   Request: [request_example as inline JSON]
>
> **Component Test Scenarios** [or "N/A — no frontend"]
> [for each test_scenario:]
> · [scenario_ref]
>   Component: [component]
>   Action: [action]
>   Assert: [assert]
>
> **Browser Test Scenarios** [or "N/A — no frontend"]
> [for each test_scenario:]
> · [scenario_ref]
>   Route: [route]
>   Action: [action]
>   Assert: [assert]
>
> **CONFIRM / REVISE [unit_test_cases | api_test | component_test | browser_test]**

- **CONFIRM** → proceed to Section 3 (HITL Gate or Digest)
- **REVISE [section]** → ask for corrections to that section only, re-display this block

**If Autonomy level is `autopilot`:**

Skip the blocking prompt — accept the derived test data as-is, proceed directly to Section 3.
Log a one-line summary to `derived_assumptions` ("test scenarios derived: [N] unit, [N] API,
[N] component, [N] browser") — these are entirely agent-derived from Phase 2 BDD scenarios, and
a wrong one is caught automatically when the test runs in Phase 4, which is why this is safe to
digest.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — HITL Gate or Digest

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished tech spec once
against the business spec and interview transcript (Steps 5–7c). Confirm every
`derived_assumptions` entry is genuinely not stated, and spot-check the rest. Add any missed
entries now.

**If Autonomy level is `careful`:**

Display a complete summary:

> **Tech Spec: [Screen Name]** ([target_screen.id])
>
> **Route:** [route]  **Auth:** [auth_requirement]
>
> **Actor Permissions:**
> [for each: actor name — ✅/❌ (conditions)]
>
> **Use Cases:** [count]
> [for each UC: [usecase_id] · [name]]
>   Endpoints: [N] · Logic steps: [N] · Data ops: [list entity:operation] · Edge cases: [N]
>
> **Shared entities:** [list or "none"]
> **Screen dependencies:** [list or "none"]
> **Implementation notes:** [count or "none"]
>
> **GO / REVISE [section] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section]** → corrections to that section only, return to Step 6, re-display full summary
- **STOP** → stop here, do nothing

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block. Continue without waiting. If the user corrects something afterward, apply the
inline-correction + versioning rule from `.claude/PATTERNS.md`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Write Artifacts

Set `meta.title` to `prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

### Step 8 — Write Screen Tech-Spec

Data source:
- New (`existing_tech_ver == 0`) → use all data from Steps 5–7b/7c/7d.
- Update (`existing_tech_ver > 0`) → for selected sections, use interview data; for unselected sections, carry over from `existing_tech_spec` verbatim. Re-derive `unit_test_cases` and `test_scenarios` only if `api_contracts` was in the selected sections.

Set `id = target_screen.id`. Set `name = target_screen.name`. Set `module_id = target_screen.module_id`.
Set `ver` to `existing_tech_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "{target_screen.module_id}.{target_screen.id}.3-tech-spec",
  data         = <constructed screen tech-spec data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `screen_tech_path` and `screen_tech_changed_fields` from result.

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/{target_screen.module_id}.{target_screen.id}.3-tech-spec.md`
(treat as empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it
back. Skip entirely if `derived_assumptions` is empty.

### Step 9 — Update api-index

Extract all endpoints from `api_contracts` written in Step 8.
For each UC → for each endpoint, build an entry:
- `method`, `path`, `description` from the endpoint
- `screen_id = target_screen.id`
- `usecase_id` = the UC's usecase_id
- `auth_required` = endpoint's auth_required
- `actor_ids` = actors who can access this screen (from `screen_biz_spec.actors`), filtered to those who can perform this UC's action

Merge into `api_index`:
- Remove any existing entries where `screen_id == target_screen.id` (idempotent replace)
- Append the new entries
- Set `meta.title = prd.meta.title`, `meta.updated_at = today`
- Set `ver = api_index_existing_ver + 1`

```
mcp__asdlc__artifact__write(
  artifact_key = "project.3-tech-spec.api-index",
  data         = <updated api-index>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `api_index_path` and `api_index_changed_fields` from result.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 10 — Invoke dep-graph-sync-agent × 2

Invoke `dep-graph-sync-agent` twice in this order. Wait for each to confirm before continuing.

```
1. artifact_key   = "{target_screen.module_id}.{target_screen.id}.3-tech-spec"
   changed_fields = <screen_tech_changed_fields>
   depends_on     = [
     "self.2-business-spec",
     "project.1-foundation.arch-spec",
     "project.3-tech-spec.entity-catalog",
     "project.3-tech-spec.shared-decisions"
   ]

2. artifact_key   = "project.3-tech-spec.api-index"
   changed_fields = <api_index_changed_fields>
   depends_on     = ["project.2-business-spec.screen-index"]
```

If either dep-graph-sync-agent call reports an error → STOP. Report error verbatim.

### Step 11 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_tech_ver was 0]: Wrote tech spec for <target_screen.name> (<target_screen.id>) (new)
  [if existing_tech_ver > 0]:   Updated tech spec for <target_screen.name> — changed: <screen_tech_changed_fields>
  API index updated — <count> endpoints for this screen

Artifacts written
  <module_id>.<screen_id>.3-tech-spec  v<ver>  ([new / updated])
  project.3-tech-spec.api-index        v<ver>  (updated)

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  [if screens remain without 3-tech-spec]: /asdlc-p3:tech-2-screen  (next screen)
  [if all screens done]:                    /asdlc-p4:impl-1-core
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Always synthesize a draft first — never open with blank questions
- One topic (one UC or one section) per turn during refinement
- At Autonomy level `autopilot`: skip Step 6 refine (and the Step 5 draft-feedback question) — accept the synthesized draft and proceed to Step 7; the draft's derived choices are already logged as derived assumptions
- At Autonomy level `careful`: never skip the test scenario confirmation (Section 2b) — always wait for CONFIRM before proceeding to HITL Gate. Step 7c has no STOP option — REVISE loops back, CONFIRM proceeds.
- At Autonomy level `careful`: never skip the HITL gate — always wait for GO before writing. Do not continue to Section 4 if the user answers STOP at the HITL Gate.
- At `autopilot`: both points become non-blocking digests (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass at the start of Section 3 — it is what catches `derived_assumptions` entries missed during Steps 5–7c, regardless of Autonomy level
- Do not continue if the selected screen has no business spec written (Step 4 returns data: null)
- Do not continue if the user answers N at the stale warning in Step 4
- Do not continue to Step 9 if Step 8 (screen tech-spec write) returns an error
- Do not continue to Step 10 if Step 9 (api-index write) returns an error
- Do not continue to Step 11 if either dep-graph-sync-agent call reports an error
- In update mode: carry over unchanged sections from `existing_tech_spec` verbatim — do not re-derive them
- If `is_api_system = false`: set `api_contracts = []`; still write `api-index` (replacing any existing entries for this screen with an empty set)
- `api-index` update always replaces all entries for `target_screen.id` — idempotent; safe to rerun
- Do not discuss business requirements — derive tech structure from business spec, not by re-interviewing the user about what the screen does
- Use entity IDs from `entity_catalog` when specifying data operations — do not invent entity names
