---
description: Diagnose and fix an issue with a specific already-implemented screen — describe what looks wrong, this command identifies which layer needs to change (UIUX type-pattern, business spec, tech spec, or implementation code), then executes the fix end-to-end
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

You are running the `asdlc-revise-screen` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Screen Revision                                     ║
╚══════════════════════════════════════════════════════╝
```

This command helps you fix something that looks wrong on a screen you have already implemented, without needing to know upfront which layer — UIUX design pattern, business spec, tech spec, or the implementation code itself — actually needs to change. You describe the problem in plain language; this command diagnoses which layer is the root cause, confirms with you, then executes the fix by delegating to the appropriate existing command(s).

This command is a **diagnostic router**, not a duplicate. Once the root cause is identified, it reads the corresponding command file(s) and follows their instructions verbatim — exactly the same principle used by `asdlc-fast-screen`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check that `project.2-business-spec.screen-index` has `status: "written"` in `artifact_index`.
If not → STOP. Report: "Pre-condition not met: screen-index has not been written. Run `/asdlc-p2:bus-1-scope` first."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Select Screen

**If `target_screen` and/or an initial problem description were already provided by a calling command (e.g. `/asdlc-revise`)** → use the provided values, skip whichever of Steps 1–2 already has its input, and go directly to Section 2.

### Step 1 — Read screen-index

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

### Step 2 — Present Screen List

For each screen in `screen_index.screens`, check `artifact_index` for `{module_id}.{screen_id}.4-implement` status.

> **Which screen has the issue?**
>
> [Module Name]
>   1. [screen name] ([screen ID])   [impl ✓ if written / — not implemented yet]
>   2. ...
>
> Type the number of your choice.

Wait for the user's answer. Save the selected screen as `target_screen`.

If the selected screen's `4-implement` status is **not** `written` → STOP. Report: "This screen has not been implemented yet — there is nothing to compare against. Run `/asdlc-fast-screen` to build it first."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Load Context

### Step 3 — Load All Four Layers

Call `mcp__asdlc__artifact__read("project.1-foundation.uiux-spec")`. Save as `uiux_spec`.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.2-business-spec")`. Save as `screen_biz_spec`.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.3-tech-spec")`. Save as `screen_tech_spec`.

Call `mcp__asdlc__artifact__read("{target_screen.module_id}.{target_screen.id}.4-implement")`. Save as `screen_impl`.

Once all four are loaded: from `uiux_spec.screen_type_patterns`, identify the entry whose `type` most plausibly matches `target_screen` — infer this from `target_screen.name` and `screen_biz_spec` content, since `screen-index` entries do not carry an explicit `type` field. **This is a best-effort inference, not a guaranteed lookup** — flag your inferred type explicitly when presenting the diagnosis in Section 3, so the user can correct it if wrong. Save the matched entry as `matched_type_pattern` (or `null` if no plausible match).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Describe & Diagnose

### Step 4 — Get Problem Description

If an initial description was already provided by a calling command → use it directly, skip to Step 5.

Otherwise ask:
> **What looks wrong on this screen?** Describe it in your own words — no need to know which part of the system is responsible.

Wait for the user's answer. Save as `problem_description`.

### Step 5 — Diagnose

Compare `problem_description` against the four loaded layers and classify the root cause into exactly one of:

- **UIUX-Spec** — the issue is about the visual/layout pattern for this screen's type (e.g. area arrangement, navigation placement, which states exist) — something defined in `matched_type_pattern`.
- **Business Spec** — the issue is about what information is shown, what actions are available, or a business rule — something defined in `screen_biz_spec`.
- **Tech Spec** — the issue is about data/API behavior, a validation, or backend logic — something defined in `screen_tech_spec`.
- **Implementation** — `matched_type_pattern`, `screen_biz_spec`, and `screen_tech_spec` all already describe the correct behavior, but the generated code in `screen_impl` does not follow them.

Present the diagnosis:

> **Diagnosis:** This looks like a **[layer]** issue.
> Reasoning: [1–2 sentences citing the specific field/content that supports this]
> [if layer == UIUX-Spec] Inferred screen type: **[matched_type_pattern.type]** (best-effort guess — correct me if this is wrong)
>
> **CONFIRM / [state the correct layer instead] / CANCEL**

- **CONFIRM** → proceed to Section 4 using the diagnosed layer.
- **[correct layer]** → use the user-specified layer instead, proceed to Section 4.
- **CANCEL** → STOP. Report: "No changes made."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Route & Execute

Based on the confirmed layer from Step 5:

### If UIUX-Spec

Read the full file `.claude/commands/asdlc-p1/fnd-3-uiux-spec.md` with the `Read` tool.

Execute its instructions starting from its own **Pre-Flight** section onward, exactly as written. Note before starting: this artifact is project-level — the fix will apply to **every** screen of this type, not only `target_screen`. This is surfaced naturally in that command's own HITL gate before anything is written.

**If that flow ends in STOP** → STOP this entire command here. Display: "Stopped before the UIUX-Spec update. No changes made."

**If that flow completes successfully** → continue: Read the full file `.claude/commands/asdlc-fast-screen.md` with the `Read` tool. Execute its instructions starting from its **Section 2** onward, with `target_screen` already set to the screen selected in Section 1 and `starting_phase = 2` (its own Section 1 will detect these are already provided and skip itself automatically). This refreshes the business spec, tech spec, and implementation for `target_screen` against the updated UIUX-Spec.

### If Business Spec, Tech Spec, or Implementation

Read the full file `.claude/commands/asdlc-fast-screen.md` with the `Read` tool.

Execute its instructions with `target_screen` already set to the screen selected in Section 1 and `starting_phase` set to:
- `2` if the diagnosed layer is Business Spec
- `3` if the diagnosed layer is Tech Spec
- `4` if the diagnosed layer is Implementation

Its own Section 1 hook will detect these are already provided and route directly to the matching section (Section 2, 3, or 4).

**If that flow ends in STOP at any point** → STOP this entire command here.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Final Summary

Display a combined summary covering everything that ran this session:

```
Diagnosis
  Layer: [diagnosed layer] [+ inferred screen type if UIUX-Spec]

What ran
  [if UIUX-Spec]: UIUX-Spec update — [written v<ver> / stopped]
  Business Spec  [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  Tech Spec      [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]
  Implementation [written v<ver> / updated v<ver> / skipped — already done / stopped / not reached]

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Never skip the diagnosis confirmation (Section 3) — always let the user confirm or correct before routing.
- Never skip a HITL gate in any delegated flow — this command does not introduce a shortcut gate of its own.
- If the diagnosed layer is UIUX-Spec, always inform the user this affects every screen of the same type before that flow's own HITL gate — do not treat it as scoped to only `target_screen`.
- If a delegated flow ends in STOP, do not proceed further. Writes already completed are not rolled back.
- Do not proceed past Section 1's screen selection if the selected screen has not been implemented yet (`4-implement` not written) — redirect to `asdlc-fast-screen` instead.
- Do not alter, shorten, or reinterpret any step of the underlying command files — read and follow them exactly as written.
- The screen-type inference in Section 2 is best-effort — always surface it explicitly in the diagnosis so the user can correct it.
