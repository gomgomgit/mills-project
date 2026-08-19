---
description: Entry point for revising something in the project when you don't know which layer needs to change — triages between a specific screen and a project-wide issue, then delegates to the appropriate diagnostic router
allowed-tools:
  - Read
  - mcp__asdlc__artifact__list
---

You are running the `asdlc-revise` command.

This command is the single entry point for "something is not quite right, but I don't know where to start." You describe the problem; this command determines whether it belongs to one specific screen or to the project as a whole, then delegates to the appropriate diagnostic router — `asdlc-revise-screen` or `asdlc-revise-project` — which does the detailed diagnosis and executes the fix.

If you already know the issue is about one specific screen, or about the project as a whole, you can call `/asdlc-revise-screen` or `/asdlc-revise-project` directly and skip this triage step.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Triage

### Step 1 — Get Problem Description

If `problem_description` was already provided by a calling command (e.g. a phase-boundary
checkpoint in `asdlc-fast-screen.md`) → use it directly, skip to Step 2.

Otherwise ask:
> **What would you like to revise, or what looks wrong?** Describe it in your own words.

Wait for the user's answer. Save as `problem_description`.

### Step 2 — Determine Scope

Classify `problem_description` as **screen-level** or **project-level**:

- **Screen-level** — the description is clearly about one specific screen's appearance, content, or behavior, AND does not reference something that would plausibly affect other screens too (e.g. "the submit button on the registration form is misplaced," "the task list doesn't show the due date").
- **Project-level** — the description references something that could plausibly affect more than one screen or the system as a whole, even if it was only noticed on one screen — for example: authentication/login behavior, error message format, entity/data structure, API conventions, overall navigation shell, tech stack, or anything described as a pattern rather than a one-off ("all the list screens...", "the way errors are shown...", "the login doesn't actually check the password right"). When in doubt, prefer **project-level** — a project-level fix can still end by refreshing just the one screen you care about (see `asdlc-revise-project`'s propagation step), so misclassifying as project-level is lower-risk than the reverse.

If genuinely ambiguous even after applying the above → ask the user directly:
> Is this about one specific screen, or something that could affect the project more broadly?

Save the answer as `scope` (`screen` or `project`).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Delegate

### If `scope == screen`

Read the full file `.claude/commands/asdlc-revise-screen.md` with the `Read` tool.

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written, passing `problem_description` as the initial problem description (its own Section 3 will detect this is already provided and skip asking again).

### If `scope == project`

Read the full file `.claude/commands/asdlc-revise-project.md` with the `Read` tool.

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written, passing `problem_description` as the initial problem description (its own Section 1 will detect this is already provided and skip asking again).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- This command performs triage only — it does not diagnose which specific artifact/layer is responsible. That detailed diagnosis belongs entirely to `asdlc-revise-screen` or `asdlc-revise-project`.
- When ambiguous, prefer classifying as project-level over screen-level (lower risk of under-scoping a fix).
- Do not alter, shorten, or reinterpret any step of the delegated command files — read and follow them exactly as written.
