---
description: Phase 1-Foundation — Generate or update UIUX Specification
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p1:fnd-3-uiux-spec` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 1 · Foundation — 3-uiux-spec                  ║
╚══════════════════════════════════════════════════════╝
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — both keys below must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.prd`
  - `project.1-foundation.arch-spec`
  If either is `"not_started"` → STOP.
  "Pre-condition not met: [key] has not been written. Run [command] first."
  (PRD → `/asdlc-p1:fnd-1-prd` / Arch-Spec → `/asdlc-p1:fnd-2-arch-spec`)

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Step 10's gate is blocking or a digest (digests at `autopilot`, blocking only at `careful`). Does **not** affect Step 16b — the visual review gate is a permanent exception, always blocking regardless of level. See `.claude/PATTERNS.md` § HITL Gate vs Digest.

Note the Mock Generation level — same file, key `mock_generation_level` (default `"none"` if the file is not found). See `CLAUDE.md` §9 for what each level means. Read in Step 12 — `none` forces `should_generate_screen_preview = false` regardless of the change-based logic there; `full` runs the full screen-type preview (Steps 13, 15, 16). This never affects `should_generate_design_system` — the design-system preview follows the existing change-based logic at every mock level.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Load Context

### Step 1 — Load Scheme

Call `mcp__asdlc__artifact__read_scheme("project.1-foundation.uiux-spec")` and save the result as `scheme`.
Use the field descriptions throughout the interview and proposal.

### Step 2 — Read Context Artifacts

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If the result contains `"error"` → STOP. Report the error verbatim.
- Save the PRD data. Use `initial_actors`, `goals`, and `constraints` as context for the interview.

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If the result contains `"error"` → STOP. Report the error verbatim.
- Save the Arch-Spec data. Use `system_type` and `tech_stack` as context for the interview.
  In particular: note the frontend tech choice (e.g. React, Flutter, Vue) and system_type (web / mobile / desktop).

Extract and save `platform_type`:
- `"web"` — `system_type` contains "Web", "SPA", or "PWA"
- `"non-web"` — anything else (Mobile, Flutter, iOS, Android, Desktop, etc.)
- default: `"web"` if unclear

### Step 3 — Load Existing UIUX-Spec

Call `mcp__asdlc__artifact__read("project.1-foundation.uiux-spec")`.
- `{"error": ...}` → report error verbatim and stop.
- `{"data": null}` → UIUX-Spec does not exist yet. Set `existing_ver = 0`. Continue to Section 2.
- `{"data": {...}}` → UIUX-Spec already exists. Save `existing_ver = data["ver"]`. Then:
  1. Display the current UIUX-Spec content clearly, grouped by the 6 sections:
     Design System / Layout / Screen Type Patterns / Component Patterns / Accessibility / Design Notes.
  2. Using `scheme` fields as a guide, ask:
     > **Which sections do you want to update?** (e.g. design_system, layout, screen_type_patterns, design_notes — or "all")
  3. For each selected section, collect the new values. For structured sections, ask one sub-field at a time.
  4. After all selected sections are collected, skip to Section 3 — Step 10 (the spec HITL gate)
     with the updated data pre-filled. Unchanged sections carry over from the existing UIUX-Spec.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Interview

**Autonomy fast-path — if Autonomy level is `autopilot`:** skip the entire interview below
(Steps 4–9). Do NOT ask any questions. Use the default design approach and derive every answer
from PRD + Arch-Spec, then jump straight to Section 3 — Step 10. Suggested derivation:

- **Q1 design_system** — use the recommended default design approach (Step 4 table), with the
  component library derived from the arch-spec frontend tech
- **Q2 navigation** — derive from `arch-spec.system_type` (e.g. sidebar + header for web apps,
  bottom tab bar for mobile)
- **Q3 screen types** — select the types implied by PRD `goals` and `initial_actors`
- **Q4 accessibility** — default to WCAG 2.1 AA unless the PRD implies stricter or looser
- **Q5 adaptation** — derive from `system_type` (desktop-first for web, mobile-first/only for mobile)

Every value derived here **is a derived assumption** — log each to `derived_assumptions` in Step 10
as `{field, value, reason: "autopilot: derived from PRD/Arch-Spec, not user-stated"}`.

**Otherwise (Autonomy level `careful`)** — run the interview below.

Ask the following questions one at a time. Wait for the user's answer before asking the next.
If the answer to a later question is already implied by a previous answer or by the loaded context, skip it and say so.

### Step 4 — Question 1: Design Defaults

Before asking about specific choices, offer a sensible default design system based on the arch-spec `tech_stack`.
Generate default values appropriate for the confirmed frontend tech (e.g. shadcn/ui for React, Material for Flutter).

> **Before I ask about specifics, here is a solid default design approach for this project:**
>
> | Aspect          | Recommended Default                                            |
> | --------------- | -------------------------------------------------------------- |
> | Color palette   | Neutral base (slate/zinc) + 1 brand accent color               |
> | Typography      | Inter or Geist — sans-serif, clean, readable                   |
> | Component style | [derive from tech_stack — e.g. shadcn/ui for React]            |
> | Spacing system  | 4px base unit (Tailwind default or equivalent)                 |
> | Border radius   | Moderate: inputs/cards smaller, modals larger                  |
> | Shadow          | Subtle: low elevation for cards, higher for dropdowns/modals   |
> | Icon library    | Lucide (or derive from tech_stack choice)                      |
>
> **Do you want to use this approach, or customise it?**
>
> A) Use all defaults — accept everything above
> B) Use most defaults — I'll specify what differs
> C) Custom — I have my own direction
> D) I already have a brand guide — I'll describe it

Record the answer. If B or C or D, ask for the specific differences before continuing to Step 5.
Derive `design_system` values from this answer and carry them forward to the proposal.

### Step 5 — Question 2: Navigation Pattern

Derive 3–4 relevant options from `arch-spec.system_type` and the application domain from the PRD.

> **How will users navigate through the application?**
>
> A) [derive from system_type — e.g. Left sidebar (fixed) + top header for web apps]
> B) [derive — e.g. Top navigation bar for simpler web apps]
> C) [derive — e.g. Bottom tab bar for mobile apps]
> D) Other — [describe]

Record the answer. Derive `layout.shell_description` and `layout.adaptation` values from this and the system_type.

### Step 6 — Question 3: Screen Types

Present the list of screen types below. Derive which are plausible from PRD `goals` and `initial_actors`.

> **Which screen types will this application use?**
> (Mark all that apply. I've pre-selected the ones that seem relevant based on the PRD.)
>
> Core types (select all that apply):
> ☐ Auth — login, register, forgot password
> ☐ Dashboard — overview, KPI cards, charts
> ☐ List — data tables with search, filter, pagination
> ☐ Form — create/edit records
> ☐ Detail — read-only view of one record
> ☐ Settings — user or system preferences
>
> Conditional types (only if relevant):
> ☐ Wizard — multi-step guided flow
> ☐ Report — analytics-heavy with charts, filters, export
>
> Pre-selected based on PRD: [list types you inferred from PRD goals and actors]
> Confirm or adjust the selection.

Record the confirmed list. This determines which entries go into `screen_type_patterns`.

### Step 7 — Question 4: Accessibility Level

> **What level of accessibility compliance is required?**
>
> A) WCAG 2.1 AA — standard compliance, meets most regulatory requirements
> B) WCAG 2.1 AAA — enhanced, stricter contrast and interaction requirements
> C) Basic — semantic structure and keyboard/gesture navigation, no formal standard required
> D) None — internal tool with no accessibility requirements

Record the answer. Derive `accessibility` field values from this.
For WCAG AA: normal_text 4.5:1, large_text 3:1, ui_components 3:1, semantic structure rules apply.
For AAA: normal_text 7:1, large_text 4.5:1, additional requirements.
For Basic/None: document accordingly.

### Step 8 — Question 5: Responsive / Adaptation Strategy

Skip this question if `arch-spec.system_type` is clearly mobile-only or desktop-only with no adaptation requirement.
Otherwise ask:

> **How does the UI adapt to different screen sizes or form factors?**
>
> A) Desktop-first — optimised for desktop, with mobile adaptation
> B) Mobile-first — optimised for mobile, with desktop enhancement
> C) Mobile-only — designed exclusively for small screens
> D) Desktop-only — no mobile support needed

Record the answer. Use it to populate `layout.adaptation.strategy` and `layout.adaptation.notes`.

### Step 9 — Schema Coverage Check

Review `scheme._tracked` (loaded in Step 1). For each tracked field, check whether the answers so far
provide sufficient input to populate it.

Coverage map for the current schema — these fields are already handled and do NOT need additional questions:
- `design_system` — covered by Q1 (defaults + customisation)
- `layout` — covered by Q2 (navigation pattern) + Q5 (adaptation strategy)
- `screen_type_patterns` — covered by Q3 (screen type selection); content derived by Claude per type
- `component_patterns` — derived by Claude from design_system choices and platform type
- `accessibility` — covered by Q4 (accessibility level); standard rules derived from level
- `design_notes` — derived by Claude from cross-cutting constraints identified during the interview;
  leave empty string if no cross-cutting decisions were made

For any tracked field in `scheme._tracked` that does NOT appear in the coverage map above,
ask the user about it — one question at a time.
If all tracked fields are covered, proceed directly to Section 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Propose & Confirm

### Step 10 — Build Proposal, then Gate or Digest

Construct the full UIUX-Spec data. Apply the logic below that matches the current mode.

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution. Even if this command is running inside a sequencer
(`fast-bootstrap`) and other commands' assumptions are still visible earlier in the
conversation, do not include them — those belong to a different artifact and are already
logged under their own file (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — if a REVISE later returns here to correct one section, do not re-run this line and
wipe out entries already logged for other, unrelated sections.

**Log to `derived_assumptions` as you go**: for each field below, ask — did the user state this
(directly, or via an interview answer), or are you determining it yourself (a default, a
platform-appropriate convention, an inference)? If the latter, append `{field, value, reason}`
to `derived_assumptions` immediately, before moving to the next field.

**Summary values for this command:** UIUX fields (`design_system`, `screen_type_patterns`,
`layout`, `accessibility`, `component_patterns`, `design_notes`) are large nested objects, so the
logged `value` must be a **concise one-line summary**, not the full object — e.g.
`design_system = neutral slate palette + Inter typography + Lucide icons` or
`screen_type_patterns = list + form types, standard states each`. The full value already lives in
the artifact JSON; the log only flags what was assumed. This keeps the digest and the bootstrap
checkpoint scannable (see `.claude/PATTERNS.md` § Derived Assumptions Log).

**New UIUX-Spec** (existing_ver == 0) — derive all fields from PRD + Arch-Spec + interview answers:

- **design_system** — populate all sub-fields from Q1 answers and defaults.
  color_palette: define tokens for Background, Surface, Border, Text Primary, Text Muted, Brand, Brand Hover,
  Destructive, Success, Warning. Each entry: {token, ref, value, description}.
  Set `ref` to the platform-appropriate format (CSS var for web, asset name for iOS, @color/ for Android).
  typography: fill scale with h1–h3, body, small, caption levels.
  spacing: define only dimensions relevant to this platform as [{name, value}] entries.
  border_radius: define per component category as [{context, value}] entries.
  shadow: define elevation levels as [{level, value}] entries.
  Derive icon_library from the component library and design approach confirmed in Q1.

- **layout** — derive `shell_description` from Q2. Build `navigation_per_role` from PRD `initial_actors`
  and typical navigation patterns for the actor roles (Admin sees all, regular users see a subset).
  Each entry: {menu_item, target, roles}. Set `target` to the appropriate format for the platform.
  Derive `adaptation` from Q5: {strategy, notes}.

- **screen_type_patterns** — for each confirmed type from Q3, generate a full entry with:
  type, description, layout, header_area, body_area, footer_area, and states.
  States per type:
  - auth: idle, submitting, error, success
  - dashboard: loading, empty, error, data
  - list: loading, empty, error, data
  - form: loading (edit mode), idle, submitting, error, success
  - detail: loading, error, data
  - settings: loading, idle, submitting, success, error
  - wizard: step-N, submitting, error, success (if applicable)
  - report: loading, empty, error, data (if applicable)
  Adapt layout and area descriptions to the confirmed platform and navigation pattern.

- **component_patterns** — derive from design_system and platform type.
  Build as [{component, notes}] entries for components with non-obvious conventions.
  Cover at minimum: the primary list/data component (table or list), the primary form pattern,
  the notification/feedback pattern, and the loading state pattern.
  Use platform-appropriate terminology (e.g. data-table for web, swipe-list for mobile).
  Leave out components that follow platform defaults with no project-specific conventions.

- **accessibility** — derive all sub-fields from Q4 level.
  color_contrast: fill normal_text, large_text, ui_components ratios per the chosen level.
  input_navigation: fill focus_indicator (for web/desktop; note OS-handled for native mobile),
  tab_or_swipe_order, dismiss, and activate — appropriate to the platform.
  structure_semantics: fill heading_hierarchy, landmark_or_regions, labels, error_association,
  live_regions — using platform-appropriate terminology per the scheme descriptions.
  text_content: fill min_font_size, icon_only_elements, images_and_media.

- **design_notes** — include any cross-cutting design decisions identified during the interview
  (e.g. color-blind policy, dark mode scope, brand guide reference, motion/animation policy).
  Leave empty string if no cross-cutting decisions were made.

**Existing UIUX-Spec update** (existing_ver > 0) — merge: take updated sections from Step 3,
carry over unchanged sections from the existing UIUX-Spec. Do NOT re-derive unchanged sections from scratch.

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished proposal once
against the interview transcript (Steps 4–8). Confirm every `derived_assumptions` entry is
genuinely not stated, and spot-check the rest. Add any missed entries now.

**If Autonomy level is `careful`:**

Display the complete proposal:

> **UIUX Spec Proposal:**
>
> **Design System:**
> — Color palette: [N tokens] — [list: Token (#value), ...]
> — Typography: [font_family], base [base_size]
> — Spacing: [N dimensions] — [list: name value, ...]
> — Radius: [N contexts] — [list: context value, ...]
> — Shadow: [N levels] — [list: level value, ...]
> — Icons: [library], [size_default]
>
> **Layout:**
> — Shell: [shell_description]
> — Navigation per role: [N menu items] across [N roles]
> — Adaptation: [strategy] — [notes summary]
>
> **Screen Type Patterns:** [N types]
> — [type 1]: [description of which screens]
> — [type 2]: [description]
> ... (full state details available on request)
>
> **Component Patterns:** [N components]
> — [component 1]: [notes summary]
> — [component 2]: [notes summary]
>
> **Accessibility:** [level]
> — Color contrast: normal text [normal_text], large text [large_text], UI [ui_components]
> — Input navigation: [focus_indicator or 'OS-handled'], activate [activate]
> — Min font size: [min_font_size]
>
> **Design Notes:** [design_notes or 'None']
>
> **GO / REVISE [section name] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section name]** → ask for corrections to that section only, update the proposal, re-display the full proposal
- **STOP** → stop here, do nothing further

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block. Continue without waiting (to Section 5, visual generation). If the user corrects
something afterward, apply the inline-correction + versioning rule from `.claude/PATTERNS.md`. This
digest is independent from the Step 16b visual gate below, which always stays blocking.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Write Artifact

### Step 11 — Write UIUX-Spec

Construct the `data` object from the proposal confirmed in Step 10.

Set `meta.title` to the value of `meta.title` from the PRD.
Set `meta.updated_at` to today's date (YYYY-MM-DD).
Set `ver` to `existing_ver + 1`.

Call:
```
mcp__asdlc__artifact__write(
  artifact_key = "project.1-foundation.uiux-spec",
  data         = <constructed data object>
)
```

If the result contains `"error"` → STOP. Report the error verbatim.

Save from the result:
- `path` — path of the written file
- `changed_fields` — list of fields that changed

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/project.1-foundation.uiux-spec.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section listing each entry, then
`Write` the file back. Skip entirely if `derived_assumptions` is empty.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Generate Visual Preview

### Step 12 — Determine Visual Generation Scope

Determine what needs to be generated:

If `platform_type == "non-web"` → set `should_generate_design_system = false`, `should_generate_screen_preview = false` → skip to Section 6.

- **New UIUX-Spec** (existing_ver == 0):
  - `should_generate_design_system = true`
  - `should_generate_screen_preview = true`

- **Update** (existing_ver > 0) — derive from `changed_fields`:
  - `design_system` or `component_patterns` in changed_fields →
    `should_generate_design_system = true`, `should_generate_screen_preview = true`
  - `layout` or `screen_type_patterns` in changed_fields →
    `should_generate_design_system = false`, `should_generate_screen_preview = true`
  - only `accessibility` or only `design_notes` in changed_fields →
    `should_generate_design_system = false`, `should_generate_screen_preview = false`

**Mock Generation level override:** if `mock_generation_level == "none"` → force
`should_generate_screen_preview = false`, no matter what the logic above computed.
`should_generate_design_system` is never affected by this override — the design-system
preview is generated at every mock level per the logic above.

If both flags are false → skip to Section 6.

Initialize `generated_files = []`.

**Structure note:** Steps 13–16 generate every preview file without stopping. All human
review happens once, at the single gate in Step 16b. Do not insert a gate between the
generation steps.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5a — Generate All Previews

Generate every preview file first, without stopping. The single review gate is Step 16b.

### Step 13 — Pick Reference Screen Type

Skip this step if `should_generate_screen_preview = false`.

**Autonomy fast-path — if Autonomy level is `autopilot`:** do NOT ask. Auto-select the most
representative / complex screen type from `screen_type_patterns` (typically Dashboard or List for
data-heavy apps, otherwise the type using the most components), save it as `reference_type`, and
continue to Step 14. The chosen type is surfaced in the Step 16b visual gate, which still runs and
stays **blocking** — the visual gate is a permanent exception at every level, including autopilot.

Read `screen_type_patterns` from the UIUX-Spec artifact (already loaded in Step 3).
Present the available types as a numbered list:

> **Select one screen type as a visual reference.**
>
> This screen type will be generated as an HTML preview to validate the overall visual
> direction of the application — shell layout, design tokens in real context, and components.
> Only one type is generated here as a proof-of-concept; other types can be generated
> separately if needed.
>
> **How to choose:** pick the most representative or complex type for this application —
> typically the most frequently accessed screen or the one that uses the most components
> (e.g. List or Dashboard for data-heavy applications).
>
> [enumerate types from `screen_type_patterns`, e.g.:]
> 1. Dashboard
> 2. List
> 3. Form
> ...
>
> Type the number of your choice.

Wait for the user's answer. Save the selected type as `reference_type`.

This is the only question in Section 5 — the agent cannot infer which screen type is most
representative. Everything after this point is generation, reviewed once in Step 16b.

### Step 14 — Generate Design System Preview

Skip this step if `should_generate_design_system = false`.

Delegate to `uiux-visual-agent` with:
```
artifact_key    = "project.1-foundation.uiux-spec"
output_folder   = ".asdlc/generated/1-foundation/uiux-spec/"
generate        = ["design-system-preview.html"]
reference_files = []
```

If the agent reports an error → STOP. Report the error verbatim.

Add the paths from the agent result to `generated_files`.
Do not stop for review here — continue directly to Step 15.

### Step 15 — Generate Reference Type: Phase 1

Skip this step if `should_generate_screen_preview = false`.

Determine `main_state` for `reference_type`:
- If `reference_type`'s `states` array contains an entry with `state == "data"` → `main_state = "data"`
- Else if it contains `state == "idle"` → `main_state = "idle"`
- Else → `main_state` = value of `state` in the first entry of the `states` array

Delegate to `uiux-visual-agent` with:
```
artifact_key    = "project.1-foundation.uiux-spec"
output_folder   = ".asdlc/generated/1-foundation/uiux-spec/"
generate        = ["screen-type-[reference_type].html"]
reference_files = []
```

If the agent reports an error → STOP. Report the error verbatim.

Add the paths from the agent result to `generated_files`.
Do not stop for review here — continue directly to Step 16.

### Step 16 — Generate Reference Type: Phase 2

Skip this step if `should_generate_screen_preview = false`.

`remaining_states` = all entries in `reference_type`'s `states` array whose `state` value ≠ `main_state`.

If `remaining_states` is empty → skip to Step 16b.

Delegate to `uiux-visual-agent` with:
```
artifact_key    = "project.1-foundation.uiux-spec"
output_folder   = ".asdlc/generated/1-foundation/uiux-spec/"
generate        = ["screen-type-[reference_type]-[state].html" for each state in remaining_states]
reference_files = [".asdlc/generated/1-foundation/uiux-spec/screen-type-[reference_type].html"]
```

The Phase 1 file is passed as `reference_files` so the remaining states stay visually
consistent with the main state. This is why Phase 2 runs after Phase 1 rather than in the
same invocation.

If the agent reports an error → STOP. Report the error verbatim.

Add the paths from the agent result to `generated_files`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5b — Visual Review Gate

### Step 16b — Visual Review Gate

Skip this step if `generated_files` is empty.

This is the only visual gate in this command. **Permanent exception (`CLAUDE.md` §8): this
gate is always blocking, at every Autonomy level including `autopilot` — it never becomes a
digest.** Display every generated file in one block:

> **Visual previews are ready. Open these files and review:**
>
> [if design-system-preview.html was generated:]
> — `.asdlc/generated/1-foundation/uiux-spec/design-system-preview.html`
>     colour palette, typography, spacing, component library
> [if screen type files were generated:]
> — `.asdlc/generated/1-foundation/uiux-spec/screen-type-[reference_type].html`
>     shell layout + primary state ([main_state])
> [for each Phase 2 state file, one line each:]
> — `.asdlc/generated/1-foundation/uiux-spec/screen-type-[reference_type]-[state].html`
>     state: [state]
>
> Does the visual direction look correct, and are the states consistent with each other?
> **GO / REVISE [file name] / STOP**

- **GO** → proceed to Section 6.
- **REVISE [file name]** → apply the matching rule below, then show this gate again.
- **STOP** → stop here. Do not commit anything.

**REVISE routing** — regenerate only what is affected:

| File named by the user | Action |
|:-----------------------|:-------|
| `design-system-preview.html` | Ask what needs to change. Update the artifact if needed (call `artifact__write` with the **same `ver`**). If `artifact__write` returns an error → STOP. Re-invoke the agent for `design-system-preview.html` only. |
| `screen-type-[reference_type].html` (primary state) | Ask what needs to change. Update the artifact if needed (same `ver`). Re-invoke the agent for the Phase 1 file, **then re-run Step 16** for the Phase 2 states — they reference the Phase 1 file and must be regenerated to stay consistent. |
| `screen-type-[reference_type]-[state].html` (a single state) | Re-invoke the agent for that one state file only, with the same `reference_files` as Step 16. Do not touch the other states. |

If the agent reports an error during any REVISE → STOP. Report the error verbatim.

When a REVISE re-runs a generation step, **replace** the affected entries in
`generated_files` rather than appending — otherwise the same path is listed twice in the
Step 18 summary.

Rewriting the artifact with the same `ver` is deliberate: the visual preview is generated
from an artifact that has already been written in Step 11, and a correction made here has
no downstream artifact yet. Bumping `ver` would mark downstream nodes stale for no reason.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 6 — Post-Write

### Step 17 — Invoke dep-graph-sync-agent

Delegate to the `dep-graph-sync-agent` agent with:

```
artifact_key   = "project.1-foundation.uiux-spec"
changed_fields = <changed_fields from Step 11>
depends_on     = ["project.1-foundation.prd", "project.1-foundation.arch-spec"]
```

Wait for the agent to confirm before continuing.

### Step 18 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_ver was 0]: Wrote UIUX Spec (new) — <meta.title from PRD>
  [if existing_ver > 0]:   Updated UIUX Spec — changed: <changed_fields from Step 11>
  [if generated_files non-empty]: Generated <N> visual preview files

Artifacts written
  project.1-foundation.uiux-spec   v<ver>  ([new / updated])

Visual previews
  [if generated_files non-empty]: list each path in generated_files, one per line, indented
  [if generated_files empty or generation was skipped]: None generated

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  /asdlc-p2:bus-1-scope
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- At Autonomy level `careful`: never skip the Step 10 HITL gate — always wait for GO before writing. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Step 10 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts. This does not affect the Step 16b visual gate below, which is always blocking.
- Never skip the audit pass before the gate/digest branch — it is what catches `derived_assumptions` entries missed during synthesis, regardless of Autonomy level
- Log UIUX `derived_assumptions` values as concise one-line summaries, never the full nested object — the full value stays in the artifact JSON (see the § Derived Assumptions Log note in Step 10)
- At Autonomy level `autopilot`: skip Section 2 entirely and auto-pick the reference screen in Step 13 — ask nothing there, derive answers from PRD/Arch-Spec, and log them as derived assumptions (see the Section 2 and Step 13 fast-paths). The Step 16b visual gate still stays blocking.
- At `careful`: never ask all interview questions at once — one per turn
- Do not generate the proposal until all questions and the schema coverage check (Step 9) are complete
- Do not continue to Step 12 if artifact__write returns an error
- Stop immediately if uiux-visual-agent reports an error at any point in Steps 14–16
- Never insert a review gate between Steps 13 and 16 — generation runs uninterrupted, and
  all visual review happens once at Step 16b
- Do not proceed past the Step 16b gate if the user answers STOP
- Do not repeat the Step 16b gate if artifact__write returns an error during REVISE
- Do not repeat the Step 16b gate if uiux-visual-agent reports an error during REVISE
- When REVISE names the primary-state file, always regenerate the Phase 2 state files too —
  they are generated against it as `reference_files` and will drift otherwise
- Do not continue to Step 18 if dep-graph-sync-agent reports an error
- Only REVISE the section the user specifies — do not regenerate the entire proposal
- Do not ask the user about information already derivable from PRD or Arch-Spec
- At `mock_generation_level == "none"`: never generate screen-type previews — Steps 13, 15, and 16 are all skipped, regardless of `changed_fields`. `design-system-preview.html` (Step 14) still generates normally.
- At `mock_generation_level == "full"`: run Steps 13–16 unchanged (current behavior)
