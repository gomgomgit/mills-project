---
description: Diagnose and fix a project-wide issue — describe what looks wrong, this command identifies which of the 7 project-level commands is the root cause, executes the fix, then propagates the change to affected screens
allowed-tools:
  - Read
  - Edit
  - Bash
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__track_node
  - mcp__asdlc__dep_graph__sync_stale_status
  - mcp__asdlc__dep_graph__get_stale_nodes
---

You are running the `asdlc-revise-project` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Project Revision                                    ║
╚══════════════════════════════════════════════════════╝
```

This command helps you fix something that looks wrong at the project level — an architecture decision, a business rule, a shared technical decision, or the generated scaffold/shared modules — without needing to know upfront which of the seven project-level commands is actually responsible. You describe the problem in plain language; this command diagnoses the root cause, confirms with you, executes the fix, then checks what else became stale as a result and helps you propagate the fix to any affected screens.

This command is a **diagnostic router**, not a duplicate. Once the root cause is identified, it reads the corresponding command file and follows its instructions verbatim — exactly the same principle used by `asdlc-fast-bootstrap`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Describe & Diagnose

### Step 1 — Get Problem Description

If an initial description was already provided by a calling command (e.g. `/asdlc-revise`) → use it directly, skip to Step 2.

Otherwise ask:
> **What looks wrong at the project level?** Describe it in your own words — no need to know which command is responsible.

Wait for the user's answer. Save as `problem_description`.

### Step 2 — Diagnose

Compare `problem_description` against the status and content of all project-level artifacts in `artifact_index` (read whichever ones are relevant to form a confident diagnosis — e.g. `artifact__read` on `project.1-foundation.prd`, `arch-spec`, etc. as needed). Classify the root cause into exactly one of the seven project-level commands:

- **PRD** (`/asdlc-p1:fnd-1-prd`) — overview, goals, problem statement, constraints
- **Architecture Spec** (`/asdlc-p1:fnd-2-arch-spec`) — tech stack, architecture pattern, deployment, NFR, integrations
- **UIUX Specification** (`/asdlc-p1:fnd-3-uiux-spec`) — design system, layout shell, screen type patterns (applies to more than one screen type/instance at once)
- **Test Strategy** (`/asdlc-p1:fnd-4-test-strategy`) — coverage thresholds, test scope, auto-fix policy
- **Scope** (`/asdlc-p2:bus-1-scope`) — actors, modules, which screens exist, usecase overview
- **Tech Core** (`/asdlc-p3:tech-1-core`) — entity catalog, shared decisions (auth, error format, pagination, naming conventions, integrations)
- **Implementation Core** (`/asdlc-p4:impl-1-core`) — project scaffold, entity model files, shared infrastructure modules

Present the diagnosis:

> **Diagnosis:** This looks like a **[command name]** issue.
> Reasoning: [1–2 sentences citing the specific field/content that supports this]
>
> **CONFIRM / [state the correct command instead] / CANCEL**

- **CONFIRM** → proceed to Section 2 using the diagnosed command.
- **[correct command]** → use the user-specified command instead, proceed to Section 2.
- **CANCEL** → STOP. Report: "No changes made."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Execute the Fix

Read the full file corresponding to the confirmed command with the `Read` tool (e.g. `.claude/commands/asdlc-p1/fnd-1-prd.md`, `.claude/commands/asdlc-p3/tech-1-core.md`, etc.).

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written — its own update-mode logic applies if the artifact already exists.

**If that flow ends in STOP** → STOP this entire command here. Display: "Stopped before completing the fix. No changes made."

**If that flow completes successfully** → continue to Section 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Propagate Downstream

### Step 3 — Check What Became Stale

Call `mcp__asdlc__dep_graph__get_stale_nodes`. Save result as `stale_nodes`.

If `stale_nodes` is empty → skip to Section 4.

### Step 4 — Handle Screen-Level Stale Nodes

Identify all distinct screens (by `{module_id}.{screen_id}`) that appear in `stale_nodes` (any of their `2-business-spec` / `3-tech-spec` / `4-implement` nodes).

**If exactly one screen is affected** (or it is the same screen the user originally referenced, if any) → proceed automatically: for that screen, read and execute `.claude/commands/asdlc-fast-screen.md` with `target_screen` set to that screen and `starting_phase` set to the earliest stale phase for it (2, 3, or 4) — its own Section 1 hook will route directly to the matching section.

**If more than one screen is affected** → do not auto-run all of them. Display:
> ⚠ This change affected **[N] screens**: [list, one per line, with which phase is stale for each]
>
> Run the fix for all of them now, select specific ones, or skip and handle later? [all / select / skip]

- **all** → for each affected screen in order, read and execute `.claude/commands/asdlc-fast-screen.md` with `target_screen` and `starting_phase` (earliest stale phase) set for that screen — its own Section 1 hook will route directly to the matching section.
- **select** → present the list as a numbered multi-select, then run only the selected screens the same way.
- **skip** → do not run any. Note in the final summary that these screens remain stale.

### Step 5 — Handle Remaining Project-Level Stale Nodes

For any remaining stale nodes with a `project.` prefix (not consumed by Step 4), resolve the fix command using the same mapping as `asdlc-check-stale.md`'s Fix Command Mapping table. Report these in the final summary as recommendations — **do not auto-execute further project-level commands** in this same run (avoid recursive cascades); the user can re-run `/asdlc-revise-project` or the specific command directly afterward.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Final Summary

Display a combined summary:

```
Fix applied
  [command] — [written v<ver> / updated v<ver> / stopped]

Screens affected
  [if none]: None
  [if handled]: [N] screens updated — [list]
  [if skipped]: [N] screens still stale — [list] — run /asdlc-fast-screen or /asdlc-revise-screen for each when ready

Other stale project-level artifacts
  [if none]: None
  [if any]: [list artifact — recommended fix command]

Dep-graph
  [if no stale nodes remain]: All nodes clean
  [if stale nodes remain]:    <N> stale — [list node keys, one per line, indented]
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Never skip the diagnosis confirmation (Section 1) — always let the user confirm or correct before executing.
- Never skip a HITL gate in any delegated flow.
- If the fix command's flow ends in STOP, do not proceed to Section 3. Nothing downstream is checked or run.
- Never auto-run the fix for more than one affected screen without the user explicitly choosing "all" or selecting specific ones in Step 4 — this is the one exception to full auto-execute, to avoid an unbounded chain of interviews the user did not ask for.
- Never auto-execute additional project-level commands beyond the one diagnosed in Section 1, even if more become stale — only report them.
- Do not alter, shorten, or reinterpret any step of the underlying command files — read and follow them exactly as written.
