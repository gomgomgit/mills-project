---
name: test-spec-writer-agent
description: Derive all test specifications for one screen from Phase 3 tech spec data. Produces unit_test_cases (per api_contract) and test_scenarios (integration, component, browser) from bdd_scenarios. Does not write artifacts — returns derived data to the caller.
tools:
  - mcp__asdlc__artifact__read
---

## Responsibilities

This agent derives all test specifications for one screen:
- `unit_test_cases` per usecase — from `business_logic` branching in `api_contracts`
- `test_scenarios` — from Phase 2 `bdd_scenarios` mapped to `api_test`, `component_test`, and `browser_test`

It is NOT responsible for: writing artifacts, HITL interaction, dep-graph operations, or git commands.

## Input Parameters

The caller provides:
- `api_contracts` — list of api_contract entries (already derived in Phase 3 Steps 5–6); each entry has `usecase_id`, `usecase_name`, `endpoints`, `business_logic`, `edge_case_handling`, `data_operations`
- `usecase_artifacts` — map of `usecase_id → usecase artifact data` (pre-loaded by caller); each artifact has `bdd_scenarios`, `main_flow`, `actors`
- `arch_spec` — full arch-spec artifact (to detect frontend presence via `tech_stack.frontend`)
- `screen_draft` — screen tech spec draft (to read `route`, `name`)
- `is_api_system` — boolean; if false, skip unit_test_cases and api_test derivation

## Process

### Step 1 — Derive unit_test_cases

For each entry in `api_contracts`:

If `is_api_system = false` → set `unit_test_cases = []` for this entry. Skip to next.

Scan each step in `business_logic` for branching conditions (look for `→ if`, `→ when`, `→ unless`, or similar conditional language). Each branch = one unit test case.

For each branch, construct:
- `description` — short label, e.g. "returns 401 when user not found by email"
- `given` — mock/stub state before calling the service method, e.g. "userRepo.findByEmail() returns null"
- `expect` — expected return value or thrown exception, e.g. "throw UnauthorizedException with code INVALID_CREDENTIALS"

Also add one happy-path case:
- `description` — "returns success result when all conditions pass"
- `given` — all dependencies return valid data
- `expect` — describe expected return value

Save as `unit_test_cases_map`: `{ usecase_id → [unit_test_cases] }`

### Step 2 — Collect bdd_scenarios

For each entry in `api_contracts`, read `usecase_artifacts[usecase_id].bdd_scenarios`.

> ⚠ **Guard**: If ALL collected `bdd_scenarios` across all usecases are empty:
> - Set `test_scenarios = []`
> - Return result with warning: `"warn": "No BDD scenarios found for any use case on this screen. Integration and browser tests cannot be derived. Re-run Phase 2 for the affected use cases."`
> - Skip Steps 3–4.

### Step 3 — Detect frontend

Check `arch_spec.tech_stack.frontend`:
- If empty or null → `has_frontend = false`
- Otherwise → `has_frontend = true`

### Step 4 — Derive test_scenarios

For each `bdd_scenario` in each usecase's `bdd_scenarios`, derive one `test_scenarios` entry:

**`scenario_ref`** → `bdd_scenario.scenario`
**`usecase_id`** → the usecase this scenario belongs to

**`api_test`** (array of steps — fully auto-derived):

Determine how many API calls are needed by reading the bdd `when` description and the usecase `main_flow`:
- **Single-step scenario** (one endpoint, most common): produce one step object.
- **Multi-step scenario** (e.g. place order then submit payment): produce multiple step objects in execution order.

For each step:
- `step` → sequential integer starting at 1
- `endpoint` → match bdd_scenario to the api_contract endpoint: happy-path → first applicable endpoint; error/edge scenario → same endpoint, different expected result
- `request_example` → construct from `body_schema` fields using bdd `when` as guide. For steps after step 1, use `{{stepN.field}}` notation to reference prior step response fields.
- `expected_status` → happy-path: 200 (or 201 for creation); error scenario: match `error_codes[].http_code` whose condition matches bdd `when`
- `expected_error_code` → null for success steps; match `error_codes[].error_code` for error steps

If `is_api_system = false` → set `api_test = []`.

**`component_test`** (semi-derived):
- If `has_frontend = false` → set `component_test = {}`. Skip.
- `component` → derive from screen name (e.g. "Login" → "LoginForm", "Register" → "RegistrationForm")
- `action` → paraphrase bdd `when` into UI interaction terms (e.g. "fill valid email and password, click submit")
- `assert` → paraphrase bdd `then` into UI assertion terms (e.g. "loading state shown, redirect triggered")

**`browser_test`** (semi-derived):
- If `has_frontend = false` → set `browser_test = {}`. Skip.
- `route` → copy from `screen_draft.route`
- `action` → paraphrase bdd `when` into browser interaction terms
- `assert` → paraphrase bdd `then` into browser assertion terms

### Step 5 — Return result

Return:
```
{
  "unit_test_cases_map": { "<usecase_id>": [<unit_test_cases>], ... },
  "test_scenarios":      [<test_scenarios>],
  "warn":                "<warning message or null>"
}
```
