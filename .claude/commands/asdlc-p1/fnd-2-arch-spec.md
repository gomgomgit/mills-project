---
description: Phase 1-Foundation — Generate or update Architecture Specification
allowed-tools:
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p1:fnd-2-arch-spec` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 1 · Foundation — 2-arch-spec                  ║
╚══════════════════════════════════════════════════════╝
```

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — the following key must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.prd`
  If it is "not_started" → STOP. "Pre-condition not met: PRD has not been written. Run /asdlc-p1:fnd-1-prd first."

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Step 9's gate is blocking or a digest. See `.claude/PATTERNS.md` § HITL Gate vs Digest. This gate digests at `autopilot` — blocking only at `careful`.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Load Context

### Step 1 — Read PRD

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If the result contains `"error"` → STOP. Report the error verbatim.
- Save the PRD data. Use all fields as context for the interview and proposal — particularly fields that describe the problem domain, goals, constraints, and users.
- Do not ask the user for information already present in the PRD.

### Step 2 — Load Arch-Spec

Call `mcp__asdlc__artifact__read_scheme("project.1-foundation.arch-spec")` and save the result as `scheme`.
Use the field descriptions throughout the interview and proposal.

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- `{"error": ...}` → report error verbatim and stop.
- `{"data": null}` → Arch-Spec does not exist yet. Set `existing_ver = 0`. Continue to Section 2.
- `{"data": {...}}` → Arch-Spec already exists. Save `existing_ver = data["ver"]`. Then:
  1. Display the current Arch-Spec content clearly.
  2. Using `scheme` fields as a guide, ask:
     > **Which fields do you want to update?** (e.g. tech_stack, deployment, nfr — or "all")
  3. For each selected field, collect the new value. For structured fields (architecture_pattern, tech_stack, integrations, nfr), ask one sub-field at a time.
  4. After all selected fields are collected, skip to Section 3 — Step 9 with the updated data pre-filled. Unchanged fields carry over from the existing Arch-Spec.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Interview

**Autonomy fast-path — if Autonomy level is `autopilot`:** skip the entire interview below
(Steps 3–8). Do NOT ask any questions. Derive every answer from the PRD (loaded in Step 1) using
sensible defaults, then jump straight to Section 3 — Step 9. Suggested derivation:

- **Q1 system_type** — infer from PRD domain/goals (web app unless the PRD implies mobile or API-only)
- **Q2 tech preferences** — none stated → recommend a stack idiomatic for the system type and domain
- **Q3 integrations** — derive from PRD goals (payment, email, auth, storage, etc.); empty `[]` if none implied
- **Q4 deployment** — default to cloud managed platform unless the PRD implies otherwise
- **Q5 scale** — infer from PRD `target_users`/goals (small unless the PRD implies larger)
- **Step 8 coverage** — populate any remaining tracked fields with schema-appropriate defaults

Every value derived here **is a derived assumption** — log each to `derived_assumptions` in Step 9
as `{field, value, reason: "autopilot: derived from PRD, not user-stated"}`.

**Otherwise (Autonomy level `careful`)** — run the interview below.

Ask the following 5 questions one at a time. Wait for the user's answer before asking the next.

For each question, provide 3–4 options plus "Other — [describe]". Where options are marked [derive from PRD], generate them based on the PRD content and answers so far.
If the answer to a later question is already implied by a previous answer, skip it and say so.

### Step 3 — Question 1: Platform / System Type

> **What type of system are you building?**
>
> A) Web app (frontend + backend)
> B) Mobile app (iOS / Android / cross-platform)
> C) API-only / backend service
> D) Other — [describe]

### Step 4 — Question 2: Tech Preferences / Constraints

> **Do you have any existing tech stack preferences or constraints?**
> (e.g. team already uses Python, must use React, company mandates specific vendor)
>
> A) No preferences — recommend based on project needs
> B) [derive 2–3 contextual options from PRD domain and system type]
> C) Other — [describe]

### Step 5 — Question 3: External Integrations

> **What external services or systems will this app integrate with?**
> (e.g. payment gateway, email / SMS, maps, auth provider, analytics, storage)
>
> A) None at this stage
> B) [derive 2–3 contextual options from PRD goals and domain]
> C) Other — [describe, list all that apply]

### Step 6 — Question 4: Deployment Preference

> **How do you plan to deploy this system?**
>
> A) Cloud — managed platform (Vercel, Railway, Supabase, Render, etc.)
> B) Cloud — self-managed (AWS, GCP, Azure)
> C) On-premise / self-hosted
> D) Other — [describe]

### Step 7 — Question 5: Scale Expectation

> **What is the expected scale of this system?**
>
> A) Small — up to a few hundred concurrent users, minimal data volume
> B) Medium — hundreds to low thousands of concurrent users
> C) Large — thousands of concurrent users or significant data volume
> D) Other — [describe growth targets or specific numbers]

### Step 8 — Schema Coverage Check

Review `scheme._tracked` (loaded in Step 2). For each tracked field, check whether the 5 answers have provided sufficient input to populate it.

Coverage map for the current schema — these fields are already handled and do NOT need additional questions:
- `system_type` — covered by Q1
- `tech_stack` — covered by Q2
- `integrations` — covered by Q3
- `deployment` — covered by Q4
- `nfr` — covered by Q5
- `architecture_pattern` — always derived by Claude from Q1 + Q2 + Q5
- `architecture_notes` — always derived by Claude as a trade-off summary

For any tracked field in `scheme._tracked` that does NOT appear in the coverage map above, ask the user about it — one question at a time.
If all tracked fields are covered, proceed directly to Section 3.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Propose & Confirm

### Step 9 — Build Proposal, then Gate or Digest

Construct the full arch-spec data. Apply the logic below that matches the current mode.

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution. Even if this command is running inside a sequencer
(`fast-bootstrap`) and other commands' assumptions are still visible earlier in the
conversation, do not include them — those belong to a different artifact and are already
logged under their own file (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — if a REVISE later returns here to correct one field, do not re-run this line and
wipe out entries already logged for other, unrelated fields.

**Log to `derived_assumptions` as you go**: for each field below, ask — did the user state this
(directly, or via an interview answer), or are you determining it yourself? If the latter,
append `{field, value, reason}` to `derived_assumptions` immediately, before moving to the next
field. Do not defer this to a review pass at the end.

**New Arch-Spec** (existing_ver == 0) — derive all fields from PRD data + interview answers:

- **system_type** — concise description combining platform type and key characteristics.
  Example: "Web app (SPA + REST API)" or "Mobile app (Flutter, cross-platform)"

- **architecture_pattern** — recommend a pattern appropriate for system type, tech preferences, and scale.
  Common mappings (adapt based on full context):
  - Web app, small–medium, single team → CLEAN Architecture or MVC
  - API-only, event-heavy → Hexagonal / Ports & Adapters
  - Large scale, multi-team → Event-Driven or Microservices
  Populate `components` with layers of the chosen pattern described in this project's context.
  Only include layers that are actually used.

- **tech_stack** — list of `{layer, choice}` entries for all relevant layers.
  Use user's preferences if given; otherwise recommend based on system type, domain, and scale.
  Use only layers relevant to this project (e.g. a mobile-only app has no "frontend" layer).
  Always include granular implementation layers when a backend or database is present — do not leave these ambiguous for later phases to guess:
  - `ORM / data access` — the specific library (e.g. SQLAlchemy, TypeORM, Prisma, Django ORM, GORM)
  - `migration` — the approach (e.g. Alembic, Flyway, Prisma Migrate, Django migrations, auto-sync in dev only)
  - `test framework` — the primary test runner (e.g. pytest, Jest, JUnit, Go test)
  If the user did not specify these, propose sensible defaults for the chosen stack and surface them in Step 9's gate or digest — at `careful`, the user confirms or REVISEs before they are written; at `autopilot`, they are written immediately and flagged in the digest's ⚠ derived block, correctable inline afterward.

- **deployment** — derive from Question 4. Set `model` and `provider` concretely.

- **integrations** — build from Question 3. Set `type` as one of: `external-api`, `third-party-sdk`, `internal-service`.
  Set to empty array `[]` if the user answered "None".

- **nfr** — derive from PRD constraints + goals + scale answer.
  Cover categories relevant to this project: performance, security, scalability, availability, compliance.
  Each requirement should be specific and measurable where possible.

- **architecture_notes** — 2–3 sentences on key trade-offs or decisions. Leave empty string if nothing meaningful.

- **Any additional fields collected in Step 8** — populate from the answers gathered.

**Existing Arch-Spec update** (existing_ver > 0) — merge: take updated fields from Step 2, carry over unchanged fields from the existing Arch-Spec. Do NOT re-derive unchanged fields from scratch.

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished proposal once
against the interview transcript (Steps 3–8). Confirm every `derived_assumptions` entry is
genuinely not stated, and spot-check the rest. Add any missed entries now.

**If Autonomy level is `careful`:**

Display the complete proposal:

> **Architecture Spec Proposal:**
>
> **System Type:** [system_type]
>
> **Architecture Pattern:** [name]
> [description]
> Components:
> — [name]: [role]
> — ...
>
> **Tech Stack:**
> — [layer]: [choice]
> — ...
>
> **Deployment:** [model] — [provider]
>
> **Integrations:**
> — [name]: [purpose] ([type])
> (or: None)
>
> **Non-Functional Requirements:**
> — [category]: [requirement]
> — ...
>
> **Architecture Notes:** [architecture_notes]
>
> *(If Step 8 collected additional fields: display each one here with its value before the GO line)*
>
> **GO / REVISE [section name] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section name]** → ask for corrections to that section only, update the proposal, re-display the full proposal
- **STOP** → stop here, do nothing further

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait). After writing, display the Review Digest (§ HITL
Gate vs Digest in `.claude/PATTERNS.md`), rendering `derived_assumptions` accumulated above as the ⚠
block. Continue without waiting. If the user corrects something afterward, apply the
inline-correction + versioning rule from `.claude/PATTERNS.md`.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Write Artifact

### Step 10 — Write Arch-Spec

Construct the `data` object from the proposal confirmed in Step 9.

Set `meta.title` to the value of `meta.title` from the PRD.
Set `meta.updated_at` to today's date (YYYY-MM-DD).
Set `ver` to `existing_ver + 1`.

Call:
```
mcp__asdlc__artifact__write(
  artifact_key = "project.1-foundation.arch-spec",
  data         = <constructed data object>
)
```

If the result contains `"error"` → STOP. Report the error verbatim.

Save from the result:
- `path` — path of the written file
- `changed_fields` — list of fields that changed

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log): if
`derived_assumptions` is non-empty, `Read`
`.asdlc/generated/internal/derived-assumptions/project.1-foundation.arch-spec.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section listing each entry, then
`Write` the file back. Skip entirely if `derived_assumptions` is empty.

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 11 — Invoke dep-graph-sync-agent

Delegate to the `dep-graph-sync-agent` agent with:

```
artifact_key   = "project.1-foundation.arch-spec"
changed_fields = <changed_fields from Step 10>
depends_on     = ["project.1-foundation.prd"]
```

Wait for the agent to confirm before continuing.

### Step 12 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if existing_ver was 0]: Wrote Architecture Spec (new) — <meta.title from PRD>
  [if existing_ver > 0]:   Updated Architecture Spec — changed: <changed_fields from Step 10>

Artifacts written
  project.1-foundation.arch-spec   v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

Recommended next
  /asdlc-p1:fnd-3-uiux-spec
```

─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- At Autonomy level `careful`: never skip the Step 9 HITL gate — always wait for GO before writing. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Step 9 becomes a non-blocking digest (§ HITL Gate vs Digest in `.claude/PATTERNS.md`) — write and continue, correcting inline if the user interrupts.
- Never skip the audit pass before the gate/digest branch — it is what catches `derived_assumptions` entries missed during synthesis, regardless of Autonomy level
- Never ask about information already present in the PRD
- At Autonomy level `autopilot`: skip Section 2 entirely — ask nothing, derive answers from the PRD, and log them as derived assumptions (see Section 2 fast-path)
- At `careful`: never ask all 5 interview questions at once — one per turn
- Do not generate the proposal until all 5 questions and the schema coverage check (Step 8) are complete
- Do not continue to Step 11 if artifact__write returns an error
- Do not continue to Step 12 if dep-graph-sync-agent reports an error
- Only REVISE the section the user specifies — do not regenerate the entire proposal
