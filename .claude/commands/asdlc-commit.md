---
description: Stage all changes, auto-generate an intent-revealing commit message from the diff, confirm with user, commit to git, display summary
allowed-tools:
  - Bash
  - mcp__asdlc__dep_graph__get_stale_nodes
---

You are running the `asdlc-commit` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  asdlc-commit                                        ║
╚══════════════════════════════════════════════════════╝
```

You are a **Release Scribe**. Your only job is to commit whatever has been written since the last commit — artifacts, source files, or both — and display a clear summary of what was committed. You do not know or advise on workflow order.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 1 — Stage All Changes

```bash
git add .
```

If this command fails → STOP. Report error verbatim.

Then inspect what is staged:

```bash
git diff --cached --name-only
```

If output is empty → STOP. Display: "Nothing to commit."

Save the list of paths as `staged_files`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 2 — Parse Staged Files

Separate `staged_files` into three groups:

- `artifact_files` — paths under `.asdlc/generated/` but NOT under `.asdlc/generated/internal/`
- `depgraph_files` — paths under `.asdlc/generated/internal/dep-graph/`
- `source_files` — all other paths

For each file in `artifact_files`:
- Read file content via Bash: `cat <path>`
- Extract: `ver`, `meta.title` (if present), `id` (if present — for screen artifacts)
- Derive a short label from the filename without extension (e.g. `entity-catalog`, `shared-decisions`, `screen-001--login`)
- Determine mode: `ver == 1` → **new**, else → **updated**

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 3 — Generate Commit Message

Auto-generate a commit message that reflects the **intent** of the change, read from the actual
diff — not just file names and versions. For a declarative artifact, the diff *is* the intent
(e.g. `tech_stack.database "SQLite" → "PostgreSQL"` means "switch database to PostgreSQL").

### 3a — Read the diff of each changed artifact

For each file in `artifact_files`, run:

```bash
git diff --cached -- <path>
```

- **New artifact** (`ver == 1`, or the diff shows the file newly added) → the change is a creation.
- **Modified artifact** → scan the diff for the changed fields and their **old → new** values (the
  JSON diff shows `- "field": <old>` / `+ "field": <new>`). Capture the few most significant field
  changes — those are what convey intent.

### 3b — Write the headline (imperative, intent-revealing)

Derive `scope`: the project-name slug from `meta.title` for project-level artifacts, or the screen
name/`id` for screen artifacts. Format the headline as `feat(<scope>): <intent phrase>` (omit
`(<scope>)` if there is no single clear scope).

Compose the intent phrase from the diff:

- **New project-level artifact** → describe its purpose, e.g. `define product requirements`,
  `add architecture spec (<framework>/<db>)`, `add project scope (actors, modules, screens)`,
  `add tech foundation (entity model + shared decisions)`,
  `add project scaffold + entity models + shared modules`.
- **New screen artifact** → e.g. `add business spec — <screen name>`, `add tech spec — <screen name>`,
  `implement <screen name> (BE + FE + tests)`.
- **Modified artifact** → describe the concrete field change from the diff, in the imperative, e.g.
  `switch database to PostgreSQL (+ TypeORM)`, `update login business rules`,
  `add Group↔Task relationship`. Always prefer the real old→new change over a generic "update X".
- Prefix `feat:` for artifact creates/changes; `chore:` for source-only (see 3d).

Keep the headline under ~72 characters.

### 3c — Write the body

After a blank line, list the concrete details, one per line:

- Each artifact: `- <label> v<ver> (new)`, or for an update `- <label> v<ver> (updated:
  <the key changed fields, with old→new where it clarifies intent>)`.
- If `source_files` is present: `- <N> source files` (list up to ~5 paths when there are few).

### 3d — Source-only commits

If there are no `artifact_files` (only `source_files`): headline `chore: <short description derived
from the file paths>` (e.g. `chore: update fast-bootstrap + fast-screen banners`); body lists the files.

Save the full message (headline + blank line + body) as `proposed_commit_message`. The blocking
gate below (Step 4) lets the user **EDIT** — use EDIT to add the *why* (business rationale) when the
diff alone does not convey it; the auto-generated headline already conveys *what* changed.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 4 — Confirmation Gate

Display:

```
Staged changes:

Artifacts ([N]):
  [label]   v[ver]  ([new / updated])
  ...

Source files ([N]):
  [list paths — up to 10; if more: "... and N more"]

Dep-graph files: [included / none]

Proposed commit message:
  "[proposed_commit_message]"

GO / EDIT / STOP
```

- **GO** → proceed to Step 5
- **EDIT** → user provides a replacement commit message → update `proposed_commit_message` → re-display the staged summary with the new message → GO / STOP
- **STOP** → unstage all changes:
  ```bash
  git restore --staged .
  ```
  Display: "Commit cancelled. Changes unstaged." → stop here

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 5 — Commit

Commit with the full message (headline + body). Since the message is multi-line, pass one `-m` per
paragraph — git renders them as headline, blank line, then body:

```bash
git commit -m "<headline>" -m "<body>"
```

(If there is no body, a single `-m "<headline>"` is fine.)

If commit fails → STOP. Report error verbatim.

Get commit hash:

```bash
git rev-parse --short HEAD
```

Save as `commit_hash`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 6 — Get Dep-Graph Status

Call `mcp__asdlc__dep_graph__get_stale_nodes`.
Save result as `stale_nodes`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 7 — Display Summary

```
Commit: [commit_hash] "[headline]"

Artifacts ([N]):
  [label]   v[ver]  ([new / updated])

Source files ([N]):
  [list paths — up to 10; if more: "... and N more"]

Dep-graph
  [if stale_nodes empty]:     All nodes clean
  [if stale_nodes non-empty]: [N] stale — [list node keys, one per line, indented]
```

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- If `git add .` fails → STOP. Report error verbatim.
- If nothing is staged after `git add .` → STOP. "Nothing to commit."
- If `git commit` fails → STOP. Report error verbatim.
- If user answers STOP at the confirmation gate → unstage with `git restore --staged .` before stopping
- EDIT only changes the commit message — never changes which files are staged
- Do not advise on next command — that is not this command's responsibility
