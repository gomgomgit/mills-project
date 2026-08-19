---
description: Phase 1-Foundation — Generate or update PRD
allowed-tools:
  - Read
  - Edit
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p1:fnd-1-prd` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 1 · Foundation — 1-prd                        ║
╚══════════════════════════════════════════════════════╝
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Step 5 (HITL Gate or Digest) is blocking or a digest. See `.claude/PATTERNS.md` § HITL Gate vs Digest. This gate is blocking only at `careful`; it digests at `autopilot`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Interview

### Step 1 — Load Context

Call `mcp__asdlc__artifact__read_scheme("project.1-foundation.prd")` and save the result.
Use the field descriptions throughout the interview and summary.

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`:
- `{"error": ...}` → report error verbatim and stop.
- `{"data": null}` → PRD does not exist yet. Set `existing_ver = 0`. Continue to Step 2.
- `{"data": {...}}` → PRD already exists. Save `existing_ver = data["ver"]`. Then:
  1. Display the current PRD content clearly.
  2. Using the scheme fields as a guide, ask:
     > **Which fields do you want to update?** (e.g. overview, goals, constraints — or "all")
  3. For each selected field, ask for the new value **one at a time**.
  4. Initialize `derived_assumptions = []` (update mode collects fields directly from the user,
     so this normally stays empty — but Step 5's audit pass references it, so it must exist).
  5. After all selected fields are collected, skip to Section 2 — Step 5 (HITL Gate or Digest)
     with the updated data pre-filled. Unchanged fields carry over from the existing PRD.

### Step 2 — Describe & Name the Application

Ask the user to describe the application and give it a name, in a single message:

> **Please describe your application briefly — what it does, who it's for, and what problem it solves. What would you like to call it? (No name yet? Just say so and I'll suggest a few options.)**

Wait for the answer. Record the description. Use it as the basis for all subsequent steps.

Extract the name from the answer:
- If the user gave a name → record it as the confirmed name.
- If the user did not give a name (or said they don't have one yet) → propose 2–3 name options based on the description, then wait for confirmation.

Record the confirmed name. Use it consistently throughout this session.

### Step 3 — Understand the Idea

Before generating questions, analyse the application idea from Step 2:
- What domain is this? (e-commerce, fintech, HR, health, SaaS, internal tool, etc.)
- Who are the likely users?
- What are the main business processes?
- What are the most likely risks or constraints?

Use this analysis to generate **5 critical questions specific to this idea** —
not generic questions that could apply to any app.

Criteria for a good question:
- The answer will significantly change scope, features, or constraints
- Cannot be answered from the idea description alone
- Specific to this domain and context

### Step 4 — Interview

**Autonomy fast-path — if Autonomy level is `autopilot`:** do NOT ask the 5 critical questions
(4a) and do NOT wait for the 4b CONFIRM ALL. Derive best-guess answers to all five questions from
the Step 2 description and Step 3 analysis, then derive `initial_actors`, `constraints`,
`success_metrics` (4b) and `overview`, `problem_statement`, `goals`, `non_goals`, `assumptions`
(4c) directly. Log **every** field you determine this way to `derived_assumptions`
(reason: "autopilot: derived from description, not user-stated"), then go straight to Step 5.
Note: Step 2 (the application description) is the one thing autopilot still requires — it is the
seed input, not a clarification, so it is always collected.

**Otherwise (Autonomy level `careful`)** — run 4a/4b/4c below.

**4a. Ask the 5 critical questions one at a time.**

For each question:
- Provide 3–4 specific, contextual options
- Always include: "Other — [describe]"
- If an answer is ambiguous, clarify before moving on
- If a later question is already answered by a previous answer, skip it and say so

Format:
> **[N]. [Specific question]**
>
> A) [option]
> B) [option]
> C) [option]
> D) Other — [describe]

**4b. Propose Actors, Constraints & Success Metrics as assumptions — confirm together, not one at a time.**

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this command's
current execution. Even if this command is running inside a sequencer (`fast-bootstrap`) and
other commands' assumptions are still visible earlier in the conversation, do not include them —
those belong to a different artifact and are already logged under their own file (see
`.claude/PATTERNS.md` § Derived Assumptions Log). This happens **once**. Two different loops return to
this paragraph later — 4b's own `REVISE [field]` below (line ~122) and a Step 5 gate-level
REVISE — **neither** may re-run this line: doing so would wipe out entries already logged for
the fields that were *not* revised.

Derive a first-draft value for each of these three fields, using the domain analysis (Step 3), the 5 critical answers (4a), and the description (Step 2):
- **Actors** — who will use the system, and any distinct permission levels
- **Constraints** — business, technical, time, or budget limitations
- **Success metrics** — how success will be measured

If a field has no derivable signal at all, state that honestly as the assumption (e.g. "no constraints apparent from context") rather than inventing one.

Present all three together as assumptions, tagged ⚠, in a single message:

> **Based on what you've told me, here's what I'd assume — correct anything that's off:**
>
> ⚠ **Actors:** [derived actors]
> ⚠ **Constraints:** [derived constraints, or "none apparent from context"]
> ⚠ **Success metrics:** [derived success metrics]
>
> **CONFIRM ALL / REVISE [field name]**

- **CONFIRM ALL** → record all three as given, continue to 4c
- **REVISE [field]** → ask for the corrected value for that field only, then re-display and re-confirm before continuing

The ⚠ mark and phrasing like "none apparent from context" are for display only — never store
them literally in the data. When constructing the data object (Step 6): `initial_actors` and
`success_metrics` are plain string lists; `constraints` is a list of `{type, description}`
objects (`type` is one of `business` / `technical` / `time` / `budget`). If nothing was
derived and the user confirms there's genuinely none, store an empty list `[]` — not a
placeholder entry.

**Log to `derived_assumptions` immediately** (see `.claude/PATTERNS.md` § Derived Assumptions Log) — the
moment each of the three fields is derived above (before CONFIRM ALL, not after): append
`{field: "initial_actors"|"constraints"|"success_metrics", value: <derived value>, reason: "no
explicit statement from user"}`. If the user later REVISEs a field, remove its entry (it's no
longer an assumption — the user just stated it) rather than leaving a stale one.

**4c. Derive remaining fields from the interview.**

Do NOT ask these one by one. Instead, derive them from everything gathered:
- `overview`, `problem_statement`, `goals`, `non_goals`, `assumptions`

As you write each one, apply the same test as 4b: is this a faithful compilation of what the
user already said, or does it introduce something not actually discussed (e.g. a goal you
inferred but never confirmed)? Only the latter gets logged to `derived_assumptions` — a
straightforward summary of stated facts is not an assumption.

For each derived field, state it to the user:
> "Based on what you've told me, I'll write: [derived value]"

Only ask explicitly if a field is genuinely empty and cannot be derived.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Confirm & Write

### Step 5 — HITL Gate or Digest

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished 4b/4c output
once against the interview transcript. Confirm every entry in `derived_assumptions` is genuinely
not stated by the user, and spot-check that nothing else should have been logged. Add any missed
entries now, before continuing.

**If Autonomy level is `careful`:**

Display a summary of all gathered information:

> **Summary before writing:**
>
> **Name:** [title]
> **Overview:** [overview]
> **Problem statement:** [problem_statement]
> **Goals:** [goals]
> **Non-goals:** [non_goals]
> **Actors:** ⚠ [initial_actors]
> **Assumptions:** [assumptions]
> **Constraints:** ⚠ [constraints]
> **Success metrics:** ⚠ [success_metrics]
>
> **GO / REVISE / STOP**

- **GO** → proceed to Step 6
- **REVISE** → ask which part to correct, return to the relevant step
- **STOP** → stop here, do nothing further

**If Autonomy level is `autopilot`:**

Proceed directly to Step 6 (no wait). After writing, display the Review Digest (§ HITL Gate
vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated in 4b/4c as the ⚠
block, then continue without waiting. If the user corrects something afterward, apply the
inline-correction + versioning rule from `.claude/PATTERNS.md`.

### Step 6 — Write PRD

Construct the `data` object from everything gathered:
- New PRD → use everything from Steps 2–4
- Existing PRD update → use the updated fields collected in Step 1, with unchanged fields carried over from the existing PRD

Set `meta.title` to the confirmed application name.
Set `meta.updated_at` to today's date (YYYY-MM-DD).
Set `ver` to `existing_ver + 1` (this is `1` for a new PRD, or incremented for an update).

Call:
```
mcp__asdlc__artifact__write(
  artifact_key = "project.1-foundation.prd",
  data         = <constructed data object>
)
```

If the result contains `"error"` — STOP. Report the error verbatim.

Save from the result:
- `path` — path of the written file
- `changed_fields` — list of fields that changed

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/project.1-foundation.prd.md` (treat as empty if
not found), append a `## v<ver> — <today's date>` section listing each entry, then `Write` the
file back. Skip entirely if `derived_assumptions` is empty.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Post-Write

### Step 7 — Invoke dep-graph-sync-agent

Delegate to the `dep-graph-sync-agent` agent with:

```
artifact_key   = "project.1-foundation.prd"
changed_fields = <changed_fields from Step 6>
depends_on     = []
```

`depends_on = []` because PRD is the root artifact — it has no upstream dependencies.

Wait for the agent to confirm before continuing.

### Step 8 — Update CLAUDE.md

Read the written PRD:
```
mcp__asdlc__artifact__read("project.1-foundation.prd")
```

Extract `meta.title` and `overview` from the result.

Edit `CLAUDE.md` — update Section 1 only:
- Replace the `**Name:**` line with: `**Name:** <meta.title>`
- Replace the `**Description:**` line with: `**Description:** <overview>`

Do not touch any other part of CLAUDE.md.

### Step 9 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_ver was 0]: Wrote PRD (new) — <meta.title>
  [if existing_ver > 0]:   Updated PRD — changed: <changed_fields from Step 6>

Artifacts written
  project.1-foundation.prd   v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  /asdlc-p1:fnd-2-arch-spec
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- At Autonomy level `careful`: never skip the Step 5 HITL gate — always wait for GO before writing. Do not continue to Step 6 if the user answers STOP.
- At `autopilot`: Step 5 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass at the start of Step 5 — it is what catches `derived_assumptions` entries missed during 4b/4c, regardless of Autonomy level
- At Autonomy level `autopilot`: skip the 4a questions and the 4b CONFIRM ALL — derive all fields as assumptions (Step 2 description is still always collected). See the Step 4 fast-path.
- At `careful`: never ask the 5 critical questions (4a) all at once — keep that part conversational, one at a time. 4b is a deliberate exception — batch it as one CONFIRM ALL / REVISE message.
- Do not continue to Step 7 if artifact__write returns an error
- Do not continue to Step 8 if dep-graph-sync-agent reports an error
- Do not continue to Step 9 if Step 8 (Update CLAUDE.md) fails
- Only update Section 1 in CLAUDE.md — do not touch any other section
