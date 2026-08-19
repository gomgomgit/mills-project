---
description: Phase 3-Tech-Spec — Define entity catalog and shared technical decisions (project-level)
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p3:tech-1-core` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 3 · Tech Spec — 1-core                        ║
╚══════════════════════════════════════════════════════╝
```

You are acting as a **Solution Architect**. Your perspective is technical — you translate business domain knowledge into precise data models and project-wide technical decisions. You reference the architecture spec (tech stack, patterns, NFR) throughout. You do not conduct business interviews — you derive and validate technical structure.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — all three must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.arch-spec`
  - `project.2-business-spec.actor-index`
  - `project.2-business-spec.screen-index`
  If any is `"not_started"` → STOP.
  Report: "Pre-condition not met: [key] has not been written. Run [command] first."
  (arch-spec → `/asdlc-p1:fnd-2-arch-spec`
   actor-index / screen-index → `/asdlc-p2:bus-1-scope`)

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means; see `.claude/PATTERNS.md` § HITL Gate vs Digest. Three independent points in this command read it: Step 5b (fixture batch confirm — blocking at `careful`, digest only at `autopilot`), Step 6 (shared-decisions confirmations — asked at `careful`, derived from arch-spec without asking at `autopilot`), and Section 4 (main gate — blocking at `careful` only, digest at `autopilot`).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Load Context

### Step 1 — Load Schemes

Call `mcp__asdlc__artifact__read_scheme("project.3-tech-spec.entity-catalog")` — save as `entity_catalog_scheme`.
Call `mcp__asdlc__artifact__read_scheme("project.3-tech-spec.shared-decisions")` — save as `shared_decisions_scheme`.

### Step 2 — Read Foundation & Business Spec

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `prd`. Extract: `meta.title`, `goals`, `constraints`.

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `arch_spec`. Extract: `tech_stack`, `architecture_pattern`, `nfr`, `integrations`, `system_type`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.actor-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `actor_index`.

Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `screen_index`.

For each screen in `screen_index.screens`, attempt:
`mcp__asdlc__artifact__read("{screen.module_id}.{screen.id}.2-business-spec")`
Collect all non-null, non-error results as `screen_specs` (skip null and error results silently — not all screens are done).

Detect `is_api_system`:
- `true` if `arch_spec.system_type` contains any of: "Web", "SPA", "PWA", "API", "REST" (case-insensitive)
- `false` otherwise (mobile native, desktop, CLI, etc.)

### Step 3 — Load Existing Artifacts (update mode check)

Call `mcp__asdlc__artifact__read("project.3-tech-spec.entity-catalog")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `entity_catalog_existing_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `entity_catalog`. Set `entity_catalog_existing_ver = data["ver"]`. Mode: **update**.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.shared-decisions")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `shared_decisions_existing_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `shared_decisions`. Set `shared_decisions_existing_ver = data["ver"]`. Mode: **update**.

If both are in **update** mode, display current content clearly and ask:

> **Which sections would you like to update?**
>
> Entity catalog — current entities: [list entity names]
> Shared decisions — sections: auth / error_format / pagination / naming_conventions / integrations / other_decisions
>
> Enter "all" to redo everything, or name specific entities or sections.

Collect answer. Unchanged data carries over unchanged — do not re-derive or re-ask.
Initialize `derived_assumptions = []` before jumping ahead — Step 6, Section 4's audit pass,
and the gate/digest all reference it, so it must exist even when Step 4/5b are skipped.
Jump to the relevant step in Section 2. (If only shared-decisions sections are changing, skip to Step 6.)

If only **one** is in update mode (the other is new):
- If entity-catalog is new and shared-decisions exists → proceed through Sections 2 and 3 normally; for shared-decisions, present existing content and ask which sections to update.
- If entity-catalog exists and shared-decisions is new → ask which entities to update (if any) in Section 2, then gather all shared-decisions fields in Section 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Entity Catalog

### Step 4 — Synthesize Draft Entities

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution. Even if this command is running inside a sequencer
(`fast-bootstrap`) and other commands' assumptions are still visible earlier in the
conversation, do not include them — those belong to different artifacts and are already
logged under their own files (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — Step 5's refine loop and any later REVISE at Section 4 must not re-run this line
and wipe out entries already logged.

Before engaging the user, analyse all `screen_specs` to extract candidate entities:
- Scan `information_displayed`, `available_actions`, `business_rules` of each screen spec
- Group related data items into candidate domain entities
- Propose field names and types using the type system from `arch_spec.tech_stack`
- Infer relationships from business rules and cross-screen references
- Infer constraints from business rules

If `screen_specs` is empty:
> "No screen business specs are available yet — I can't derive entities from them. Please describe your domain entities directly: what are the main data objects in this system?"
Wait for the user's response, then continue from there (treat their description as the basis for the entity draft).

Otherwise, present the draft:

> **Here's my initial entity model based on the business specs:**
>
> **[EntityName]** (`[entity-id]`) — [one-line description]
> Fields: [name: type (required/optional)] ...
> Relationships: [→ EntityName (one-to-many), ...] or "none"
> Constraints: [list] or "none"
>
> [repeat for each candidate entity]
>
> Does this capture your domain? What's missing, wrong, or needs adjusting?

Log to `derived_assumptions`: relationships and constraints inferred (not stated in any screen
spec) for each entity. If the user confirms an entity without correcting an inferred item, keep
the entry; if they correct it in Step 5, remove it.

### Step 5 — Refine Entities

Present refinement and confirm/adjust questions as labeled options where the answer is enumerable (see `.claude/PATTERNS.md` § Interview Question Style); keep free-text for open inputs.

Refine one entity at a time based on the user's response. For each entity the user flags or adds:
- Name and ID (derive ID as kebab-case slug if not provided)
- Description
- Fields: name, type (use tech-stack-appropriate types), required/optional, description
- Relationships to other entities
- Constraints (uniqueness, business invariants, non-nullable rules)

After each entity is confirmed, move to the next. Do not revisit confirmed entities unless the user explicitly asks.

If the user introduces a new entity, add it and confirm before continuing.

### Step 5b — Derive & Confirm test_fixture (batched)

Derive a `test_fixture` — a concrete example data object with realistic values for all required fields — for every entity **in scope**:

- **New mode** → all entities confirmed in Step 5.
- **Update mode** → only the entities selected in Step 3. Fixtures of entities that were carried over unchanged are **not** re-derived and **not** re-confirmed, per the carry-over rule in Step 3.

Derivation rules:
- Use realistic values that match the field type and description (e.g. `"email": "test@example.com"`, `"role": "customer"`, `"amount": 50000`)
- For generated/computed fields (password hashes, tokens, auto-increment IDs, timestamps): use a realistic placeholder (e.g. `"id": "usr-001"`, `"password_hash": "$2b$10$placeholder"`, `"created_at": "2024-01-01T00:00:00Z"`)
- For foreign keys: use the fixture ID of the related entity (e.g. if User has `order_id`, use `"order_id": "ord-001"`). In update mode, if the related entity is out of scope, reuse the ID from its existing carried-over fixture — do not invent a new one.
- Respect all entity constraints (e.g. unique fields, non-nullable, enum values)

Derive the fixtures for all in-scope entities first, then confirm them in a single
exchange — not one question per entity.

**If Autonomy level is `careful`:**

> **test_fixture — [N] entities** [in update mode add: "(only the entities you selected)"]
>
> [for each in-scope entity, in catalog order:]
> **[EntityName]**
> ```json
> [derived fixture as formatted JSON]
> ```
>
> These are placeholder values used only by generated tests — they do not affect the
> schema or production data.
>
> **CONFIRM ALL / REVISE [EntityName]**

- **CONFIRM ALL** → accept every fixture shown as derived, proceed to Section 3.
- **REVISE [EntityName]** → ask what is wrong with that entity's fixture only, update it,
  re-display this block. Do not re-ask about entities the user did not name.

A wrong fixture value is cheap: it surfaces as a failing test in Phase 4 and is corrected
there. That is why these are batched rather than confirmed one at a time.

**If Autonomy level is `autopilot`:**

Skip the blocking prompt — accept every derived fixture as-is, proceed directly to Section 3.
Do **not** log `test_fixture` to `derived_assumptions` — fixtures are low-stakes placeholder
values (a wrong one surfaces as a failing test in Phase 4), so they are deliberately excluded
from the assumptions log, the digest, and the review checkpoints to keep them scannable. The
entity relationships and constraints derived in Step 4 are still logged normally.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Shared Decisions

### Step 6 — Gather Shared Decisions

**Autonomy fast-path — if Autonomy level is `autopilot`:** do NOT ask any of the confirmations
below. Derive every shared decision from `arch_spec` (auth, error format, pagination, naming
conventions, integrations, other) using the same derivation rules stated for each sub-section,
and log each derived value to `derived_assumptions` (reason: "autopilot: derived from arch-spec,
not user-stated"). Then proceed directly to Section 4. Note: this fast-path applies to Step 6
only — the entity model (Steps 4–5) keeps its approval checkpoint at every level, including
`autopilot`. **Otherwise (`careful`)** — gather each sub-section as described below.

After entities are confirmed (or if only shared-decisions is being updated), transition:

> "Entity model is set. Let me now confirm the project-wide technical decisions."

Gather each sub-section in order. Reference `arch_spec` to pre-fill where possible — do not ask for what can be derived. For each derived default the user accepts without correction below, log it to `derived_assumptions` (see `.claude/PATTERNS.md` § Derived Assumptions Log); if they correct it, don't log it — it's now their statement.

**Auth:**
Derive from `arch_spec.tech_stack` or `arch_spec.nfr`. Present your derivation and ask for confirmation only if unclear.
> "Based on the arch spec, I'd record auth as: mechanism = [X], session strategy = [Y]. Is that right?"

**Error format:**
Derive a sensible default from `arch_spec.tech_stack`. Propose and confirm.
> "I'd propose this error format: `{ \"error\": \"message\", \"code\": \"ERROR_CODE\" }`. Does that match your convention?"

**Pagination:**
If `is_api_system = false`: set `strategy = "N/A"`, `defaults = ""`, `notes = ""` and skip.
If `is_api_system = true` and list screens exist in `screen_specs`, ask:
> "For list screens, I'd suggest [offset / cursor] pagination with default page size [N]. Does that fit?"
If `is_api_system = true` and no list screens, set `strategy = "N/A"` and skip.

**Naming conventions:**
If `is_api_system = false`: skip `api_endpoints` field — set to "N/A". Still gather `db_tables` and `db_columns` conventions:
> "For DB table names: [convention]? For column names: [convention]?"
If `is_api_system = true`, derive all conventions from `arch_spec.tech_stack`. Propose and confirm.
> "For [tech stack]: API endpoints → [convention], DB tables → [convention], columns → [convention]. Correct?"

**Integrations:**
For each entry in `arch_spec.integrations`, ask:
> "For [integration name]: auth method? Key config (env var name)? Any technical notes?"
If `arch_spec.integrations` is empty, set `integrations = []` and skip.

**Other decisions:**
> "Any other project-wide decisions to record? (e.g. timezone handling, soft-delete strategy, file storage)"
If the user says none, set `other_decisions = []`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — HITL Gate or Digest

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished entity catalog
and shared decisions once against the conversation (Steps 4–6). Confirm every
`derived_assumptions` entry is genuinely not stated, and spot-check the rest. Add any missed
entries now.

**If Autonomy level is `careful`:**

Display a complete summary:

> **Tech Foundation Summary:**
>
> **Entity Catalog ([N] entities):**
> [for each entity: **Name** (`id`) — description]
>   Fields: [N fields] · Relationships: [list or "none"] · Constraints: [list or "none"]
>
> **Shared Decisions:**
> **Auth:** [mechanism] — [session_strategy]
> **Error format:** [structure]
> **Pagination:** [strategy] — [defaults]
> **Naming:** endpoints → [convention] · tables → [convention] · columns → [convention]
> **Integrations:** [for each: name — auth_method (key_config)] or "none"
> **Other:** [list] or "none"
>
> **GO / REVISE [section] / STOP**

- **GO** → proceed to Section 5
- **REVISE [section]** → corrections to that section only, return to relevant step, re-display full summary
- **STOP** → stop here, do nothing

**If Autonomy level is `autopilot`:**

Proceed directly to Section 5 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block. Continue without waiting. If the user corrects something afterward, apply the
inline-correction + versioning rule from `.claude/PATTERNS.md` per artifact (entity-catalog and
shared-decisions have independent `ver`s).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Write Artifacts

Set `meta.title` to `prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

### Step 7 — Write entity-catalog

- New (`entity_catalog_existing_ver == 0`) → use all entities from Steps 4–5b (include `test_fixture` per entity).
- Update → use updated entities; carry over unchanged entities verbatim.

Set `ver` to `entity_catalog_existing_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.3-tech-spec.entity-catalog",
  data         = <constructed entity-catalog data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `entity_catalog_path` and `entity_catalog_changed_fields` from result.

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): entries
from Step 4 (entity relationships/constraints) and Step 5b (fixtures) — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.3-tech-spec.entity-catalog.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it back.

### Step 8 — Write shared-decisions

- New (`shared_decisions_existing_ver == 0`) → use all decisions from Step 6.
- Update → use updated sections; carry over unchanged sections verbatim.

Set `ver` to `shared_decisions_existing_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.3-tech-spec.shared-decisions",
  data         = <constructed shared-decisions data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `shared_decisions_path` and `shared_decisions_changed_fields` from result.

**Append to the Derived Assumptions Log**: entries from Step 6 (auth/error-format/pagination/
naming/integrations/other decisions) — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.3-tech-spec.shared-decisions.md` (treat
as empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it back.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 6 — Post-Write

### Step 9 — Invoke dep-graph-sync-agent × 2

Invoke `dep-graph-sync-agent` twice in this order. Wait for each to confirm before continuing.

```
1. artifact_key   = "project.3-tech-spec.entity-catalog"
   changed_fields = <entity_catalog_changed_fields>
   depends_on     = [
     "project.1-foundation.arch-spec",
     "project.2-business-spec.actor-index",
     "project.2-business-spec.screen-index"
   ]

2. artifact_key   = "project.3-tech-spec.shared-decisions"
   changed_fields = <shared_decisions_changed_fields>
   depends_on     = [
     "project.1-foundation.prd",
     "project.1-foundation.arch-spec",
     "project.2-business-spec.actor-index",
     "project.2-business-spec.screen-index",
     "project.3-tech-spec.entity-catalog"
   ]
```

If either dep-graph-sync-agent call reports an error → STOP. Report error verbatim.

### Step 10 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if both *_existing_ver were 0]: Wrote entity catalog (<N> entities) + shared decisions (new)
  [if either was updated]:         Updated — changed: <summary of changed fields>

Artifacts written
  project.3-tech-spec.entity-catalog    v<ver>  ([new / updated])
  project.3-tech-spec.shared-decisions  v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  /asdlc-p3:tech-2-screen
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Always synthesize a draft first from business specs — never open with blank questions
- One entity or decision topic per turn during refinement
- At Autonomy level `autopilot`: skip the Step 6 shared-decisions confirmations — derive all of them from arch-spec and log as derived assumptions. The Step 4–5 entity/ERD approval is NOT skipped; that checkpoint stays at every level.
- At Autonomy level `careful`: never skip the Section 4 HITL gate — always wait for GO before writing. Do not continue to Section 5 if the user answers STOP.
- At `autopilot`: Section 4 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass before the gate/digest branch — it is what catches `derived_assumptions` entries missed during Steps 4–6, regardless of Autonomy level
- At `careful`: always confirm `test_fixture` before writing — never confirm fixtures one entity at a time, derive all in-scope fixtures first, then confirm them in a single CONFIRM ALL / REVISE block (Step 5b). At `autopilot`, Step 5b skips the confirmation entirely and auto-accepts the derived fixtures.
- Do not continue to Step 8 if Step 7 (entity-catalog write) returns an error
- Do not continue to Step 9 if Step 8 (shared-decisions write) returns an error
- Do not continue to Step 10 if either dep-graph-sync-agent call reports an error
- In update mode: carry over unchanged entities and decision fields verbatim — do not re-derive them
- Do not hardcode type systems — use types appropriate for the tech stack in arch-spec
- If arch-spec indicates a non-API system, still collect full entity catalog; skip API-specific fields in shared-decisions (pagination, naming conventions for endpoints) and set them to "N/A"
