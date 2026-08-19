---
description: Run Phase 2 (Business Spec) + Phase 3 (Tech Spec) + Phase 4 (Implement) for one screen in a single invocation
allowed-tools:
  - Read
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__track_node
  - mcp__asdlc__dep_graph__sync_stale_status
  - mcp__asdlc__dep_graph__get_stale_nodes
---

You are running the `asdlc-fast-screen` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Screen Pipeline                                     ║
╚══════════════════════════════════════════════════════╝
```

This command runs the full per-screen pipeline — **Business Spec (Phase 2) → Tech Spec (Phase 3) → Implement (Phase 4)** — for one screen, in a single invocation. It does not shorten or simplify any individual phase: each phase keeps its own full interview, HITL gate, and write logic exactly as defined in its own command file. The only thing this command removes is having to invoke three separate commands and re-select the same screen three times.

This command is a **sequencer**, not a duplicate. At each phase it reads the corresponding phase command file and follows its instructions verbatim, with one override: the screen is already selected, so each phase's own "Select Screen" step is skipped (this override is already built into those files — look for the "If `target_screen` was already provided by a calling command" note in their Step 3).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check that `project.2-business-spec.screen-index` has `status: "written"` in `artifact_index`.
If not → STOP. Report: "Pre-condition not met: screen-index has not been written. Run `/asdlc-p2:bus-1-scope` first."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Select Screen (once, for all 3 phases)

**If `target_screen` and `starting_phase` were already provided by a calling command (e.g. `/asdlc-revise-screen`)** → skip this entire section, use the provided values directly, and go to Section 2 if `starting_phase == 2`, Section 3 if `starting_phase == 3`, or Section 4 if `starting_phase == 4`.

### Step 1 — Read screen-index

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

### Step 2 — Present Screen List with Full Status

For each screen in `screen_index.screens`, check `artifact_index` for three keys:
- `{module_id}.{screen_id}.2-business-spec`
- `{module_id}.{screen_id}.3-tech-spec`
- `{module_id}.{screen_id}.4-implement`

Display grouped by module:

> **Which screen do you want to run the full pipeline for?**
>
> [Module Name]
>   1. [screen name] ([screen ID])   2:[✓/—]  3:[✓/—]  4:[✓/—]
>   2. ...
> [Module Name]
>   3. ...
>
> Type the number of your choice.

Wait for the user's answer. Save the selected screen as `target_screen` (the full entry from `screen_index.screens`).

### Step 3 — Determine Starting Phase

Using the status checked in Step 2 for `target_screen`:

- `2-business-spec` is `not_started` → `starting_phase = 2`. No question needed.
- `2-business-spec` is `written`, `3-tech-spec` is `not_started` → ask:
  > This screen already has a business spec (v[ver]). Start from **Phase 2** to review/update it, or skip straight to **Phase 3**? [2 / 3]
- `2-business-spec` and `3-tech-spec` are `written`, `4-implement` is `not_started` → ask:
  > This screen already has a business spec and tech spec. Start from **Phase 2**, **Phase 3**, or skip straight to **Phase 4**? [2 / 3 / 4]
- All three `written` → ask:
  > This screen is fully done (Phase 2/3/4 all written). Which phase do you want to review/update? [2 / 3 / 4 / cancel]
  - `cancel` → STOP. Report: "No changes made."

Save the answer as `starting_phase` (2, 3, or 4).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Run Phase 2 (Business Spec)

Only if `starting_phase == 2`. Otherwise skip to Section 3.

Read the full file `.claude/commands/asdlc-p2/bus-2-screen.md` with the `Read` tool.

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written, with `target_screen` already set to the value chosen in Section 1 — its own Step 3 ("Select Screen") will detect this and skip itself automatically. Everything else — draft synthesis, refinement, usecase confirmation, schema coverage check, the HITL gate, all writes, dep-graph-sync, screen-mock-agent, and its own post-write summary — runs exactly as defined in that file.

**If that flow ends in STOP** (at its HITL gate, or any error condition it defines) → STOP this entire command here. Do not proceed to Section 3. Display:
> Pipeline stopped at Phase 2. No further phases were run for [target_screen.name]. Run `/asdlc-fast-screen` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Run Phase 3 (Tech Spec)

Only if `starting_phase <= 3`. Otherwise skip to Section 3b.

Re-check pre-conditions first (state may have changed after Section 2's write): call `mcp__asdlc__artifact__list` again.
- If the call fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.arch-spec`, `project.3-tech-spec.entity-catalog`, and `project.3-tech-spec.shared-decisions` are all `written`.
- If any is `not_started` → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: Phase 2 for this screen has already been saved — only Phase 3/4 are blocked." (Use the same command mapping as `asdlc-p3:tech-2-screen`'s own Pre-Flight.)

Read the full file `.claude/commands/asdlc-p3/tech-2-screen.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above — skip re-running it to avoid a redundant duplicate check), exactly as written, with `target_screen` already set — its own Step 3 will detect this and skip itself automatically. Everything else — context loading, draft synthesis, refinement, test-spec-writer-agent delegation, test scenario confirmation, the HITL gate, all writes, api-index update, and dep-graph-sync — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 4. Display:
> Pipeline stopped at Phase 3. [target_screen.name] has a business spec but no tech spec yet (or it is unchanged). Run `/asdlc-fast-screen` again when ready to resume from Phase 4.

**If that flow completes successfully** → continue automatically to Section 3b.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3b — Checkpoint: Pre-Implementation Review

Reached before Phase 4 for **every** screen (not just the first). This is a **permanent
exception** (`CLAUDE.md` §8) — always blocking, at every Autonomy level including `autopilot`.
Its purpose: validate the complete business + technical spec before any implementation code is
generated. Phase 4 (Section 4) never runs until the user answers here.

### Step — Load the Screen's Full Specs

Phase 4 has not run yet, so read the two upstream specs in full:
- `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.2-business-spec")` → `screen_biz_spec`.
- `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.3-tech-spec")` → `screen_tech_spec`.
- For each `usecase_id` in `screen_biz_spec.usecase_ids`, attempt
  `mcp__asdlc__artifact__read("project.2-business-spec.usecases.{usecase_id}")` → collect as
  `usecase_artifacts` (skip null results).

Gather derived assumptions — attempt `Read` on each log, taking only the **last** `## v...`
section (skip files not found or with no bullets):
- `.asdlc/generated/internal/derived-assumptions/{module_id}.{screen_id}.2-business-spec.md`
- `.asdlc/generated/internal/derived-assumptions/{module_id}.{screen_id}.3-tech-spec.md`

### Step — Display Checkpoint

Display the **complete** business spec and tech spec — every field, full values, **not** summaries
or counts. Then the assumptions. **Render every assumption bullet verbatim** — one line per
assumption, exactly as stored (`field = value ← reason`); do not summarize, collapse, or paraphrase
(see `.claude/PATTERNS.md` § Derived Assumptions Log).

```
Checkpoint — Pre-Implementation Review: [target_screen.name] ([target_screen.id])
(validasi spec lengkap sebelum kode ditulis — belum ada implementasi)

═══ BUSINESS SPEC ═══
Description: [screen_biz_spec.description]
Actors: [list]
Entry points: [list]
Information displayed:
  [each item]
Available actions:
  [each: action — description (who can do it)]
Business rules:
  [each rule]
Edge cases: [each, or "none"]
Open questions: [each, or "none"]
Test priority: [test_priority]

Use cases (full):
  [for each usecase in usecase_artifacts:]
  [id] · [name]
    Description: [description]
    Actors: [list]   Preconditions: [list]
    Main flow:
      [each step: N. actor — description]
    Alternative flows:
      [each: name — trigger; steps]  (or "none")
    Postconditions: [list]
    Business rules: [list]

═══ TECH SPEC ═══
Route: [route]   Auth: [auth_requirement]
Actor permissions:
  [each actor: name — can_access (conditions)]
API contracts (full):
  [for each api_contract:]
  [usecase_id] · [usecase name]
    Endpoints: [each method + path]
    Request: [full request shape — path/query params, body fields + validation]
    Response: [full success schema; each error code + condition]
    Business logic:
      [each step, in full]
    Data operations: [each entity: operation]
    Edge case handling:
      [each: condition → handling]
    Business rules applied: [list]
    Unit test cases: [each]
Test scenarios:
  Unit: [each]
  API/Integration: [each: method path → expected status/error; request example]
  Component: [each, or "N/A — no frontend"]
  Browser: [each, or "N/A — no frontend"]
Shared entities: [list or "none"]
Screen dependencies: [list or "none"]
Implementation notes: [list or "none"]

═══ DERIVED ASSUMPTIONS (Phase 2 + Phase 3) ═══
  Business Spec:
    · [bullet verbatim — field = value ← reason]
  Tech Spec:
    · [bullet verbatim]
  (or "(tidak ada asumsi tercatat)" if both logs are empty)

LANJUT / REVISI / STOP
```

- **LANJUT** → proceed to Section 4 (Phase 4 implementation).
- **REVISI** → ask: "**What looks wrong?**" Wait for the answer, save as `problem_description`.
  `Read` the full file `.claude/commands/asdlc-revise.md` with the `Read` tool. Execute its
  instructions from its own **Section 1** onward, passing `problem_description` as already provided
  (its Section 1 detects this and skips asking) — its triage decides screen-level vs project-level.
  After the revision completes, **STOP this command here** and display: "Spec revised for
  [target_screen.name]. Run `/asdlc-fast-screen` again (start from Phase 4) when ready to
  implement." — do not auto-proceed to Phase 4, since the spec just changed.
- **STOP** → stop here. Display: "Stopped before implementation. [target_screen.name] has its
  business and tech spec saved; no code was generated. Run `/asdlc-fast-screen` again (from Phase
  4) when ready to implement."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Run Phase 4 (Implement)

Only if `starting_phase <= 4`.

Re-check pre-conditions first: call `mcp__asdlc__artifact__list` again.
- If the call fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.4-implement.scaffold`, `project.4-implement.entity-models`, and `project.4-implement.shared-modules` are all `written`.
- If any is `not_started` → STOP. Report: "Pre-condition not met: [key] has not been written. Run `/asdlc-p4:impl-1-core` first. Note: Phase 2 and 3 for this screen have already been saved."

Read the full file `.claude/commands/asdlc-p4/impl-2-screen.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above — skip re-running it), exactly as written, with `target_screen` already set — its own Step 3 will detect this and skip itself automatically. Everything else — context loading, implementation plan synthesis, refinement, the HITL gate, screen-impl-agent delegation (code-writer → test-writer → test-runner, auto-fix loop), the implement artifact write, and dep-graph-sync — runs exactly as defined in that file.

**If that flow ends in STOP** → note this in the final summary (Section 6), do not display a separate stop message, since this is the last phase.

**If that flow completes** → continue automatically to Section 6.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 6 — Final Summary

Display a combined summary covering only the phases actually run this session:

```
Phases this session
  Phase 2 (Business Spec)  [written v<ver> / updated v<ver> / skipped — already done / stopped]
  Phase 3 (Tech Spec)      [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  Phase 4 (Implement)      [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]

Dep-graph
  [if no stale nodes across the run]:  All nodes clean
  [if stale nodes remain]:             <N> stale — [list node keys, one per line, indented]

Recommended next
  [if all 3 phases done]:        /asdlc-fast-screen  (next screen)
  [if stopped mid-pipeline]:     /asdlc-fast-screen again for this screen, once ready to resume
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- **Never pause between phases to ask the user whether to continue** (no "Lanjut?" / "Continue?" prompt). Phase transitions are automatic. At `autopilot`, each phase's write gate is a non-blocking digest — after showing it, proceed **immediately** to the next phase, and ignore any "Recommended next" line in a phase's own summary. Likewise, between phases do **not** print the digest's standalone inline-correction invitation ("Anything off? Say so — I'll fix it now.") — corrections are consolidated at the Section 3b checkpoint, and the user may interrupt at any time. The digest still shows the artifact written and its ⚠ derived assumptions. The only points that wait for the user are each phase's own **blocking** HITL gate (only at `careful`), the Section 3b pre-implementation checkpoint, and the `spec_mismatch` pause in Phase 4.
- Never skip a HITL gate — each phase's own gate (GO / REVISE / STOP) applies in full, exactly as defined in that phase's own command file. This command does not introduce a combined or shortcut gate.
- If a phase's flow ends in STOP, do not proceed to the next phase. Writes already completed in earlier phases/sections are not rolled back.
- Do not re-ask for screen selection during Phase 2/3/4 sub-flows — `target_screen` is fixed for the entire run once chosen in Section 1.
- If re-checking pre-conditions before Section 3 or Section 4 reveals a required project-level artifact is missing, stop there — do not attempt to skip ahead to a later phase.
- If the `artifact__list` re-check call itself fails (Section 3 or Section 4) — treat it the same as a Pre-Flight failure (MCP server not running) and stop immediately; do not treat a failed call as "not started"
- This command does not create or modify dep-graph nodes directly — all dep-graph updates happen inside each phase's own sub-flow, unchanged from running that command standalone.
- Do not alter, shorten, or reinterpret any step of the three underlying phase command files — read and follow them exactly as written.
- Never skip the Section 3b pre-implementation checkpoint — it fires for **every** screen before Phase 4 and is a permanent exception (`CLAUDE.md` §8), always blocking at every Autonomy level including `autopilot`. Do not run Section 4 (Phase 4) until the user answers LANJUT.
- At the Section 3b checkpoint, display the **full** business spec and tech spec (every field, not summaries) plus the Phase 2/3 derived assumptions verbatim — the whole point is validating everything before code is generated.
- Do not alter, shorten, or reinterpret `asdlc-revise.md` when delegating to it from the checkpoint's REVISI branch — read and follow it exactly as written, starting at its Section 1.
