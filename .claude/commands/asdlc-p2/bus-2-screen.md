---
description: Phase 2-Business-Spec — Deep-dive business spec for one screen
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p2:bus-2-screen` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 2 · Business Spec — 2-screen                  ║
╚══════════════════════════════════════════════════════╝
```

You are acting as a **Business Analyst**. Your perspective is purely business — you do not know or discuss technology, architecture, implementation, or technical decisions. Everything you ask and say is from the user's business domain.

This command conducts a deep, conversational business specification for a single screen. The goal is to understand the screen so thoroughly that a developer could implement it correctly without ever asking a business question. Prioritise storytelling and exploration over structured Q&A.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — all three keys must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.prd`
  - `project.1-foundation.uiux-spec`
  - `project.2-business-spec.screen-index`
  If any is `"not_started"` → STOP.
  "Pre-condition not met: [key] has not been written. Run [command] first."
  (PRD → `/asdlc-p1:fnd-1-prd`
   UIUX-Spec → `/asdlc-p1:fnd-3-uiux-spec`
   screen-index → `/asdlc-p2:bus-1-scope`)

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means; see `.claude/PATTERNS.md` § HITL Gate vs Digest. Four independent points in this command read it: Step 6 (refine loop — runs at `careful`, skipped at `autopilot`), Step 7 (usecase batch confirm — blocking at `careful`, digest only at `autopilot`), Step 8 (`test_priority` confirm — blocking at `careful` only, digest at `autopilot`), and Section 3 (main gate — blocking at `careful` only, digest at `autopilot`).

Note the Mock Generation level — same file, key `mock_generation_level` (default `"none"` if the file is not found). See `CLAUDE.md` §9 for what each level means. Only `full` runs Step 14 (screen-mock-agent) — `none` skips it, since it does not generate a per-screen mock.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Select Screen & Load Context

### Step 1 — Load Schemes

Call `mcp__asdlc__artifact__read_scheme("project.2-business-spec.screen-index")` — save as `screen_scheme`.
Call `mcp__asdlc__artifact__read_scheme("project.2-business-spec.usecase-index")` — save as `usecase_scheme`.

### Step 2 — Read Indexes & Foundation

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.usecase-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `usecase_index`. If `{"data": null}`, treat as `{"usecases": [], "ver": 0}`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.actor-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `actor_index`. If `{"data": null}`, treat as `{"actors": []}`.

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `prd`.

Call `mcp__asdlc__artifact__read("project.1-foundation.uiux-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `uiux_spec`.

### Step 3 — Select Screen

**If `target_screen` was already provided by a calling command (e.g. `/asdlc-fast-screen`)** → skip this step entirely, use the provided value as `target_screen`, and go directly to Step 4.

Otherwise, present the list of screens from `screen_index.screens`, grouped by module:

> **Which screen would you like to specify?**
>
> [Module Name]
>   1. [screen name] ([screen ID])
>   2. ...
> [Module Name]
>   3. ...
>
> Type the number of your choice.

Wait for the user's answer. Save the selected screen as `target_screen` (the full entry from `screen_index.screens`).

### Step 4 — Load Existing Screen Artifact

Construct the module key: the module ID comes from `target_screen.module_id`. Use the screen ID from `target_screen.id` as the screen_id part. Phase is `2-business-spec`.

Example: if `module_id = "module-auth"` and `id = "screen-001--login"`:
key = `module-auth.screen-001--login.2-business-spec`

Call `mcp__asdlc__artifact__read_scheme` on this key — save as `screen_artifact_scheme`.

Call `mcp__asdlc__artifact__read` on this key:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Screen artifact does not exist yet. Set `existing_screen_ver = 0`. Continue to Section 2.
- `{"data": {...}}` → Already exists. Save `existing_screen_ver = data["ver"]`. Then:
  1. Display the current screen spec clearly.
  2. Ask: **Which sections do you want to update?** (e.g. business_rules, available_actions — or "all")
  3. Initialize `derived_assumptions = []` before conducting any of the steps below (Step 8's
     audit pass and Section 3's digest reference it, so it must exist even if Step 5 itself is
     skipped for unselected sections).
  4. For each selected section, conduct the relevant steps from Section 2.
  5. Skip unselected sections — carry over their data unchanged.
  6. Skip to Section 3 (HITL Gate or Digest) with updated data pre-filled.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Draft & Refine

Before engaging the user, synthesize a complete first-cut draft from all loaded context. Present the draft to the user, then refine through discussion — one topic per turn.

**Question style — present options, not open text.** For any question whose answer is enumerable or a confirm/adjust, present labeled options (`A)`/`B)`/`C)` …, recommended first + `✓`, always with a final `Other — [describe]`) so the user answers with a letter — see `.claude/PATTERNS.md` § Interview Question Style. Derive options from context; keep free-text only for genuinely open inputs (naming, free lists, describing a correction).

Do not mention technical concepts. If the user brings up technical details, acknowledge and steer back:
> "Got it — I'll note that for the technical phase. Let's stay focused on the business side for now."

### Step 5 — Synthesize & Present Draft

**Autonomy fast-path — if Autonomy level is `autopilot`:** still perform the synthesis and
`derived_assumptions` logging described in this step (the draft is needed to write the artifact),
but do NOT present the draft with a "how does this look?" question and do NOT run Step 6's refine
loop — treat the synthesized draft as accepted and proceed directly to Step 7. **Otherwise
(`careful`)** — present the draft and refine as described below.

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution (this one screen). Even if this command is running inside a
sequencer (`fast-screen`) and other screens'/commands' assumptions are still visible earlier
in the conversation, do not include them — those belong to different artifacts and are already
logged under their own files (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — Step 6's refine loop and any later REVISE at Section 3 must not re-run this line
and wipe out entries already logged.

Draw from all loaded context: `target_screen` (name, module, one-line description from screen-index), `prd` (goals, problem statement), `uiux_spec` (screen type patterns), `actor_index` (all actors and their roles), and `usecase_index` (existing usecases that may apply to this screen).

Consult `screen_artifact_scheme._tracked` to ensure the draft covers all tracked fields. Use `screen_artifact_scheme` field descriptions as the definition for what each field should contain.

For `usecase_ids`: scan `usecase_index.usecases` for usecases whose `screen_ids` already includes `target_screen.id`, or whose name/description suggests a match with this screen.

Construct a draft for all tracked fields, then present it:

> **Here's my initial concept for [Screen Name]:**
>
> **What it does:** [description]
>
> **Who uses it:** [actor names]
> **How they get here:** [entry_points]
>
> **Information shown:**
> [information_displayed items]
>
> **Available actions:**
> [for each: Action — brief description (who can do it)]
>
> **Business rules I'd expect:**
> [list, or "None identified yet"]
>
> **Candidate usecases:**
> [list — existing matches from usecase_index + proposed new ones, or "None identified yet"]
>
> **Edge cases I'd anticipate:**
> [list, or "None identified yet"]
>
> **Open questions:**
> [list, or "None identified yet"]
>
> How does this look?
>
> A) Looks right — accept and continue ✓
> B) Adjust — tell me what's off, missing, or needs more detail

Log to `derived_assumptions` as you build this draft: any business rule, edge case, or entry
point you proposed rather than the user describing it directly. If the user confirms the draft
without correcting that item, keep the entry; if they correct it in Step 6, remove it (it's now
their statement, not an assumption).

### Step 6 — Refine

(Skipped entirely at `autopilot` — see the Step 5 fast-path.)

Based on the user's response, refine the draft section by section. Use `screen_artifact_scheme` field descriptions as the definition for what each field should contain.

For each section the user flags or wants to expand:
- Present the current draft value for that section
- Ask targeted follow-up questions — one at a time
- Update the draft

Sections the user has confirmed are final — do not revisit them. If the user confirms the full draft is correct, proceed directly to Step 7.

### Step 7 — Derive & Confirm Usecases (single batch)

All usecases on this screen are confirmed in **one** exchange — not one question per usecase.

**7a. Existing usecases (from `usecase_index`) — do not ask.**

Match them silently and add them to `usecase_ids`. Their content is not re-interviewed here — the screen command that originally defined them is the source of truth. The only change written to an existing usecase artifact (Step 10) is adding this screen's ID to its `related_screen_ids` if not already present. They are listed in the batch block below for visibility only; a wrong match is cheap to fix and does not warrant its own question.

**7b. New usecases — derive a full draft for every in-scope one before asking anything.**

Scope: in **new** mode this covers every new usecase in the draft. In **update** mode it covers only the usecases affected by the sections selected in Step 4 — usecases carried over unchanged are neither re-derived nor re-confirmed.

For each new usecase in scope, derive all of the following from the screen draft confirmed in Steps 5–6 (`available_actions`, `business_rules`, `information_displayed`, `edge_cases`) plus `actor_index`:
- `name` — short human-readable name (e.g. "User Login")
- `id` — next available ID following `usecase-{NNN}--{slug}`; NNN is the next zero-padded sequential number after the highest existing NNN in `usecase_index`; slug is kebab-case of the name
- `description` — one paragraph
- `actors` — which actor IDs participate
- `preconditions` — what must be true before it starts
- `main_flow` — step-by-step, who does what. Each entry matches the artifact schema: `step` (integer), `actor` (actor ID or "System"), `description`
- `alternative_flows` — derive one per relevant entry in `edge_cases` and per failure path implied by `business_rules`. Each entry must match the artifact schema: `name` (short label), `trigger` (the condition, referencing the `main_flow` step number where it branches), and `steps` (list of what happens). A two-part "condition → outcome" shape is not enough.
- `postconditions` — what is true after it succeeds
- `business_rules` — rules specific to this usecase

**7c. Edge case cross-check — a completeness sweep over what 7b produced.**

7b already derives `alternative_flows` from `edge_cases`. This step verifies none were missed. For each entry in `edge_cases`:
- If it is already reflected in `alternative_flows` of a drafted usecase → no action needed.
- If it is not yet reflected → add it to the `alternative_flows` of the most relevant usecase.
- If it cannot be mapped to any usecase (e.g. cross-cutting concerns like session expiry, network failure) → leave it in `edge_cases` only; it will not produce a BDD scenario.

This must happen **before** 7d. The user confirms the usecases in a single block, so anything added afterwards would never be seen — the confirmation would apply to a flow that no longer matches what was written.

**7d. Present all of them at once.**

**If Autonomy level is `careful`:**

> **Usecases on this screen**
>
> [if any existing matched:]
> Linked to existing (content unchanged):
>   [for each: `[id]` · [name]]
>
> New — drafted from this screen's actions, rules, and edge cases:
> [for each in-scope new usecase:]
>   **[id] · [name]**
>   [description]
>   Actors: [actor names]  ·  Preconditions: [list or "none"]
>   Main flow:
>     1. [actor/System] — [description]
>     2. ...
>   Alternative flows:
>     · **[name]** — trigger: [trigger]
>       [steps, one per line, indented]
>     (or "none derived — see question below")
>   Postconditions: [list]
>   Business rules: [list or "none"]
>
> [Include this line only when at least one new usecase has an empty `alternative_flows`:]
> ⚠ I could not derive any alternative flow for: [list those usecase names]. Is there
>   genuinely nothing that can go wrong there, or did I miss something?
>
> **CONFIRM ALL / REVISE [usecase-id]**

- **CONFIRM ALL** → accept every usecase as drafted, proceed to Step 8. The edge case cross-check has already run in 7c.
- **REVISE [usecase-id]** → ask what is wrong with that usecase only, update it, re-display this block. Do not re-ask about usecases the user did not name.
- **A free-form answer to the ⚠ question** (e.g. "yes — if stock runs out it should fail") → treat it as a REVISE of the usecase it refers to: add the missing flow, then re-display this block. Do not treat it as a CONFIRM ALL.

The ⚠ line matters: a drafted flow anchors the reader on what is written, which makes a **missing** alternative flow much harder to notice than a wrong one. Stating explicitly where nothing was derived is what keeps that gap visible. Do not omit it when it applies.

**If Autonomy level is `autopilot`:**

Skip the blocking prompt — treat every drafted usecase (including the edge-case cross-check
from 7c) as accepted, and proceed directly to Step 8. Log each new usecase to
`derived_assumptions` (`field` = the usecase ID, `value` = a one-line summary, `reason` =
"drafted from screen spec, not individually confirmed"), and if any usecase has an empty
`alternative_flows`, log that specifically too — so the gap is still visible at the one
non-blocking review point this run has, just not blocking on its own.

Record `usecase_ids` — list of all confirmed usecase IDs (existing + new) on this screen.

### Step 8 — Schema Coverage Check

Review `screen_artifact_scheme._tracked`. For each tracked field, verify the current draft has a non-empty value.

If any tracked field is still empty and cannot be derived from context or the discussion so far, ask the user about it — one field at a time.

**Derive `test_priority` — do not ask the user directly.** Apply this logic based on data collected in Steps 5–7:

- `"high"` — screen handles payments, authentication, personal data, legal actions, financial transactions; OR has 5+ business rules; OR has multiple actors with different permission levels
- `"medium"` — screen has meaningful business rules (2–4); OR handles data that affects other users or other screens
- `"low"` — read-only screens, settings with low business impact, informational/display-only pages

**If Autonomy level is `careful`:**

Present the derived value to the user before proceeding:
> "Based on this screen's complexity and sensitivity, I'd rate its test priority as **[value]** because [brief reason — e.g. 'it handles authentication and has 6 business rules']. Does that seem right?"

Adjust if the user disagrees. Store the confirmed value in `screen_draft.test_priority`.

**If Autonomy level is `autopilot`:**

Store the derived value in `screen_draft.test_priority` without waiting for confirmation —
append `{field: "test_priority", value: <value>, reason: <brief reason>}` to
`derived_assumptions` instead, which renders in the Section 3 Review Digest's ⚠ block (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`). A wrong priority only shifts test execution order, which is
why this is safe to derive without asking at `autopilot`.

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished draft once
against the conversation (Steps 5–8). Confirm every `derived_assumptions` entry is genuinely
not stated, and spot-check the rest. Add any missed entries now.

If all tracked fields are populated, proceed to Section 3.
────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — HITL Gate or Digest

**If Autonomy level is `careful`:**

Display a complete summary of everything gathered for this screen:

> **Screen Spec: [Screen Name]**
>
> **Description:** [description]
>
> **Actors:** [list actor names]
> **Entry Points:** [list]
>
> **Information Displayed:**
> [list]
>
> **Available Actions:**
> [for each: Action — description (who can do it)]
>
> **Business Rules:**
> [list]
>
> **Usecases:** [count]
> New usecases:
>   [for each new usecase: ID · Name]
>     Main flow: [N steps]
>     Alt flows: [N]
> Existing usecases (content unchanged):
>   [for each existing usecase: ID · Name]
>
> **Edge Cases:** [list or "None"]
> **Open Questions:** [list or "None"]
>
> **GO / REVISE [section] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section]** → ask for corrections to that section only, return to the relevant step, re-display full summary
- **STOP** → stop here, do nothing further

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block. Continue without waiting. If the user corrects something afterward, apply the
inline-correction + versioning rule from `.claude/PATTERNS.md` — note this command writes multiple
artifacts (screen + usecases + usecase-index), so apply the rule per artifact touched by the
correction.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Write Artifacts

Set `meta.title` to `prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

### Step 9 — Write Screen Artifact

Data source:
- New screen (`existing_screen_ver == 0`) → use all data collected in Steps 5–8.
- Update (`existing_screen_ver > 0`) → for sections selected in Step 4, use data from the interview. For unselected sections, carry over unchanged from the existing artifact. Do not re-derive unchanged fields.

Set `ver` to `existing_screen_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "<module_id>.<screen_id>.2-business-spec",
  data         = <constructed screen data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `screen_artifact_path` and `screen_changed_fields` from result.

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): screen-level
entries in `derived_assumptions` (`test_priority`, business rules, edge cases — anything not
tagged with a usecase ID) — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/<module_id>.<screen_id>.2-business-spec.md`
(treat as empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it
back.

### Step 10 — Write Usecase Artifacts

For each usecase identified in Step 7:

**New usecase** (not previously in `usecase_index`): construct full artifact from the usecase data confirmed in Step 7. Set `related_screen_ids = [target_screen.id]`. Set `ver = 1`.

Delegate to `bdd-spec-writer-agent` with:
```
usecase               = <full usecase data from Step 7>
existing_bdd_scenarios = []
actor_index           = <actor_index from Step 2>
```
Save `result.merged_bdd_scenarios` as `bdd_scenarios` for this usecase. Include it in the artifact data.

**Existing usecase** (already in `usecase_index`): read the existing artifact first.

Delegate to `bdd-spec-writer-agent` with:
```
usecase               = <full usecase data from existing artifact>
existing_bdd_scenarios = <existing artifact bdd_scenarios>
actor_index           = <actor_index from Step 2>
```
Save `result.merged_bdd_scenarios` as `bdd_scenarios` for this usecase.

Check screen ID: if `target_screen.id` is not already in `related_screen_ids`, add it.

Write the artifact with `ver = existing_ver + 1` if either:
- `result.added_count > 0` (new scenarios were added), OR
- Screen ID was missing and added.

If neither applies → skip, no write needed.

For each artifact to write:
```
mcp__asdlc__artifact__write(
  artifact_key = "project.2-business-spec.usecases.<usecase_id>",
  data         = <constructed or updated usecase data>
)
```

If any result contains `"error"` → STOP. Report error verbatim.
Accumulate `usecase_artifact_paths` (only paths for artifacts that were actually written) and `usecase_changed_fields_map`.

**Append to the Derived Assumptions Log**: for each usecase written above, its `derived_assumptions`
entries (tagged with that usecase's ID from Step 7d's autopilot logging) — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.2-business-spec.usecases.<usecase_id>.md`
(treat as empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it
back.

### Step 11 — Update usecase-index

Only if Step 10 wrote at least one usecase artifact (new usecase added, or existing usecase had `related_screen_ids` updated):

Merge: append new usecase entries to `usecase_index.usecases` with `screen_ids = [target_screen.id]`. For existing usecases, add this screen's ID to their `screen_ids` if not already present. Set `ver = usecase_index.ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.2-business-spec.usecase-index",
  data         = <updated usecase-index>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `usecase_index_path` and `usecase_index_changed_fields` from result.
Set `usecase_index_updated = true`.

If Step 10 wrote no usecase artifacts at all: set `usecase_index_updated = false`. Skip to Step 12.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 12 — Invoke dep-graph-sync-agent for screen artifact

Delegate to `dep-graph-sync-agent` with:

```
artifact_key   = "<module_id>.<screen_id>.2-business-spec"
changed_fields = <screen_changed_fields>
depends_on     = [
  "project.1-foundation.prd",
  "project.1-foundation.uiux-spec",
  "project.2-business-spec.screen-index"
]
```

Wait for confirmation before continuing. If it reports an error → STOP.

### Step 13 — Invoke dep-graph-sync-agent for usecase-index (conditional)

Only if `usecase_index_updated = true`:

Delegate to `dep-graph-sync-agent` with:

```
artifact_key   = "project.2-business-spec.usecase-index"
changed_fields = <usecase_index_changed_fields>
depends_on     = ["project.2-business-spec.screen-index"]
```

Wait for confirmation before continuing. If it reports an error → STOP.

### Step 14 — Invoke screen-mock-agent (conditional)

Skip this step if `mock_generation_level != "full"` → set `mock_html_path = null`, `mock_skip_reason = "config"`. Continue to Step 15.

Otherwise, delegate to `screen-mock-agent` with:

```
artifact_key  = "<module_id>.<screen_id>.2-business-spec"
screen_id     = <target_screen.id>
output_folder = ".asdlc/generated/2-business-spec/screens/html/"
assets_path   = "../../../1-foundation/uiux-spec/assets"
```

Save result as `mock_result`.
- If `mock_result.ok == true` → save `mock_html_path = mock_result.path`.
- If `mock_result.ok == false` → set `mock_html_path = null`, `mock_skip_reason = "error"`. Log the error but continue — mock is non-blocking.

### Step 15 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_screen_ver was 0]: Wrote business spec for <target_screen.name> (<target_screen.id>) (new)
  [if existing_screen_ver > 0]:   Updated business spec for <target_screen.name> — changed: <screen_changed_fields>
  [if new usecases written]:      Wrote <N> new usecase artifacts
  [if mock_html_path not null]:                          Mock preview generated
  [if mock_html_path null and mock_skip_reason == "config"]: Mock skipped (mock_generation_level = <mock_generation_level>)
  [if mock_html_path null and mock_skip_reason == "error"]:  Mock skipped (generation failed)

Artifacts written
  <module_id>.<screen_id>.2-business-spec   v<ver>  ([new / updated])
  [for each new usecase written]:
  project.2-business-spec.usecases.<id>    v1  (new)
  [if usecase_index_updated]:
  project.2-business-spec.usecase-index    v<ver>  (updated)

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  [if screens remain without 2-business-spec]: /asdlc-p2:bus-2-screen  (next screen)
  [if all screens done]:                        /asdlc-p3:tech-1-core
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Never discuss or ask about technology, architecture, or implementation — Business Analyst perspective only
- Always propose a draft first — never open with blank questions. Refine through discussion.
- One topic per turn during refinement
- At Autonomy level `autopilot`: skip Step 6 refine (and the Step 5 draft-feedback question) — accept the synthesized draft and proceed to Step 7; the draft's proposed items are already logged as derived assumptions
- At Autonomy level `careful`: never skip the Section 3 HITL gate — always wait for GO before writing. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Section 3 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass at the end of Step 8 — it is what catches `derived_assumptions` entries missed during Steps 5–8, regardless of Autonomy level
- Do not continue to Step 10 if Step 9 (screen artifact write) returns an error
- Do not continue to Step 11 if any Step 10 (usecase artifact write) returns an error
- Do not continue to Step 12 if Step 11 (usecase-index write) returns an error
- Do not continue to Step 13 if Step 12 (dep-graph-sync for screen) reports an error
- Do not continue to Step 14 if Step 13 (dep-graph-sync for usecase-index) reports an error
- Step 14 (screen-mock-agent) only runs at `mock_generation_level == "full"`; at `none` it is skipped, not attempted
- Step 14 (screen-mock-agent) failure is non-blocking — always continue to Step 15
- Do not continue to Step 15 if Step 14 was skipped due to a Step 13 error
- In update mode: carry over unchanged fields from the existing artifact — do not re-derive them
- Never drop a usecase from usecase-index — only add or update
- At Autonomy level `careful`: never confirm usecases one at a time — Step 7 derives every in-scope usecase first, then confirms all of them in a single CONFIRM ALL / REVISE block. At `autopilot`, Step 7 skips the confirmation entirely and auto-accepts the draft (§ HITL Gate vs Digest in `.claude/PATTERNS.md`).
- Never omit the ⚠ line in Step 7 when a new usecase has no derived alternative_flows — a
  missing flow is far harder to spot in a drafted block than a wrong one
