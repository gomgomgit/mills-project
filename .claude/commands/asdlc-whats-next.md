---
description: Read-only recommendation of the single most useful next command to run, based on artifact status, staleness, and screen progress
allowed-tools:
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__dep_graph__get_stale_nodes
---

You are running the `asdlc-whats-next` command.

You are a **Navigator**. Your only job is to look at the current state of the project and tell the user the most useful next command(s) to run. You do not write, modify, or fix anything — purely read-only reporting, same spirit as `asdlc-check-stale`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Gather Data

### Step 1 — Stale Nodes

Call `mcp__asdlc__dep_graph__get_stale_nodes`. Save result as `stale_nodes`.

### Step 2 — Screen Index (if available)

If `project.2-business-spec.screen-index` has `status: "written"` in `artifact_index`:
Call `mcp__asdlc__artifact__read("project.2-business-spec.screen-index")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Otherwise, save as `screen_index`.

Otherwise (status is `"not_started"`): set `screen_index = null`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Build Recommendations

### Step 3 — Project-Level, Not Started, Ready to Run

Scan project-level status using the same **command-level buckets** as the Fix Command Mapping table below — not raw leaf artifact keys. `project.2-business-spec.*` (actor-index, module-index, screen-index, usecase-index) and `project.4-implement.*` (scaffold, entity-models, shared-modules) are each written by a single command invocation (`1-scope` and `1-impl-core` respectively) — treat each of those groups as **one** candidate, not one per artifact. Listing them separately would produce duplicate recommendation lines that point to the same command.

Buckets, in phase order, each with its own readiness prerequisite:

- `project.1-foundation.prd` — no prerequisite
- `project.1-foundation.arch-spec` — needs `prd`
- `project.1-foundation.uiux-spec` — needs `prd`
- `project.1-foundation.test-strategy` — needs `prd`, `arch-spec`
- `project.2-business-spec.*` (actor-index, module-index, screen-index, usecase-index) — needs `prd`
- `project.3-tech-spec.entity-catalog` — needs `arch-spec`, `actor-index`, `screen-index`
- `project.3-tech-spec.shared-decisions` — needs `prd`, `arch-spec`, `entity-catalog`
- `project.4-implement.*` (scaffold, entity-models, shared-modules) — needs `arch-spec`, `shared-decisions`

A bucket counts as "not started" if **any** artifact within it is `not_started` in `artifact_index` (for single-artifact buckets, that's just its own status). Include a bucket in `not_started_candidates` only if its prerequisite is fully `written` — this keeps the list to items that are immediately actionable right now, not just "eventually needed."

Save the ordered list as `not_started_candidates` — each entry represents one fix command, never more than one entry per command.

### Step 4 — Project-Level Stale

`stale_nodes` entries are tracked at the **field level**, not the artifact level — each `path` from `dep_graph__get_stale_nodes` looks like `project.3-tech-spec.entity-catalog.entities` (phase + artifact + a specific field within it), never just `project.3-tech-spec.entity-catalog`. Do not exact-match paths against the Fix Command Mapping table; it will never hit.

For each entry in `stale_nodes` whose `path` starts with `project.`:

1. Take its first three dot-separated segments (`project.{phase}.{artifact}`) as the owning artifact key — everything after that is the field name within it.
2. Resolve that artifact key to a fix command using the Fix Command Mapping table below, matching most specific pattern first (same convention as `asdlc-check-stale`).
3. Group all stale paths that resolve to the **same fix command** into one entry — this covers both a single artifact with multiple stale fields, and multiple artifacts sharing one command (e.g. `entity-catalog` + `shared-decisions` both resolving to `/asdlc-p3:tech-1-core`).

For each group, collect two things for the "stale because" display: the distinct owning artifact names within the group (e.g. `entity-catalog`, `shared-decisions`), and the combined `stale_keys` from every grouped node — `stale_keys` are the *upstream dependency paths that changed* (e.g. `project.1-foundation.arch-spec`), not the node's own field name. This mirrors `asdlc-check-stale`'s "Stale because: [stale_keys]" convention — it explains *why* something went stale, not just *which* field is stale.

Save the deduplicated, grouped list as `stale_project_nodes` — one entry per fix command, never more.

### Step 5 — Screen-Level Gaps

If `screen_index` is not null: for each screen in `screen_index.screens`, check `artifact_index` for `{module_id}.{screen_id}.2-business-spec`, `.3-tech-spec`, `.4-implement`.

A screen has **pending work** if any of the three is `not_started`, OR if any of the three keys appears in `stale_nodes`.

Count screens with pending work → `screens_pending_count`. List their `name (id)` → `screens_pending_list` (cap at 10 entries; if more, append "... and N more").

If `screen_index` is null: set `screens_pending_count = 0` and note internally that this check was skipped (screen-index not written yet) — used only for the closing message if nothing else is pending.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — Display

Combine into one ranked list, in this priority order:

1. Project-level stale items (`stale_project_nodes`) — these block correctness of anything built on top of them.
2. Project-level not-started items ready to run (`not_started_candidates`), in phase order. For a multi-artifact bucket (`project.2-business-spec.*`, `project.4-implement.*`), the "bucket label" is the phase key plus the missing artifact names, e.g. `project.2-business-spec (actor-index, module-index, screen-index, usecase-index)` — not a single artifact_key. For single-artifact buckets, the bucket label is just the artifact_key itself.
3. Screen-level pending work summary (if `screens_pending_count > 0`).

Number entries sequentially across all three categories combined — do not restart numbering per category. Skip any category that is empty; never print a category header with zero items.

```
Rekomendasi langkah berikutnya:

[N]. [artifact name(s)] — stale (karena: [combined stale_keys joined with ", "])
     → run [fix command — see mapping table below]

[N]. [bucket label] — belum ditulis
     → run [fix command — see mapping table below]

[N]. [screens_pending_count] screen punya pekerjaan pending: [screens_pending_list]
     → run /asdlc-fast-screen
```

If all three categories are empty:

> ✅ Semua artifact up to date. Project siap `/asdlc-commit`.

If all three categories are empty **and** `screen_index` was null (screen-index itself never written): append a note — "Catatan: screen-index belum ditulis, jadi progres per-screen belum bisa dicek. Jalankan `/asdlc-p2:bus-1-scope` untuk mulai."

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Fix Command Mapping (project-level)

| Artifact key                              | Fix command                                                     |
|--------------------------------------------|-------------------------------------------------------------------|
| `project.1-foundation.prd`                 | `/asdlc-p1:fnd-1-prd`                                 |
| `project.1-foundation.arch-spec`           | `/asdlc-p1:fnd-2-arch-spec`                           |
| `project.1-foundation.uiux-spec`           | `/asdlc-p1:fnd-3-uiux-spec`                           |
| `project.1-foundation.test-strategy`       | `/asdlc-p1:fnd-4-test-strategy`                       |
| `project.2-business-spec.*`                | `/asdlc-p2:bus-1-scope`                            |
| `project.3-tech-spec.entity-catalog`       | `/asdlc-p3:tech-1-core`                                 |
| `project.3-tech-spec.shared-decisions`     | `/asdlc-p3:tech-1-core`                                 |
| `project.3-tech-spec.api-index`            | `/asdlc-p3:tech-2-screen` (re-run per affected screen)  |
| `project.4-implement.*`                    | `/asdlc-p4:impl-1-core`                            |

Match from most specific to least specific (same convention as `asdlc-check-stale`).

Screen-level items are intentionally **not** mapped to individual phase commands here — always point to `/asdlc-fast-screen`, which already detects the correct phase to resume for any given screen.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Read-only — never call `artifact__write` or `dep_graph__track_node`, never modify any artifact
- No HITL gate — this is a report, not an action; nothing here requires GO/REVISE/STOP
- Skip any recommendation category that is empty — never display a header or count of "0"
- Only list a not-started project-level item if all of its dependencies are already `written` — do not list items that are not yet runnable
- Never list more than one not-started entry for the same fix command — `project.2-business-spec.*` and `project.4-implement.*` are each a single bucket, not one entry per artifact
- Never list more than one stale entry for the same fix command either — e.g. if `entity-catalog` and `shared-decisions` are both stale at once, report a single `/asdlc-p3:tech-1-core` entry, not two
- Do not compute which of Phase 2/3/4 a screen individually needs — that logic already lives in `/asdlc-fast-screen`; only report the count and list of screens with pending work
- Cap `screens_pending_list` at 10 entries; beyond that, summarize with "... and N more"
- This command does not create or modify dep-graph nodes
- Do not continue past Step 2 if `artifact__read` on screen-index returns an error
