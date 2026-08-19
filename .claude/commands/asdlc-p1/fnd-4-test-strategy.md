---
description: Phase 1-Foundation — Generate or update Test Strategy
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p1:fnd-4-test-strategy` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 1 · Foundation — 4-test-strategy              ║
╚══════════════════════════════════════════════════════╝
```

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — the following keys must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.prd`
  - `project.1-foundation.arch-spec`
  If any are "not_started" → STOP. "Pre-condition not met: [key] has not been written. Run [command] first."
    - prd → `/asdlc-p1:fnd-1-prd`
    - arch-spec → `/asdlc-p1:fnd-2-arch-spec`

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Step 12's gate is blocking or a digest (digests at `autopilot`, blocking only at `careful`). See `.claude/PATTERNS.md` § HITL Gate vs Digest.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Load Context

### Step 1 — Read PRD

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If the result contains `"error"` → STOP. Report the error verbatim.
- Save the PRD data. Use these fields for context:
  - `goals` / `problem_statement` — derive project domain (fintech, health, commerce, etc.)
  - `constraints` — any testing constraints mentioned
  - `target_users` — gauge criticality of system

Derive `risk_level` from the PRD:
- **high** — fintech, health, legal, payments, or any domain where failures have serious consequences
- **medium** — general business apps, productivity tools, internal tools with moderate user impact
- **low** — simple CRUD, personal tools, prototypes

Save `risk_level` for use in interview recommendations.

### Step 2 — Read Arch-Spec

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If the result contains `"error"` → STOP. Report the error verbatim.
- Find the entry in `tech_stack` whose `layer` is "test framework". Save its `choice` as `test_framework_name`.
- Save `system_type` for context (e.g. web app, API-only, mobile).

### Step 3 — Load Test-Strategy Scheme and Existing Data

Call `mcp__asdlc__artifact__read_scheme("project.1-foundation.test-strategy")` and save as `scheme`.

Call `mcp__asdlc__artifact__read("project.1-foundation.test-strategy")`.
- `{"error": ...}` → report error verbatim and stop.
- `{"data": null}` → Test Strategy does not exist yet. Set `existing_ver = 0`. Continue to Section 2.
- `{"data": {...}}` → Test Strategy already exists. Save `existing_ver = data["ver"]`. Then:
  1. Display the current Test Strategy content clearly.
  2. Using `scheme` fields as a guide, ask:
     > **Which fields do you want to update?** (e.g. unit_test, integration_test, component_test, browser_test, auto_fix, done_definition — or "all")
  3. For each selected field, collect the new value one sub-field at a time.
  4. After all selected fields are collected, skip to Section 3 — Step 12 with the updated data pre-filled. Unchanged fields carry over from the existing Test Strategy.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Interview

**Autonomy fast-path — if Autonomy level is `autopilot`:** skip the entire interview below.
Do not ask any questions. Auto-select every recommended value, leave all `run_command` /
`seed_command` / `start_command` / `base_url` fields as empty strings ("configure later"), then
jump straight to Section 3 — Step 12. Use this mapping:

- `unit_test.coverage_threshold` — from `risk_level` (high → 90, medium → 80, low → 70)
- `unit_test.scope` — "Service layer, business logic, utility functions"
- `unit_test.mock_policy` — "Mock all I/O: database, network, external services, time"
- `unit_test.run_command` — "" (empty)
- `integration_test.environment.database` — "real_test_db"
- `integration_test.environment.external_services` — "mock"
- `integration_test.seed_command` — "" (empty)
- `integration_test.run_command` — "" (empty)
- If a web/mobile frontend exists in `arch_spec.tech_stack.frontend`:
  - `component_test.framework` — derive from stack (React/Next → React Testing Library;
    Vue/Nuxt → Vue Test Utils + Vitest; Angular → Angular TestBed; Svelte → Svelte Testing
    Library; React Native → RN Testing Library; other → idiomatic choice for the ecosystem)
  - `component_test.scope` — "All interactive components (forms, modals, dropdowns)"
  - `component_test.mock_api` — true
  - `component_test.run_command` — "" (empty)
  - `browser_test.tool` — "Playwright"
  - `browser_test.environment` — "local dev server with test database"
  - `browser_test.headless` — true
  - `browser_test.scope` — "single-screen"
  - `browser_test.start_command` — "" (empty)
  - `browser_test.base_url` — "" (empty)
  - `browser_test.run_command` — "" (empty)
  - Otherwise (no frontend) set all `component_test.*` and `browser_test.*` string fields to ""
    (keep `component_test.mock_api = true`, `browser_test.scope = "single-screen"`).
- `auto_fix.max_retries` — 5

Every field auto-selected here **is a derived assumption** — append each to `derived_assumptions`
in Step 12 as `{field, value, reason: "autopilot: recommended default, not user-stated"}` so the
Review Digest surfaces exactly what was auto-decided. (This is the one case where this command's
`derived_assumptions` list is not empty.)

**Otherwise (Autonomy level `careful`)** — run the interview below.

Ask the following interview questions one at a time. Wait for the user's answer before asking the next.

For each question: state the recommendation clearly, explain the reasoning briefly, then list options.
Mention `test_framework_name` (from arch-spec) where relevant so the user understands which test framework applies.
If the user answers "default" or "yes" or simply accepts, use the recommended value.

### Step 4 — Question 1: Unit Test Coverage Threshold

Determine recommended value from `risk_level`:
- high → 90%
- medium → 80%
- low → 70%

> **Q1 — Unit Test Coverage Threshold**
>
> How much of the code must be covered by unit tests before a screen is considered done?
>
> **Recommendation: [N]%** — [reason based on risk_level, e.g. "standard for business apps" or "higher threshold for high-stakes domains"]
>
> A) [recommended - 10]% — minimal, suitable for prototypes
> B) [recommended]% — recommended for this project ✓
> C) [recommended + 10]% — high-confidence, more effort required
> D) Custom — enter a specific percentage

### Step 5 — Question 2: Unit Test Scope

> **Q2 — Unit Test Scope**
>
> What should unit tests cover in this project?
>
> **Recommendation: Service layer, business logic, and utility functions** — these are the layers that contain logic that can fail independently of infrastructure.
>
> A) Service layer + business logic + utilities (recommended) ✓
> B) Service layer + business logic only (exclude utilities)
> C) All layers including controllers/routes
> D) Custom — describe what to include

### Step 6 — Question 3: Unit Test Mock Policy

> **Q3 — Unit Test Mock Policy**
>
> What should be mocked (faked) when running unit tests?
>
> **Recommendation: Mock all I/O — database, network calls, external services, and time** — unit tests must be isolated and run without infrastructure. Mocking makes tests fast, deterministic, and runnable anywhere.
>
> A) Mock all I/O: DB, network, external services, time (recommended) ✓
> B) Mock only external services (DB calls are real via in-memory DB)
> C) Custom — describe mock policy

### Step 6b — Unit Test Run Command

> **Q3b — Unit Test: Run Command**
>
> What shell command runs your unit tests?
>
> Enter the exact command as run from the project root. Leave blank to configure later.
>
> Examples: `pytest tests/unit`, `go test ./internal/...`, `cargo test --lib`, `./gradlew test`, `npm test`

Save as `unit_test.run_command` (may be an empty string if not yet decided).

### Step 7 — Question 4: Integration Test Database Strategy

> **Q4 — Integration Test: Database Strategy**
>
> Integration tests verify that your app works end-to-end with real infrastructure. What database should they use?
>
> **Recommendation: Real test database** — using a dedicated test DB (separate from dev/prod) gives the highest confidence: it tests real queries, real constraints, and real migrations. An in-memory substitute can miss DB-specific behaviour.
>
> A) Real test database — separate DB instance for tests (recommended) ✓
> B) In-memory database — faster but may miss DB-specific behaviour
> C) Custom — describe strategy

### Step 8 — Question 5: Integration Test External Services

> **Q5 — Integration Test: External Services**
>
> How should external services (payment APIs, email, SMS, maps, etc.) be handled during integration tests?
>
> **Recommendation: Mock external services** — mocking keeps integration tests fast, avoids costs, and prevents flakiness from third-party availability. Real behaviour of external services should be verified in manual or staging tests.
>
> A) Mock external services — fast, free, and reliable (recommended) ✓
> B) Sandbox / staging — use provider test environments
> C) Real — hit actual external services (not recommended for automated tests)

### Step 8b — Integration Test Seed Command

> **Q5b — Integration Test: Seed Command**
>
> What command seeds the test database before integration tests run?
>
> Leave blank if seeding is handled inside the test framework itself (e.g. a `conftest.py` fixture, a `beforeAll` block, or similar).
>
> Examples: `make db-seed-test`, `flask seed-test`, `rake db:seed RAILS_ENV=test`

Save as `integration_test.seed_command` (may be an empty string).

### Step 8c — Integration Test Run Command

> **Q5c — Integration Test: Run Command**
>
> What command runs integration tests?
>
> Enter the exact command as run from the project root. Leave blank to configure later.
>
> Examples: `pytest tests/integration`, `mvn test -Pintegration`, `go test ./tests/integration/...`

Save as `integration_test.run_command` (may be an empty string).

### Step 9 — Question 6: Component Test Framework

Check `arch_spec.tech_stack.frontend`. If empty or not a web/mobile frontend → skip this question, set `component_test.framework = ""`, `component_test.scope = ""`, `component_test.mock_api = true`, `component_test.run_command = ""`, and continue to Step 10.

If a frontend exists, ask:

> **Q6 — Component Test Framework**
>
> Your frontend is **[tech_stack.frontend]**. Component tests verify individual UI components in isolation — rendering, state changes, and user interactions — without a real server.
>
> Which framework should we use?
>
> **Recommendation: [derived from stack below]**
>
> — React / Next.js → **React Testing Library** ✓
> — Vue / Nuxt → **Vue Test Utils + Vitest** ✓
> — Angular → **Angular Testing Utilities (TestBed)** ✓
> — Svelte → **Svelte Testing Library** ✓
> — React Native → **React Native Testing Library** ✓
> — Other → suggest based on ecosystem

Present the matching recommendation. Ask:
> A) [recommended framework] — idiomatic for [tech_stack.frontend] ✓
> B) Custom — enter a specific framework

Always set `component_test.mock_api = true` (components tested in isolation from backend).

Ask the user what should be in scope:
> Which components should have tests? E.g.:
> — All interactive components (forms, modals, dropdowns) — recommended ✓
> — All components that contain business logic
> — All components above a certain complexity threshold
> — Custom

Save `component_test.framework`, `component_test.scope`, `component_test.mock_api = true`.

### Step 9b — Component Test Run Command

(Skip if no frontend — set `component_test.run_command = ""`.)

> **Q6b — Component Test: Run Command**
>
> What command runs component tests?
>
> Enter the exact command as run from the project root. Leave blank if no frontend.
>
> Examples: `vitest run`, `jest --testPathPattern=component`, `ng test --watch=false`, `yarn test:components`

Save as `component_test.run_command` (empty string if no frontend).

### Step 10 — Question 7: Browser Test Tool

Check `arch_spec.tech_stack.frontend`. If empty or no web frontend → skip this question, set `browser_test.tool = ""`, `browser_test.environment = ""`, `browser_test.start_command = ""`, `browser_test.base_url = ""`, `browser_test.run_command = ""`, and continue to Step 11.

If a frontend exists, ask:

> **Q7 — Browser Test Tool**
>
> Browser tests run your app in a real browser for a single screen at a time — no multi-screen journeys. They catch rendering issues, JS errors, and interactions that component tests can't catch.
>
> **Recommendation: Playwright** — fast, multi-browser, excellent async support, good CI integration.
>
> A) Playwright — recommended, modern, multi-browser ✓
> B) Cypress — great DX, Chrome-first
> C) Selenium — widest browser support, older ecosystem
> D) Custom — enter your own

Then ask:
> What environment should browser tests run against?
>
> **Recommendation: local dev server with test database** — fast feedback, no network dependency.
>
> A) Local dev server with test database (recommended) ✓
> B) Staging server with test database
> C) Custom

Then ask:
> Run in headless mode? (No visible browser window — faster, required for CI)
>
> **Recommendation: Yes (headless)** — always headless in CI; you can run headed locally for debugging.
>
> A) Yes, headless (recommended) ✓
> B) No, headed

Save `browser_test.tool`, `browser_test.scope = "single-screen"`, `browser_test.environment`, `browser_test.headless`.

Then ask:
> **What command starts the full stack before browser tests?** (BE + FE + DB)
>
> The agent will run this command, then poll `base_url` every 2 seconds (up to 30 seconds) until it gets HTTP 200, before running any browser test. Leave blank if you prefer to start the server manually outside the framework.
>
> Examples: `make start-test`, `docker-compose -f docker-compose.test.yml up -d`, `./scripts/start-test-env.sh`

If the user provides a command, then also ask:
> **What URL should the agent poll to confirm the server is ready?**
>
> Must return HTTP 200 within 30 seconds (polled every 2s).
>
> Example: `http://localhost:3000`, `http://localhost:8080/health`

If `start_command` is empty → set `browser_test.base_url = ""` and skip the base_url question.

Then ask:
> **What command runs the browser tests?**
>
> Enter the exact command as run from the project root. Leave blank to configure later.
>
> Examples: `playwright test`, `cypress run --headless`, `selenium-side-runner tests/`

Save `browser_test.start_command`, `browser_test.base_url`, `browser_test.run_command`.

### Step 11 — Question 8: Auto-Fix Max Retries

> **Q8 — Auto-Fix: Maximum Retry Attempts**
>
> When a test fails after implementation, the agent will automatically try to fix the code and re-run. How many times should it try before stopping and asking for your help?
>
> **Recommendation: 5 attempts** — enough to handle common issues (wrong variable, missing import, off-by-one), but avoids wasting time on problems that need human judgement.
>
> A) 3 — conservative, escalates to you sooner
> B) 5 — standard, handles most common issues ✓
> C) 10 — aggressive, agent tries harder before escalating
> D) Custom — enter a specific number

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Propose & Confirm

### Step 12 — Build Proposal, then Gate or Digest

Construct the full test-strategy data. Apply the logic below that matches the current mode.

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution. Even if this command is running inside a sequencer
(`fast-bootstrap`) and other commands' assumptions are still visible earlier in the
conversation, do not include them — those belong to a different artifact and are already
logged under their own file (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — if a REVISE later returns here to correct one field, do not re-run this line and
wipe out entries already logged for other, unrelated fields.

**Log to `derived_assumptions` as you go**: for each field below, ask — did the user state this
via an interview answer, or are you determining it yourself? If the latter, append `{field,
value, reason}` to `derived_assumptions` immediately. Most fields here come directly from Q1–Q8
or are fixed framework conventions (e.g. `integration_test.style`, `auto_fix.enabled`) — neither
counts as an assumption, so this list is usually empty for this command. **Exception:** at Autonomy
level `autopilot` the interview is skipped and every recommended value auto-selected in Section 2
is a derived assumption — log all of them here.

**New Test Strategy** (existing_ver == 0) — derive all fields from context + interview answers:

- **unit_test.coverage_threshold** — from Q1
- **unit_test.scope** — from Q2, written as a concise phrase (e.g. "Service layer, business logic, utility functions")
- **unit_test.mock_policy** — from Q3, written as a concise phrase (e.g. "Mock all I/O: database, network, external services, time")
- **unit_test.run_command** — from Q3b (may be empty string)
- **integration_test.style** — always "BDD"
- **integration_test.format** — always "Gherkin"
- **integration_test.scope** — always "All API endpoints"
- **integration_test.environment.database** — from Q4 (e.g. "real_test_db", "in_memory")
- **integration_test.environment.external_services** — from Q5 (e.g. "mock", "sandbox", "real")
- **integration_test.seed_strategy** — always "factories"
- **integration_test.seed_command** — from Q5b (may be empty string)
- **integration_test.run_command** — from Q5c (may be empty string)
- **component_test.framework** — from Q6 (empty string if no frontend)
- **component_test.scope** — from Q6 (empty string if no frontend)
- **component_test.mock_api** — always true
- **component_test.run_command** — from Q6b (empty string if no frontend)
- **browser_test.tool** — from Q7 (empty string if no frontend)
- **browser_test.scope** — always "single-screen"
- **browser_test.environment** — from Q7 (empty string if no frontend)
- **browser_test.headless** — from Q7 (default true)
- **browser_test.start_command** — from Q7 (empty string if no frontend or user skips)
- **browser_test.base_url** — from Q7 (empty string if start_command is empty)
- **browser_test.run_command** — from Q7 (empty string if no frontend)
- **auto_fix.enabled** — always true
- **auto_fix.max_retries** — from Q8
- **auto_fix.on_environment_error** — always "stop"
- **auto_fix.on_spec_mismatch** — always "pause"
- **auto_fix.on_implementation_error** — always "auto_fix"
- **done_definition.screen_done_when** — always:
  - "All unit tests pass"
  - "Unit test coverage >= [coverage_threshold]%"
  - "All API integration tests (BDD/Gherkin) pass"
  - "All component tests pass" (omit if no frontend)
  - "All single-screen browser tests pass" (omit if no frontend)
- **done_definition.cannot_mark_done_if** — always:
  - "Any test is failing"
  - "Coverage is below threshold"
  - "Any test is skipped without a documented reason"

**Existing Test Strategy update** (existing_ver > 0) — merge: take updated fields from Step 3, carry over unchanged fields from the existing data.

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished proposal once
against the interview transcript (Steps 4–11). Confirm every `derived_assumptions` entry is
genuinely not stated, and spot-check the rest. Add any missed entries now.

**If Autonomy level is `careful`:**

Display the complete proposal:

> **Test Strategy Proposal** — [meta.title from PRD]
>
> **Unit Test**
> — Coverage threshold: [N]%
> — Scope: [scope]
> — Mock policy: [mock_policy]
> — Run command: [unit_test.run_command or "— (not set)"]
>
> **Integration Test (API)**
> — Style: BDD (Gherkin — Given/When/Then)
> — Scope: [scope]
> — Database: [database]
> — External services: [external_services]
> — Seed strategy: factories
> — Seed command: [integration_test.seed_command or "— (not set)"]
> — Run command: [integration_test.run_command or "— (not set)"]
>
> **Component Test**
> — Framework: [component_test.framework or "N/A — no frontend"]
> — Scope: [component_test.scope or "N/A"]
> — Mock API: yes
> — Run command: [component_test.run_command or "— (not set)"]
>
> **Browser Test (single-screen)**
> — Tool: [browser_test.tool or "N/A — no frontend"]
> — Environment: [browser_test.environment or "N/A"]
> — Headless: [browser_test.headless]
> — Start command: [browser_test.start_command or "— (manual start)"]
> — Base URL: [browser_test.base_url or "—"]
> — Run command: [browser_test.run_command or "— (not set)"]
>
> **Auto-Fix Policy**
> — Enabled: yes
> — Max retries: [max_retries]
> — On environment error: stop (escalate to user)
> — On spec mismatch: pause (confirm with user before changing test)
> — On implementation error: auto-fix up to [max_retries]x
>
> **Definition of Done — per screen**
> ✓ [screen_done_when, one per line]
> ✗ Cannot mark done if: [cannot_mark_done_if, one per line]
>
> **GO / REVISE [section name] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section name]** → ask for corrections to that section only, update the proposal, re-display
- **STOP** → stop here, do nothing further

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block (usually empty for this command — see note above). Continue without waiting. If the user
corrects something afterward, apply the inline-correction + versioning rule from `.claude/PATTERNS.md`.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Write Artifact

### Step 13 — Write Test Strategy

Construct the `data` object from the confirmed proposal.

Set `meta.title` to the value of `meta.title` from the PRD.
Set `meta.updated_at` to today's date (YYYY-MM-DD).
Set `ver` to `existing_ver + 1`.

Call:
```
mcp__asdlc__artifact__write(
  artifact_key = "project.1-foundation.test-strategy",
  data         = <constructed data object>
)
```

If the result contains `"error"` → STOP. Report the error verbatim.

Save from the result:
- `path` — path of the written file
- `changed_fields` — list of fields that changed

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/project.1-foundation.test-strategy.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section listing each entry, then
`Write` the file back. Skip entirely if `derived_assumptions` is empty.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 14 — Invoke dep-graph-sync-agent

Delegate to the `dep-graph-sync-agent` agent with:

```
artifact_key   = "project.1-foundation.test-strategy"
changed_fields = <changed_fields from Step 13>
depends_on     = ["project.1-foundation.prd", "project.1-foundation.arch-spec"]
```

Wait for the agent to confirm before continuing.

### Step 15 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_ver was 0]: Wrote Test Strategy (new) — <meta.title from PRD>
  [if existing_ver > 0]:   Updated Test Strategy — changed: <changed_fields from Step 13>

Artifacts written
  project.1-foundation.test-strategy   v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  /asdlc-p2:bus-1-scope
```

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- At Autonomy level `careful`: never skip the Step 12 HITL gate — always wait for GO before writing. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Step 12 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass before the gate/digest branch — it is what catches `derived_assumptions` entries missed during synthesis, regardless of Autonomy level
- At Autonomy level `autopilot`: skip Section 2 entirely — ask nothing, auto-select recommended defaults, and log them as derived assumptions (see Section 2 fast-path)
- At `careful`: never ask all interview questions at once — one per turn
- Always show the recommendation and reasoning before listing options — do not make the user guess
- Do not generate the proposal until all interview questions are answered
- Do not continue to Step 14 if artifact__write returns an error
- Do not continue to Step 15 if dep-graph-sync-agent reports an error
- Only REVISE the section the user specifies — do not regenerate the entire proposal
- Never modify test scenarios or test files to make failing tests pass — only fix implementation code
