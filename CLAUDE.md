# CLAUDE.md

This file provides guidance to Claude Code when working in this repository.

---

## 1. Project

**Name:** Mill Smart Log
**Description:** Mill Smart Log adalah sistem manajemen operasional pabrik CPO (mill) yang mendigitalisasi log sheet stasiun produksi — Operator menginput data melalui aplikasi mobile offline-first, sementara Supervisor, Mill Management, dan Admin mengelola, memonitor, dan turut menginput data stasiun melalui web (dan mobile untuk Supervisor), menggantikan pencatatan manual berbasis kertas.
**Current state:** call `artifact__list` at the start of every session.

---

## 2. Framework Overview

Agentic-SDLC is an artifact-based framework for running a structured SDLC.
Each stage of work is represented as an **artifact** (a JSON file) written incrementally by Claude via MCP tools.

**Phases and artifacts:**
Work is divided into phases — `1-foundation`, `2-business-spec`, `3-tech-spec`, `4-implement`.
Each phase has one or more commands. Each command handles one artifact or one step.

**Folder structure:**
- `.asdlc/template/` — templates and schemas for each artifact; read by MCP tools
- `.asdlc/generated/` — written artifacts (output from MCP tools); do not edit directly
- `.asdlc/generated/internal/dep-graph/` — dep-graph files (see Section 7)
- `.asdlc/generated/internal/derived-assumptions/{artifact_key}.md` — one append-only log per artifact, agent-derived field values (see `.claude/PATTERNS.md` § Derived Assumptions Log)
- `.asdlc/generated/internal/config.json` — process-config (`autonomy_level`, §8; `mock_generation_level`, §9; `test_generation_level`, §10); not an artifact, read/written directly
- `.asdlc/mcp/` — MCP server; must be registered in `.mcp.json` at the project root
- `.claude/commands/` — command files; one command = one artifact or one step
- `.claude/agents/` — agent files; invoked by commands, not directly by the user
- `.claude/PATTERNS.md` — runtime reference for HITL Gate vs Digest and Derived Assumptions Log mechanics; read by commands at execution time (see §8)

**Workflow per artifact:**
1. User runs a command (e.g. `/asdlc-p1:fnd-1-prd`)
2. Command conducts interview + HITL gate or Review Digest (per Autonomy level, §8) → writes artifact via `artifact__write`
3. Command invokes `dep-graph-sync-agent` → bumps node version via `dep_graph__track_node`
4. Command updates `CLAUDE.md` if relevant
5. Command calls `dep_graph__sync_stale_status` → displays summary (phase, artifacts written, dep-graph status, recommended next)
6. User runs `/asdlc-commit` → stages files, confirms commit message, commits to git

---

## 3. MCP Tools

Server: `.asdlc/mcp/server.py`

| Tool                            | Parameters                                                | Description                                                                                        |
|:--------------------------------|:----------------------------------------------------------|:---------------------------------------------------------------------------------------------------|
| `artifact__list`                | —                                                         | List all known artifacts and their write status. Call at the start of every session.               |
| `artifact__read`                | `artifact_key`                                            | Read artifact content. Returns `{"data": dict}` or `{"data": null}` if not yet written.           |
| `artifact__read_scheme`         | `artifact_key`                                            | Read artifact field descriptions. Returns `{"data": dict}` or `{"data": null}`.                   |
| `artifact__write`               | `artifact_key`, `data`                                    | Write artifact. Returns `{"ok": true, "key", "path", "changed_fields"}`.                          |
| `dep_graph__track_node`         | `artifact_key`, `changed_fields`, `depends_on`, `files?` | Bump node version in dep-graph and snapshot depends_on. Called by `dep-graph-sync-agent`.         |
| `dep_graph__sync_stale_status`  | —                                                         | Compute and persist stale status across the entire dep-graph. Returns stale/clean/not_started summary. |
| `dep_graph__get_stale_nodes`    | —                                                         | Read nodes currently marked as stale. Read-only — use for pre-flight checks.                      |

---

## 4. Rules

- **Never read/write artifact files directly** — always via MCP tools. Two deliberate exceptions, both read/written directly by design, neither is an artifact: the Derived Assumptions Log (see `.claude/PATTERNS.md` § Derived Assumptions Log) and `config.json` (§8, §9, and §10, see below)
- **Never write an artifact without either a gate or a digest** — the autonomy level (§8) decides which, and the five permanent exceptions in §8 are never downgraded
- **Never start an artifact if pre-conditions are not met**
- **Never write code before Phase 3 is complete**

---

## 5. Agents

Agent files are in `.claude/agents/`. Agents are invoked by commands — never run them directly.

| Agent                      | Invoked by        | Description                                                                                   |
|:---------------------------|:------------------|:------------------------------------------------------------------------------------------------|
| `dep-graph-sync-agent`     | All commands      | Calls `dep_graph__track_node` to bump node version and snapshot depends_on after artifact write. |
| `uiux-visual-agent`        | `fnd-3-uiux-spec` | Reads uiux-spec + PRD artifacts, generates shared CSS assets (`assets/design-tokens.css`, `assets/components.css`, `assets/shell.css`) and HTML visual previews (design-system-preview.html, screen-type-[type].html, screen-type-[type]-[state].html). All HTML references shared assets — not self-contained. |
| `bdd-spec-writer-agent`    | `bus-2-screen`    | Receives usecase data + existing bdd_scenarios. Re-derives scenarios from current content (happy-path per actor, 1 per alternative_flow, 1 per validating business_rule), merges semantically with existing scenarios (never removes), and returns merged_bdd_scenarios + added_count. |
| `test-spec-writer-agent`   | `tech-2-screen`   | Derives all test specifications for one screen: unit_test_cases (from business_logic branching per api_contract) and test_scenarios (api_test + component_test + browser_test, from Phase 2 bdd_scenarios). Returns unit_test_cases_map, test_scenarios, and optional warning. |
| `screen-mock-agent`        | `bus-2-screen`    | Reads a screen business spec artifact + uiux-spec, generates a static 1-state HTML mock at `.asdlc/generated/2-business-spec/screens/html/<screen_id>.html`. References Phase 1 shared CSS assets. Only invoked at Mock Generation level `full` (§9); skipped at `none`. Non-blocking — failure does not stop the command. |
| `entity-models-agent`      | `impl-1-core`     | Receives arch_spec, entity_catalog, and entities_to_generate list. Generates one model/schema file per entity using the ORM or schema library for the tech stack. Reports files_generated and entities_implemented. |
| `shared-modules-agent`     | `impl-1-core`     | Receives shared_decisions, arch_spec, entity_models_artifact, modules_to_generate (BE), and fe_modules_to_generate (FE) lists. Generates BE shared modules (auth middleware, error handler, DB client, integration clients, pagination helper) and FE shared modules (api-client, auth-store, router, fe-error-handler), test infrastructure config, and .env.example. Reports files_generated, modules_implemented, fe_files_generated, fe_modules_implemented, test_infrastructure, env_vars_required. |
| `screen-impl-agent`        | `impl-2-screen`   | Orchestrator for Phase 4 screen implementation. Manages the `code-writer-agent` → `test-writer-agent` → `test-runner-agent` flow, enforces auto-fix policies from test-strategy, and returns final test_results + file lists to the command. |
| `code-writer-agent`        | `screen-impl-agent`         | Generates or fixes **implementation files only** for one screen (route/controller, service, FE component). Operates in `generate` mode (first call) or `fix` mode (called with test failure details). Never generates test files. Never runs tests. |
| `test-writer-agent`        | `screen-impl-agent`         | Generates **all test files** for one screen (BE unit, FE unit, integration, component, browser). Invoked once after `code-writer-agent` generate mode. Never invoked in fix rounds — test files are not modified after initial generation. |
| `test-runner-agent`        | `screen-impl-agent`         | Runs all applicable test types (unit, integration, component, browser) for one screen and reports pass/fail/coverage per type, including per-test failure details. Never modifies source or test files. |

---

## 6. Commands

Command files are in `.claude/commands/`. Read the command file before executing.

| Command                                 | Description                                              |
|:----------------------------------------|:---------------------------------------------------------|
| `asdlc-p1:fnd-1-prd`                    | Generate / update PRD                                    |
| `asdlc-p1:fnd-2-arch-spec`              | Generate / update Architecture Spec                      |
| `asdlc-p1:fnd-3-uiux-spec`              | Generate / update UIUX Specification                     |
| `asdlc-p1:fnd-4-test-strategy`          | Generate / update Test Strategy                        |
| `asdlc-p2:bus-1-scope`                  | Define project scope: actors, modules, screens, usecase overview |
| `asdlc-p2:bus-2-screen`                 | Deep-dive business spec for one screen                   |
| `asdlc-p3:tech-1-core`                  | Define entity catalog + shared technical decisions       |
| `asdlc-p3:tech-2-screen`                | Deep-dive technical spec for one screen                  |
| `asdlc-p4:impl-1-core`                  | Generate project scaffold, entity models, and shared infrastructure modules |
| `asdlc-p4:impl-2-screen`                | Implement one screen (routes, services, unit tests, FE components, FE tests) |
| `asdlc-fast-screen`                     | Run Phase 2 + Phase 3 + Phase 4 for one screen in a single invocation — selects the screen once, then runs each phase's existing flow (full interview, HITL gate or digest per §8, writes) in sequence; includes a mandatory pre-implementation review checkpoint (permanent exception) before Phase 4 for every screen |
| `asdlc-fast-bootstrap`                  | Run PRD + Arch-Spec + UIUX-Spec + Test Strategy + Scope + Tech Core + Implementation Core in a single invocation — project bootstrap sequencer with flexible per-step resume; ends with a bootstrap review checkpoint (permanent exception) once all 7 steps are done |
| `asdlc-revise`                          | Entry point when you don't know which layer is wrong — describe the problem, triages screen-level vs project-level, delegates to `asdlc-revise-screen` or `asdlc-revise-project` |
| `asdlc-revise-screen`                   | Diagnose an issue on one already-implemented screen (UIUX type-pattern / Business Spec / Tech Spec / Implementation), confirm, then fix via `asdlc-fast-screen` (or `fnd-3-uiux-spec` first if UIUX-Spec) |
| `asdlc-revise-project`                  | Diagnose a project-level issue (1 of 7 candidates, same as `asdlc-fast-bootstrap`'s steps), confirm, execute the fix, then propagate to affected screens (bounded — asks before running more than one) |
| `asdlc-commit`                          | Stage all changes, confirm commit message, commit to git, display summary    |
| `asdlc-check-stale`                     | Check which artifacts are stale and what command to run to fix them          |
| `asdlc-whats-next`                      | Read-only: recommend the most useful next command(s) to run, based on artifact status, staleness, and screen progress |
| `asdlc-config`                          | View or change process-config values (Autonomy level, Mock Generation level) in `config.json` — validates against a known-keys registry, never writes an unvalidated value |

---

## 7. Dep-Graph

The dep-graph tracks the version of every artifact and the dependencies between them.
Every time an artifact is written, its node is bumped via `dep_graph__track_node`.
If an upstream artifact changes version, downstream nodes are automatically marked stale.

Dep-graph files:

- `project.json` — nodes for all project-level artifacts
- `modules.json` — index of all modules
- `module-{module_id}.json` — nodes for all screen phases in one module

`artifact_key` format:
- Project artifact (flat): `"project.{phase}.{artifact}"` — e.g. `"project.1-foundation.prd"`
- Project artifact (item): `"project.{phase}.{subfolder}.{item_id}"` — e.g. `"project.2-business-spec.usecases.usecase-001--login"` → file at `generated/2-business-spec/usecases/usecase-001--login.json`; template/schema shared at `template/2-business-spec/usecases.json`
- Module node: `"{module_id}.{screen_id}.{phase}"` — e.g. `"module-001.screen-001--login.2-business-spec"`

Phase 1 project-level artifacts and their dep-graph dependencies:

| Artifact key                                | depends_on                                                          |
|:--------------------------------------------|:--------------------------------------------------------------------|
| `project.1-foundation.prd`                  | (root — no dependencies)                                            |
| `project.1-foundation.arch-spec`            | `project.1-foundation.prd`                                          |
| `project.1-foundation.uiux-spec`            | `project.1-foundation.prd`                                          |
| `project.1-foundation.test-strategy`        | `project.1-foundation.prd`, `project.1-foundation.arch-spec`        |

Phase 2 project-level artifacts and their dep-graph dependencies:

| Artifact key                              | depends_on                                         |
|:------------------------------------------|:---------------------------------------------------|
| `project.2-business-spec.actor-index`     | `project.1-foundation.prd`                         |
| `project.2-business-spec.module-index`    | `project.1-foundation.prd`                         |
| `project.2-business-spec.screen-index`    | `project.2-business-spec.module-index`             |
| `project.2-business-spec.usecase-index`   | `project.2-business-spec.screen-index`             |
| `{module_id}.{screen_id}.2-business-spec` | `prd`, `uiux-spec`, `screen-index` (project-level) |

Note: individual usecase item artifacts (`project.2-business-spec.usecases.*`) are **not** tracked as dep-graph nodes. Staleness for usecases is captured via `usecase-index` version.

Phase 3 project-level artifacts and their dep-graph dependencies:

| Artifact key                               | depends_on                                                                                            |
|:-------------------------------------------|:------------------------------------------------------------------------------------------------------|
| `project.3-tech-spec.entity-catalog`       | `project.1-foundation.arch-spec`, `project.2-business-spec.actor-index`, `project.2-business-spec.screen-index` |
| `project.3-tech-spec.shared-decisions`     | `project.1-foundation.prd`, `project.1-foundation.arch-spec`, `project.3-tech-spec.entity-catalog`   |
| `project.3-tech-spec.api-index`            | `project.2-business-spec.screen-index`                                                                |
| `{module_id}.{screen_id}.3-tech-spec`      | `self.2-business-spec`, `project.1-foundation.arch-spec`, `project.3-tech-spec.entity-catalog`, `project.3-tech-spec.shared-decisions` |

Phase 4 project-level artifacts and their dep-graph dependencies:

| Artifact key                                  | depends_on                                                                                                        |
|:----------------------------------------------|:------------------------------------------------------------------------------------------------------------------|
| `project.4-implement.scaffold`                | `project.1-foundation.arch-spec`, `project.3-tech-spec.shared-decisions`                                         |
| `project.4-implement.entity-models`           | `project.3-tech-spec.entity-catalog`, `project.4-implement.scaffold`                                             |
| `project.4-implement.shared-modules`          | `project.3-tech-spec.shared-decisions`, `project.4-implement.entity-models`, `project.4-implement.scaffold`      |
| `{module_id}.{screen_id}.4-implement`         | `self.3-tech-spec`, `project.4-implement.entity-models`, `project.4-implement.shared-modules`                    |

---

## 8. Autonomy

**Where the value lives:** `.asdlc/generated/internal/config.json` → `autonomy_level`. `Read`
that file to get the current value; default `"careful"` if the file is not found. Valid
values: `careful` | `autopilot`.

Controls whether a command's HITL gate blocks and waits, or writes the artifact immediately
and shows a non-blocking **Review Digest** instead (see `.claude/PATTERNS.md` § HITL Gate vs Digest).
This is a process preference, not an artifact — no dep-graph node, no schema, no `ver`; it's
read/written directly like the Derived Assumptions Log (§4 Rule 1), not through MCP. Every
command with a gate reads this value in its Pre-Flight step.

The digest's `⚠ Derived by the agent` block is sourced from the Derived Assumptions Log
(`.claude/PATTERNS.md` § Derived Assumptions Log) — every command logs to its own
`.asdlc/generated/internal/derived-assumptions/{artifact_key}.md` regardless of level, even at
`careful` where nothing is shown in a digest, since the log has value independent of display.

| Level | Behavior |
|:------|:---------|
| `careful` | **Default.** All gates blocking — you review and approve every step. Use for high-risk work or domains the agent doesn't yet understand well. |
| `autopilot` | All gates become Review Digests — including the PRD gate and the two batch confirmations — except the five permanent exceptions below. |

**Permanent exceptions — blocking at every level, including `autopilot`:**

- **Visual UIUX preview gate** (`fnd-3-uiux-spec` Step 16b) — human eyes catch visual defects the agent cannot self-assess.
- **`spec_mismatch` pause** inside `screen-impl-agent` (triggered during `impl-2-screen`) — an unresolvable conflict between spec and implementation the agent must not decide alone.
- **`asdlc-commit` gate** — never commit unseen.
- **Bootstrap checkpoint** (`asdlc-fast-bootstrap` Section 9) — one full-project review of every
  derived assumption across all 7 steps, before per-screen work begins.
- **Pre-implementation review** (`asdlc-fast-screen` Section 3b) — before Phase 4 for **every**
  screen, a full review of the screen's business spec, tech spec, and derived assumptions, so
  nothing reaches implementation unvalidated.

---

## 9. Mock Generation

**Where the value lives:** `.asdlc/generated/internal/config.json` → `mock_generation_level`.
`Read` that file to get the current value; default `"none"` if the file is not found.
Valid values: `full` | `none`. Same file as Autonomy (§8), same read/write
mechanics — a process preference, not an artifact; no dep-graph node, no schema, no `ver`.

Controls how much visual mock/preview output the framework generates, independent of the
Autonomy level. Two commands read it:

| Level | `fnd-3-uiux-spec` (Phase 1) | `bus-2-screen` (Phase 2, per screen) |
|:------|:----------------------------|:--------------------------------------|
| `full` | System design (`design-system-preview.html`) + system preview, all states (`screen-type-[type].html` + one file per remaining state) | Screen mock generated (`screen-mock-agent`) |
| `none` | **Default.** System design only — no screen-type preview is generated at all (Steps 13, 15, 16 all skipped) | Screen mock skipped |

`design-system-preview.html` generation itself is never gated by this level — it follows the
same change-based logic (`should_generate_design_system`) at every level. The
screen-type preview (Phase 1) and the per-screen mock (Phase 2) are what turn off at `none`.

This does not interact with the Autonomy gates in any way — a screen preview or screen mock
being skipped by `mock_generation_level` is not a HITL decision and produces no gate or
digest, just a "skipped" line in that command's summary. The Step 16b visual review gate
(§8 permanent exceptions) still applies whenever at least one preview file was generated —
it is simply skipped if `generated_files` ends up empty (e.g. `none`, or an update that only
touched `accessibility`/`design_notes`).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

## 10. Test Generation

**Where the value lives:** `.asdlc/generated/internal/config.json` → `test_generation_level`.
`Read` that file to get the current value; default `"full"` if the file is not found.
Valid values: `full` | `none`. Same file as Autonomy (§8) and Mock Generation
(§9), same read/write mechanics — a process preference, not an artifact; no dep-graph node,
no schema, no `ver`.

Controls whether Phase 4 generates and runs tests, independent of the Autonomy level. Only
`impl-2-screen` (Phase 4, per screen) reads it and passes it to `screen-impl-agent`:

| Level | Behavior |
|:------|:---------|
| `full` | **Default.** `test-writer-agent` generates all test files, `test-runner-agent` runs them, and the auto-fix loop runs up to `test_strategy.auto_fix.max_retries`. Current behavior. |
| `none` | No test files generated at all — no `test-writer-agent`, no run, no auto-fix. Screen status is `"partial"` (unverified) with a documented `known_issue`. |

`impl-1-core` never generates tests, so this level does not affect it.

This does not interact with the Autonomy gates. But note one safety consequence: the
`spec_mismatch` pause (§8 permanent exceptions) is raised by `test-runner-agent` while running
tests — at `none` no tests run, so that pause cannot trigger. Turning tests
off trades away that reactive safety net. A screen left `"partial"` this way can be verified
later by re-running `impl-2-screen` in update mode and choosing the "add/complete tests
(keep existing code)" scope — this generates and runs the tests against the existing code
(auto-fixing only where tests fail) without regenerating it from the spec.
