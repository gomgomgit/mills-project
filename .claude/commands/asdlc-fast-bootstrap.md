---
description: Run Phase 1 Foundation (PRD, Arch-Spec, UIUX-Spec, Test Strategy) + Phase 2 Scope + Phase 3 Tech Core + Phase 4 Implementation Core in a single invocation — project bootstrap sequencer
allowed-tools:
  - Read
  - Edit
  - Bash
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__get_stale_nodes
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-fast-bootstrap` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Project Bootstrap                                   ║
╚══════════════════════════════════════════════════════╝
```

This command runs the full project-bootstrap pipeline — **PRD → Architecture Spec → UIUX Specification → Test Strategy → Scope (Phase 2) → Tech Core (Phase 3) → Implementation Core (Phase 4)** — in a single invocation. It covers every project-level command that is not per-screen. It does not shorten or simplify any individual step: each step keeps its own full interview, HITL gate(s), and write logic exactly as defined in its own command file. The only thing this command removes is having to invoke seven separate commands manually.

This command is a **sequencer**, not a duplicate. At each step it reads the corresponding command file and follows its instructions verbatim. Unlike `asdlc-fast-screen`, none of the seven underlying commands need any modification to support this sequencer — none of them have a "selection" step to skip, so each one is simply read and executed exactly as it would be if invoked directly by the user.

This command must not assume any prior step ran through this sequencer — it always re-derives status fresh from `mcp__asdlc__artifact__list`. Steps completed by running the underlying commands directly (e.g. `/asdlc-p1:fnd-4-test-strategy`) are indistinguishable from steps completed through this sequencer, and both are fully interchangeable.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Determine Starting Step

### Step 1 — Check Status of All 7 Steps

Using `artifact_index`, determine done/not-done for each step:

- **Step 1 (PRD)** — `project.1-foundation.prd` is `written`?
- **Step 2 (Architecture Spec)** — `project.1-foundation.arch-spec` is `written`?
- **Step 3 (UIUX Specification)** — `project.1-foundation.uiux-spec` is `written`?
- **Step 4 (Test Strategy)** — `project.1-foundation.test-strategy` is `written`?
- **Step 5 (Scope)** — ALL of `project.2-business-spec.actor-index`, `module-index`, `screen-index`, `usecase-index` are `written`?
- **Step 6 (Tech Core)** — ALL of `project.3-tech-spec.entity-catalog`, `shared-decisions` are `written`?
- **Step 7 (Implementation Core)** — ALL of `project.4-implement.scaffold`, `entity-models`, `shared-modules` are `written`?

### Step 2 — Determine Starting Step

If **all 7 steps are not done** (fresh project) → `starting_step = 1`. No question needed.

Otherwise, display status and ask:

> **Project bootstrap status:**
>
> 1. PRD                            [✓ written / — not started]
> 2. Architecture Spec               [✓ written / — not started]
> 3. UIUX Specification              [✓ written / — not started]
> 4. Test Strategy                   [✓ written / — not started]
> 5. Scope (Phase 2)                 [✓ written / — not started]
> 6. Tech Core (Phase 3)             [✓ written / — not started]
> 7. Implementation Core (Phase 4)   [✓ written / — not started]
>
> Continue from step [N] (first not-yet-done step), type a step number 1–7 to redo/review that step onward, or "cancel"?

- User accepts the default → `starting_step` = the first not-done step number.
- User types a number 1–7 → `starting_step` = that number (this may re-run steps already marked written — each underlying command's own update-mode logic handles that).
- "cancel" → STOP. Report: "No changes made."

Save the answer as `starting_step`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Step 1: PRD

Only if `starting_step == 1`. Otherwise skip to Section 3.

Read the full file `.claude/commands/asdlc-p1/fnd-1-prd.md` with the `Read` tool.

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written. Everything else — the interview, the HITL gate, the write, dep-graph-sync, the CLAUDE.md Section 1 update, and its own post-write summary — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 3. Display:
> Pipeline stopped at Step 1 (PRD). No further steps were run. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Step 2: Architecture Spec

Only if `starting_step <= 2`. Otherwise skip to Section 4.

Re-check pre-conditions first (state may have changed after Section 2's write): call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.prd` is `written`.
- If not → STOP. Report: "Pre-condition not met: PRD has not been written. Run `/asdlc-p1:fnd-1-prd` first."

Read the full file `.claude/commands/asdlc-p1/fnd-2-arch-spec.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above — skip re-running it to avoid a redundant duplicate check), exactly as written. Everything else — the interview, the schema coverage check, the HITL gate, the write, and dep-graph-sync — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 4. Display:
> Pipeline stopped at Step 2 (Architecture Spec). PRD has already been saved. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 4.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Step 3: UIUX Specification

Only if `starting_step <= 3`. Otherwise skip to Section 5.

Re-check pre-conditions first: call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.prd` and `project.1-foundation.arch-spec` are both `written`.
- If either is not → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: earlier steps in this run have already been saved." (PRD → `/asdlc-p1:fnd-1-prd` / Arch-Spec → `/asdlc-p1:fnd-2-arch-spec`)

Note the Mock Generation level here — `Read` `.asdlc/generated/internal/config.json`; use
`mock_generation_level` (default `"none"` if the file is not found). See `CLAUDE.md` §9
for what each level means. Unlike `autonomy_level`, no earlier step in this pipeline reads
this key, so it is not yet known from context at this point — this is the first place it is
needed, by fnd-3-uiux-spec's own Section 5 (visual preview generation).

Read the full file `.claude/commands/asdlc-p1/fnd-3-uiux-spec.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above), exactly as written. Everything else — the interview, the schema coverage check, the HITL gate, the write, the visual preview generation (design system + reference screen type), the single visual review gate at its Step 16b, and dep-graph-sync — runs exactly as defined in that file.

**If that flow ends in STOP** (at any of its gates) → STOP this entire command here. Do not proceed to Section 5. Display:
> Pipeline stopped at Step 3 (UIUX Specification). PRD and Architecture Spec have already been saved. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 5.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Step 4: Test Strategy

Only if `starting_step <= 4`. Otherwise skip to Section 6.

Re-check pre-conditions first: call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.prd` and `project.1-foundation.arch-spec` are both `written`.
- If either is not → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: earlier steps in this run have already been saved." (PRD → `/asdlc-p1:fnd-1-prd` / Arch-Spec → `/asdlc-p1:fnd-2-arch-spec`)

Read the full file `.claude/commands/asdlc-p1/fnd-4-test-strategy.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above), exactly as written. Everything else — the interview, the HITL gate, the write, and dep-graph-sync — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 6. Display:
> Pipeline stopped at Step 4 (Test Strategy). PRD, Architecture Spec, and UIUX Specification (if reached) have already been saved. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 6.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 6 — Step 5: Scope (Phase 2)

Only if `starting_step <= 5`. Otherwise skip to Section 7.

Re-check pre-conditions first: call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.prd` and `project.1-foundation.uiux-spec` are both `written`.
- If either is not → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: earlier steps in this run have already been saved." (PRD → `/asdlc-p1:fnd-1-prd` / UIUX-Spec → `/asdlc-p1:fnd-3-uiux-spec`)

Read the full file `.claude/commands/asdlc-p2/bus-1-scope.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above), exactly as written. Everything else — the Business Analyst interview (actors, modules, screens, usecase overview), the HITL gate, all four index writes, and dep-graph-sync × 4 — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 7. Display:
> Pipeline stopped at Step 5 (Scope). Earlier steps in this run have already been saved. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 7.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 7 — Step 6: Tech Core (Phase 3)

Only if `starting_step <= 6`. Otherwise skip to Section 8.

### Pre-Condition Re-Check

Call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.arch-spec`, `project.2-business-spec.actor-index`, and `project.2-business-spec.screen-index` are all `written`.
- If any is not → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: earlier steps in this run have already been saved." (arch-spec → `/asdlc-p1:fnd-2-arch-spec` / actor-index + screen-index → `/asdlc-p2:bus-1-scope`)

### Extra Check — Screen Business Spec Coverage

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")` and save as `screen_index` (skip this call if `screen_index` is already available from Section 6 in this same run).

For each screen in `screen_index.screens`, check `artifact_index` for `{module_id}.{screen_id}.2-business-spec` status.

If **zero** screens have a `written` business spec:
> ⚠ No per-screen business specs have been written yet (`/asdlc-p2:bus-2-screen`). The entity catalog in this step will need to be described manually by you rather than derived from screen specs. Continue anyway? (Y/N)
- **N** → STOP. Report: "Stopped before Tech Core. Run `/asdlc-p2:bus-2-screen` for the screens you want, then run `/asdlc-fast-bootstrap` again to resume from Tech Core. Earlier steps in this run have already been saved."
- **Y** → proceed.

If at least one screen has a written business spec → proceed without asking (tech-1-core already handles partial coverage — screens without a business spec are silently skipped when deriving entities).

### Run Tech Core

Read the full file `.claude/commands/asdlc-p3/tech-1-core.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above), exactly as written. Everything else — entity synthesis, `test_fixture` confirmation per entity, the shared-decisions interview, the HITL gate, both writes, and dep-graph-sync × 2 — runs exactly as defined in that file.

**If that flow ends in STOP** → STOP this entire command here. Do not proceed to Section 8. Display:
> Pipeline stopped at Step 6 (Tech Core). Earlier steps in this run have already been saved. Run `/asdlc-fast-bootstrap` again when ready to resume.

**If that flow completes successfully** → continue automatically to Section 8.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 8 — Step 7: Implementation Core (Phase 4)

Only if `starting_step <= 7`.

Re-check pre-conditions first: call `mcp__asdlc__artifact__list` again.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Verify `project.1-foundation.arch-spec`, `project.3-tech-spec.entity-catalog`, and `project.3-tech-spec.shared-decisions` are all `written`.
- If any is not → STOP. Report: "Pre-condition not met: [key] has not been written. Run [command] first. Note: earlier steps in this run have already been saved." (arch-spec → `/asdlc-p1:fnd-2-arch-spec` / entity-catalog + shared-decisions → `/asdlc-p3:tech-1-core`)

Read the full file `.claude/commands/asdlc-p4/impl-1-core.md` with the `Read` tool.

Execute its instructions starting from its **Section 1** onward (its own Pre-Flight was already re-verified above), exactly as written. Everything else — the generation plan, the HITL gate, scaffold generation, entity-models-agent delegation, shared-modules-agent delegation, all three writes, and dep-graph-sync × 3 — runs exactly as defined in that file.

**If that flow ends in STOP** → note this in the final summary (Section 10) — skip Section 9 (the checkpoint below only applies once all 7 steps are done), do not display a separate stop message, since this is the last step.

**If that flow completes** → continue automatically to Section 9.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 9 — Checkpoint: Bootstrap Review

Only reached if Section 8 completed without STOP (all 7 steps done). This is a **permanent
exception** (`CLAUDE.md` §8) — always blocking, at every Autonomy level including `autopilot`.

### Step — Gather Derived Assumptions

For each of these 13 project-level artifact_keys, attempt `Read` on
`.asdlc/generated/internal/derived-assumptions/{artifact_key}.md`:

```
project.1-foundation.prd
project.1-foundation.arch-spec
project.1-foundation.uiux-spec
project.1-foundation.test-strategy
project.2-business-spec.actor-index
project.2-business-spec.module-index
project.2-business-spec.screen-index
project.2-business-spec.usecase-index
project.3-tech-spec.entity-catalog
project.3-tech-spec.shared-decisions
project.4-implement.scaffold
project.4-implement.entity-models
project.4-implement.shared-modules
```

For each file found, take only the **last** `## v...` section (the artifact's current state) —
not its full history. An artifact whose file isn't found, or whose last section has no bullets,
contributes zero bullets — it is not itself an error, just an artifact with nothing derived.

Group by **command** (not by individual artifact_key), using the exact same classification
`asdlc-revise-project.md` Step 2 already defines:

```
PRD                    → project.1-foundation.prd
Architecture Spec      → project.1-foundation.arch-spec
UIUX Specification     → project.1-foundation.uiux-spec
Test Strategy          → project.1-foundation.test-strategy
Scope                  → project.2-business-spec.{actor,module,screen,usecase}-index
Tech Core              → project.3-tech-spec.{entity-catalog,shared-decisions}
Implementation Core    → project.4-implement.{scaffold,entity-models,shared-modules}
```

For commands mapping to more than one artifact_key (Scope, Tech Core, Implementation Core),
combine bullets from all of that command's artifacts into one group — label each bullet's
originating artifact_key inline if the command has more than one artifact that actually
contributed bullets, to keep the source traceable.

Count `N` = number of **commands** (out of 7) with at least one bullet across their artifact(s),
`M` = total bullets across all commands.

### Step — Display Checkpoint

**Render every bullet verbatim — do not summarize.** Print each bullet from the logs exactly as
stored (`field = value ← reason`), one line per assumption. Do NOT collapse multiple fields into
one line, list a field name without its value, or replace a value with a prose description. If a
command contributed 12 bullets, show 12 lines. (The logs are already kept concise at the source —
UIUX fields are stored as one-line summaries and `test_fixture` is not logged, see
`.claude/PATTERNS.md` § Derived Assumptions Log — so verbatim rendering stays complete without
becoming bloated.)

```
Checkpoint — Bootstrap selesai (N/7 command punya asumsi, M asumsi total)

  [Command name]
    · [bullet from log] [(artifact_key) — only if this command has >1 contributing artifact]
    · [bullet from log]
  [Command name]
    (tidak ada asumsi tercatat)
  ...

LANJUT / REVISI [command name] / STOP
```

Fires even if `M == 0` — the checkpoint's value isn't only the assumption list, it's the one
guaranteed full-project review point before per-screen work begins.

- **LANJUT** → proceed to Section 10.
- **REVISI [command name]** → `Read` the full file `.claude/commands/asdlc-revise-project.md`
  with the `Read` tool. Execute its instructions starting from its own **Section 2** onward
  (skip Section 1 — the command is already named here, no diagnosis needed), with the confirmed
  command pre-set to the one named. That flow's own Section 3 (propagate downstream) and
  Section 4 (final summary) replace this command's Section 10 for this run.
- **STOP** → stop here. Display: "Checkpoint stopped — no further action. All 7 steps remain
  saved as written. Run `/asdlc-revise-project` when ready to fix something."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 10 — Final Summary

Display a combined summary covering only the steps actually run this session:

```
Steps this session
  1. PRD                          [written v<ver> / updated v<ver> / skipped — already done / stopped]
  2. Architecture Spec            [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  3. UIUX Specification           [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  4. Test Strategy                [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  5. Scope (Phase 2)              [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  6. Tech Core (Phase 3)          [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  7. Implementation Core (Phase 4) [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]

Dep-graph
  [if no stale nodes across the run]:  All nodes clean
  [if stale nodes remain]:             <N> stale — [list node keys, one per line, indented]

Recommended next
  [if all 7 steps done]:          /asdlc-p2:bus-2-screen  (start per-screen business spec)
  [if stopped mid-pipeline]:      /asdlc-fast-bootstrap again, once ready to resume
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- **Never pause between steps to ask the user whether to continue** (no "Lanjut ke Step N?" / "Continue?" prompt). Step transitions are automatic. At `autopilot`, a step's write gate is a non-blocking digest — after showing it, proceed **immediately** to the next step, and ignore any "Recommended next" line in a step's own summary (that is standalone guidance, not a stopping point). Likewise, between steps do **not** print the digest's standalone inline-correction invitation ("Anything off? Say so — I'll fix it now.") — that line is for standalone runs where the turn ends; here corrections are consolidated at the Section 9 checkpoint, and the user may interrupt at any time. The digest still shows the artifact written and its ⚠ derived assumptions. The only points that wait for the user are each step's own **blocking** HITL gate (only at `careful`), the UIUX visual review gate (Step 16b), and the Section 9 checkpoint.
- Never skip a HITL gate — each step's own gate(s) apply in full, exactly as defined in that step's own command file. This command does not introduce a combined or shortcut gate.
- If a step's flow ends in STOP, do not proceed to the next step. Writes already completed in earlier steps/sections are not rolled back.
- Do not re-ask for information already confirmed earlier in this same run.
- If re-checking pre-conditions before Section 3 onward reveals a required artifact is missing, stop there — do not attempt to skip ahead to a later step.
- If the `artifact__list` re-check call itself fails, treat it the same as a Pre-Flight failure (MCP server not running) and stop immediately.
- This command does not create or modify dep-graph nodes directly — all dep-graph updates happen inside each step's own sub-flow, unchanged from running that command standalone.
- Do not alter, shorten, or reinterpret any step of the seven underlying command files — read and follow them exactly as written.
- This command must not assume prior steps ran through this sequencer — always re-derive status fresh from `artifact__list`. Steps completed by running the underlying commands directly are fully interchangeable with steps completed through this sequencer.
- Before running Tech Core (Section 7), always check screen business-spec coverage and warn explicitly if none exist yet — do not silently proceed.
- Never skip the Section 9 checkpoint once all 7 steps are done — it is a permanent exception (`CLAUDE.md` §8), always blocking regardless of Autonomy level, even at `autopilot`.
- Do not alter, shorten, or reinterpret `asdlc-revise-project.md` when delegating to it from the checkpoint's REVISI branch — read and follow it exactly as written, starting at its Section 2.
