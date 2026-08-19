---
description: Phase 4-Implement — Generate project scaffold, entity models, and shared infrastructure modules
allowed-tools:
  - Bash
  - Read
  - Write
  - mcp__asdlc__artifact__list
  - mcp__asdlc__artifact__read
  - mcp__asdlc__artifact__read_scheme
  - mcp__asdlc__artifact__write
  - mcp__asdlc__dep_graph__get_stale_nodes
  - mcp__asdlc__dep_graph__sync_stale_status
---

You are running the `asdlc-p4:impl-1-core` command.

**Before anything else — before Pre-Flight, before any tool call — print this banner as your very first output:**

```
╔══════════════════════════════════════════════════════╗
║  Phase 4 · Implement — 1-impl-core                   ║
╚══════════════════════════════════════════════════════╝
```

You are acting as a **Lead Engineer**. Your focus is generating the shared foundation that all screen implementations depend on: project scaffold, entity model files, and shared infrastructure modules. You implement directly from the tech foundation artifacts — you do not re-derive or re-discuss decisions already made in Phase 3.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Pre-Flight

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Check pre-conditions — all three must have `status: "written"` in `artifact_index`:
  - `project.1-foundation.arch-spec`
  - `project.3-tech-spec.entity-catalog`
  - `project.3-tech-spec.shared-decisions`
  If any is `"not_started"` → STOP.
  Report: "Pre-condition not met: [key] has not been written. Run [command] first."
  (arch-spec → `/asdlc-p1:fnd-2-arch-spec`
   entity-catalog + shared-decisions → `/asdlc-p3:tech-1-core`)

Note the Autonomy level — `Read` `.asdlc/generated/internal/config.json`; use `autonomy_level` (default `"careful"` if the file is not found). See `CLAUDE.md` §8 for what each level means. Determines whether Section 3's gate is blocking or a digest (digests at `autopilot`, blocking only at `careful`). Because this command writes three artifacts across a multi-step generation section rather than one write call, the digest for this command is folded into Step 15's existing summary rather than shown as a separate box — see Step 15. See `.claude/PATTERNS.md` § HITL Gate vs Digest.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 1 — Load Context

### Step 1 — Load Schemes

Call `mcp__asdlc__artifact__read_scheme("project.4-implement.scaffold")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `scaffold_scheme`.

Call `mcp__asdlc__artifact__read_scheme("project.4-implement.entity-models")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `entity_models_scheme`.

Call `mcp__asdlc__artifact__read_scheme("project.4-implement.shared-modules")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `shared_modules_scheme`.

### Step 2 — Read Tech Foundation

Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `prd`.

Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `arch_spec`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.entity-catalog")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `entity_catalog`.

Call `mcp__asdlc__artifact__read("project.3-tech-spec.shared-decisions")`.
- If result contains `"error"` → STOP. Report error verbatim.
- Save as `shared_decisions`.

### Step 3 — Load Existing Artifacts (update mode check)

Call `mcp__asdlc__artifact__read("project.4-implement.scaffold")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `scaffold_existing_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `scaffold_existing`. Set `scaffold_existing_ver = data["ver"]`. Mode: **update**.

Call `mcp__asdlc__artifact__read("project.4-implement.entity-models")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `entity_models_existing_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `entity_models`. Set `entity_models_existing_ver = data["ver"]`. Mode: **update**.

Call `mcp__asdlc__artifact__read("project.4-implement.shared-modules")`:
- `{"error": ...}` → STOP. Report error verbatim.
- `{"data": null}` → Set `shared_modules_existing_ver = 0`. Mode: **new**.
- `{"data": {...}}` → Save as `shared_modules`. Set `shared_modules_existing_ver = data["ver"]`. Mode: **update**.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 2 — Plan

### Step 4 — Derive Generation Plan

**Initialize `derived_assumptions = []`** — a fresh, empty list scoped strictly to this
command's current execution. Even if this command is running inside a sequencer
(`fast-bootstrap`) and other commands' assumptions are still visible earlier in the
conversation, do not include them — those belong to different artifacts and are already
logged under their own files (see `.claude/PATTERNS.md` § Derived Assumptions Log). This happens
**once** — Step 5's "apply adjustments and re-present" loop must not re-run this line and
wipe out entries already logged.

Most of what follows is a mechanical translation of `arch_spec.tech_stack` (already confirmed
in Phase 1) — that doesn't count as a new assumption. Log to `derived_assumptions` only where
you're choosing something the tech stack doesn't dictate: specific default env var names,
specific setup-script command choices, specific config defaults not implied by the stack. Tag
each entry's `field` with a `scaffold.`, `entity-models.`, or `shared-modules.` prefix matching
which of the three artifacts it belongs to — Section 4 appends each artifact's own entries to
its own log file.

**For scaffold:**

Mode **new**: read `arch_spec.tech_stack` in full. Using your knowledge of the tech stack's conventions, derive:
- `framework` — the primary BE framework name (e.g. "express", "fastapi", "django", "gin")
- `package_manager` — the appropriate package manager for that stack (e.g. npm, pip, go mod, maven, composer)
- `folders_to_create` — the idiomatic folder structure for the tech stack (e.g. for Express+TS: `src/`, `src/routes/`, `src/services/`, `src/models/`, `src/middleware/`, `tests/`; for FastAPI: `app/`, `app/routes/`, `app/services/`, `app/models/`, `tests/`; derive for any other stack from its conventions)
- `files_to_generate` — list of `{ file_path, description }` for each skeleton file:
  - Package config file (package.json / requirements.txt / go.mod / pom.xml / composer.json / etc.) with a description of the dependencies to include
  - Additional config files if required by the stack (e.g. tsconfig.json, alembic.ini, application.yml)
  - Entry point file with its description
  - App factory file with its description (if the framework uses one)
  - `.gitignore`
  - `README.md` — project documentation: how to run locally and how to deploy
  - Setup script — idiomatic task runner for the stack (derive from `arch_spec.tech_stack`). Include targets or script keys for: `install` (install dependencies), `db-setup` (create/migrate DB schema in dev mode), `dev` (start dev server), `test` (run test suite)

The command — not the agent — derives this plan. The command executes the plan directly in Step 6.

Mode **update**: perform impact mapping —
1. Call `mcp__asdlc__dep_graph__get_stale_nodes`. Check if `project.4-implement.scaffold` is in the result.
2. If stale: check if `arch_spec.tech_stack` framework or key dependencies have changed → if yes, re-derive all scaffold files; if no, re-derive only affected files (e.g. package config if deps changed)
3. If not stale: inform user that scaffold is up to date. Ask if they still want to regenerate.

**For entity-models:**

Mode **new**: plan to generate one model file per entity in `entity_catalog.entities`. Derive file path from tech stack convention (e.g. `src/models/order.py`, `src/models/Order.ts`).

Mode **update**: perform impact mapping —
1. Call `mcp__asdlc__dep_graph__get_stale_nodes`. Check if `project.4-implement.entity-models` is in the result.
2. If stale: compare `entity_catalog.entities` with `entity_models.entities_implemented`:
   - Entity ID in catalog but not in `entities_implemented` → **new entity**, generate new model file
   - Entity ID in both but fields/relationships/constraints changed → **changed entity**, regenerate its model file
   - Entity ID in both, unchanged → **skip**
3. If not stale: inform user that entity-models is up to date. Ask if they still want to regenerate specific entities.

**For shared-modules:**

Mode **new**: plan to generate all BE modules derived from `shared_decisions`:
- `auth-middleware` — from `shared_decisions.auth`
- `error-handler` — from `shared_decisions.error_format`
- `db-client` — from `arch_spec.tech_stack` (ORM/DB library)
- `pagination-helper` — only if `shared_decisions.pagination.strategy` ≠ "N/A"
- `integration-[name]` — one per entry in `shared_decisions.integrations`
- Test infrastructure config file — derived from `arch_spec.tech_stack`
- `.env.example` — derived from all modules (BE + FE) that require env vars

And all applicable FE modules derived from `arch_spec.tech_stack`:
- `api-client` — HTTP client for FE → BE API calls (SPA/hybrid frameworks — derive from `arch_spec.tech_stack`; e.g. React, Vue, Angular, Next.js, Svelte)
- `auth-store` — client-side auth state management (SPA frameworks)
- `router` — client-side routing config + auth guard (SPA frameworks)
- `fe-error-handler` — client-side API error display (all FE stacks)
If the tech stack is server-rendered only (e.g. Django + Jinja2 with no SPA layer): skip api-client, auth-store, and router; generate only fe-error-handler if a JS layer exists.

Mode **update**: perform impact mapping —
1. Call `mcp__asdlc__dep_graph__get_stale_nodes`. Check if `project.4-implement.shared-modules` is in the result.
2. If stale: compare sub-sections with `shared_modules.modules_implemented` and `shared_modules.fe_modules_implemented`:
   - `shared_decisions.auth` changed → regenerate `auth-middleware` and `auth-store`
   - `shared_decisions.error_format` changed → regenerate `error-handler` and `fe-error-handler`
   - `shared_decisions.integrations` changed → regenerate affected `integration-[name]` clients
   - `shared_decisions.pagination` changed and strategy ≠ "N/A" → regenerate `pagination-helper`
   - `entity_models.files_generated` changed → regenerate `db-client` if it imports entity models
   - `fe_modules_implemented` is empty → all FE modules are new, generate all applicable
   - `arch_spec.tech_stack` FE framework changed → regenerate all FE modules
   - `project.4-implement.scaffold` changed → regenerate all modules (folder paths may have changed)
   - Always regenerate `.env.example` if any BE or FE module changed
3. If not stale: inform user that shared-modules is up to date. Ask if they still want to regenerate specific modules.

### Step 5 — Present Plan

Present the generation plan clearly, separated by artifact:

> **Implementation Plan: [prd.meta.title]**
>
> **Scaffold** ([framework] — new / up-to-date):
> Folders ([N]): [list folder paths]
> Files ([N]):
> [for each: file path — description (e.g. "package.json — npm dependencies", "src/index.ts — entry point")]
>
> **Entity Models** ([N new / N to regenerate / N unchanged]):
> [for each to generate: entity_id — file path — new / regenerate]
> [for each unchanged: entity_id — skipped]
>
> **BE Shared Modules** ([N new / N to regenerate / N unchanged]):
> [for each to generate: module name — file path — new / regenerate]
> [for each unchanged: module name — skipped]
>
> **FE Shared Modules** ([N new / N to regenerate / N unchanged]):
> [for each to generate: module name — file path — new / regenerate]
> [for each unchanged: module name — skipped]
> Test infrastructure: [framework] — [config file path]
> .env.example: [N env vars]
>
> Ready to generate?
>
> A) Looks good — proceed ✓
> B) Adjust — tell me what to change (see `.claude/PATTERNS.md` § Interview Question Style)

If user has adjustments, apply them and re-present. Otherwise proceed to HITL gate.

**Audit pass** (see `.claude/PATTERNS.md` § Derived Assumptions Log): re-read the finished plan once
against Step 4's own guidance on what counts as an assumption. Confirm every
`derived_assumptions` entry is genuinely not dictated by the tech stack, and spot-check the
rest. Add any missed entries now.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 3 — HITL Gate or Digest

**If Autonomy level is `careful`:**

Display the confirmed plan:

> **Confirmed Plan: [prd.meta.title]**
>
> **Scaffold:** [framework] — [N files]
> [list: file path — description]
>
> **Entity Models:** [N files to generate]
> [list: entity_id — path]
>
> **BE Shared Modules:** [N files to generate]
> [list: module name — path]
>
> **FE Shared Modules:** [N files to generate]
> [list: module name — path]
> Test infra: [framework] ([config path])
> .env.example: [N vars]
>
> **GO / REVISE [scaffold | entity-models | shared-modules] / STOP**

- **GO** → proceed to Section 4
- **REVISE [section]** → adjustments to that section only, return to Step 5, re-display confirmed plan
- **STOP** → stop here, do nothing

**If Autonomy level is `autopilot`:**

Proceed directly to Section 4 (no wait) using the plan as finalized in Step 5. The Review
Digest is shown once everything is generated — see Step 15.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 4 — Generate & Write

### Step 6 — Generate Scaffold Files

Generate the project scaffold directly. Use `arch_spec.tech_stack` for all content decisions.

**Create directories:**
For each path in the confirmed `folders_to_create`:
```bash
mkdir -p <path>
```
If any mkdir fails → STOP. Report the failed path.

**Generate file content and write files:**
For each `{ file_path, description }` in the confirmed `files_to_generate`, generate content appropriate for `arch_spec.tech_stack` and write it using the Write tool.

Apply these requirements regardless of tech stack:

- **Package config file** (package.json / requirements.txt / go.mod / pom.xml / etc.):
  Use `prd.meta.title` as the display name. Derive slug using the idiomatic convention for `arch_spec.tech_stack`. Include only dependencies from `arch_spec.tech_stack`. Version: `0.1.0`.

- **Entry point** (main.py / src/index.ts / cmd/main.go / etc.):
  Load `.env` at startup. Call app factory if applicable. Register `GET /health → 200 {"status": "ok"}`. Include route registration markers where screen routes will be mounted, using the idiomatic comment character for `arch_spec.tech_stack` (e.g. `//` for JS/TS/Go, `#` for Python/Ruby): `=== ASDLC_ROUTES_START ===` and `=== ASDLC_ROUTES_END ===`
  Start server on `PORT` from env (derive sensible default from `arch_spec.tech_stack`).

- **App factory** (app.ts / app/__init__.py / etc.):
  Importable without side-effects. Stub global middleware (body parsing, CORS, request logging). Return app/router instance.

- **Config files** (tsconfig.json / alembic.ini / application.yml / etc.):
  Sensible defaults for the stack. Do not hardcode DB connection strings — those go in `.env.example`.

- **.gitignore**:
  Cover build artifacts, `.env` / `.env.*` (but NOT `.env.example`), dependency dirs (`node_modules/`, `venv/`, `vendor/`), OS and IDE files.
  If `.gitignore` already exists in `existing_files`: read it and append only missing lines.

- **Setup script** (idiomatic task runner for the stack):
  Derive the idiomatic task runner from `arch_spec.tech_stack`. Generate a file with the following targets (or script keys):
  - `install` — install all dependencies using the stack-idiomatic package manager command
  - `db-setup` — run dev-mode schema creation or initial migration using the stack-idiomatic approach
  - `dev` — start the development server with hot-reload if available
  - `test` — run the full test suite
  Derive the task runner format and all commands from `arch_spec.tech_stack`.

- **README.md**:
  Generate a `README.md` at the project root with the following sections:
  - **Project name and description** — from `prd.meta.title` and a brief summary from `prd`
  - **Tech stack** — formatted list from `arch_spec.tech_stack`
  - **Prerequisites** — what to install (runtime version and package manager) and the install-deps command. Derive both from `arch_spec.tech_stack`.
  - **Running locally** — step-by-step: (1) copy `.env.example` → `.env` and fill in values, (2) install dependencies using the install target from the generated setup script, (3) set up the database schema using the db-setup target, (4) start the development server using the dev target. Derive the exact commands from `arch_spec.tech_stack` and the setup script generated in this step.
  - **Environment variables** — note: "See `.env.example` for the full list of required variables."
  - **Deploying** — based on `arch_spec.deployment.provider` and `arch_spec.deployment.model`:
    - Vercel → `vercel deploy`, note env var setup in Vercel dashboard
    - Railway → `railway up`, note env var setup in Railway dashboard
    - AWS (Cloud managed) → ECR + ECS or Lambda depending on `system_type`; general push + deploy steps
    - GCP → Cloud Run or App Engine based on `deployment.model`; general push + deploy steps
    - Self-hosted / On-premise → Docker: `docker build -t <name> .` + `docker run` with env file
    - Other providers → generic: containerize, push image, set env vars on the platform
  If `README.md` already exists in `existing_files`: overwrite it entirely.

If any file write fails → STOP. Report the failed path.

Collect results:
- `sc_framework` — framework name from plan
- `sc_package_manager` — package manager from plan
- `sc_folders_created` — list of folders created (relative paths)
- `sc_files_generated` — list of all files written (relative paths)
- `sc_entry_points` — entry point file(s) from the plan
- `sc_setup_notes` — first-run notes derived from `arch_spec.tech_stack` (e.g. special migration steps, manual env var requirements). Empty list if none.

### Step 7 — Write scaffold artifact

Set `meta.title = prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

- New (`scaffold_existing_ver == 0`) → use all data from Step 6.
- Update → use latest data from Step 6; merge `files_generated` = union of existing + new; merge `folders_created` = union of existing + new.

Set `ver = scaffold_existing_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.4-implement.scaffold",
  data         = <constructed scaffold data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `scaffold_path` and `scaffold_changed_fields` from result.

**Append to the Derived Assumptions Log** (see `.claude/PATTERNS.md` § Derived Assumptions Log):
`derived_assumptions` entries tagged `scaffold.*` — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.4-implement.scaffold.md` (treat as empty
if not found), append a `## v<ver> — <today's date>` section, then `Write` it back.

### Step 8 — Invoke dep-graph-sync-agent for scaffold

Invoke `dep-graph-sync-agent` with:

```
artifact_key   = "project.4-implement.scaffold"
changed_fields = <scaffold_changed_fields>
depends_on     = [
  "project.1-foundation.arch-spec",
  "project.3-tech-spec.shared-decisions"
]
```

Wait for confirmation. If it reports an error → STOP. Report error verbatim.

### Step 9 — Invoke entity-models-agent

Delegate to `entity-models-agent` with:

```
arch_spec            = <arch_spec>
entity_catalog       = <entity_catalog>
entities_to_generate = <list of {entity_id, file_path, mode: "new"|"regenerate"} from Step 4>
existing_files       = <entity_models.files_generated if update mode, else []>
```

Wait for the agent to complete. If it reports an error → STOP. Report error verbatim.

Save from agent result:
- `em_files_generated` — list of files generated/updated (relative paths)
- `em_entities_implemented` — full list of entity IDs now implemented (merge with existing if update)
- `em_setup_notes` — setup notes from agent

### Step 10 — Write entity-models artifact

Set `meta.title = prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

- New (`entity_models_existing_ver == 0`) → use all data from Step 9.
- Update → merge: `entities_implemented` = union of existing + new; `files_generated` = union of existing + new; carry over unchanged `setup_notes`.

Set `ver = entity_models_existing_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.4-implement.entity-models",
  data         = <constructed entity-models data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `entity_models_path` and `entity_models_changed_fields` from result.

**Append to the Derived Assumptions Log**: `derived_assumptions` entries tagged
`entity-models.*` — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.4-implement.entity-models.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it back.

### Step 11 — Invoke dep-graph-sync-agent for entity-models

Invoke `dep-graph-sync-agent` with:

```
artifact_key   = "project.4-implement.entity-models"
changed_fields = <entity_models_changed_fields>
depends_on     = [
  "project.3-tech-spec.entity-catalog",
  "project.4-implement.scaffold"
]
```

Wait for confirmation. If it reports an error → STOP. Report error verbatim.

### Step 12 — Invoke shared-modules-agent

Delegate to `shared-modules-agent` with:

```
arch_spec              = <arch_spec>
shared_decisions       = <shared_decisions>
entity_models_artifact = <entity_models artifact data (after write in Step 10)>
modules_to_generate    = <list of {module_name, file_path, mode: "new"|"regenerate"} BE modules from Step 4>
fe_modules_to_generate = <list of {module_name, file_path, mode: "new"|"regenerate"} FE modules from Step 4>
existing_files         = <shared_modules.files_generated if update mode, else []>
fe_existing_files      = <shared_modules.fe_files_generated if update mode, else []>
```

Wait for the agent to complete. If it reports an error → STOP. Report error verbatim.

Save from agent result:
- `sm_files_generated` — list of BE files generated/updated (relative paths)
- `sm_modules_implemented` — full list of BE module names now implemented
- `sm_fe_files_generated` — list of FE files generated/updated (relative paths)
- `sm_fe_modules_implemented` — full list of FE module names now implemented
- `sm_test_infrastructure` — `{ framework, config_file_path }`
- `sm_env_vars_required` — list of `{ name, description }`
- `sm_setup_notes` — setup notes from agent

### Step 13 — Write shared-modules artifact

Set `meta.title = prd.meta.title`. Set `meta.updated_at` to today's date (YYYY-MM-DD).

- New (`shared_modules_existing_ver == 0`) → use all data from Step 12.
- Update → merge: `modules_implemented` = union of existing + new; `fe_modules_implemented` = union of existing + new; `files_generated` = union of existing + new; `fe_files_generated` = union of existing + new; use latest `test_infrastructure`, `env_vars_required`, `setup_notes` from agent.

Set `ver = shared_modules_existing_ver + 1`.

```
mcp__asdlc__artifact__write(
  artifact_key = "project.4-implement.shared-modules",
  data         = <constructed shared-modules data>
)
```

If result contains `"error"` → STOP. Report error verbatim.
Save `shared_modules_path` and `shared_modules_changed_fields` from result.

**Append to the Derived Assumptions Log**: `derived_assumptions` entries tagged
`shared-modules.*` — if any, `Read`
`.asdlc/generated/internal/derived-assumptions/project.4-implement.shared-modules.md` (treat as
empty if not found), append a `## v<ver> — <today's date>` section, then `Write` it back.

### Step 14 — Invoke dep-graph-sync-agent for shared-modules

Invoke `dep-graph-sync-agent` with:

```
artifact_key   = "project.4-implement.shared-modules"
changed_fields = <shared_modules_changed_fields>
depends_on     = [
  "project.3-tech-spec.shared-decisions",
  "project.4-implement.entity-models",
  "project.4-implement.scaffold"
]
```

Wait for confirmation. If it reports an error → STOP. Report error verbatim.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Section 5 — Post-Write

### Step 15 — Sync Stale Status & Display Summary

Call `mcp__asdlc__dep_graph__sync_stale_status`.
Save result as `stale_result`.

Display:

```
What happened
  [if all *_existing_ver were 0]: Generated <sc_framework> scaffold (<N> files), <N> entity models, <N> BE modules, <N> FE modules (new)
  [if any was updated]:           Updated — <summary of what changed per artifact>

Artifacts written
  project.4-implement.scaffold       v<ver>  ([new / updated])
  project.4-implement.entity-models  v<ver>  ([new / updated])
  project.4-implement.shared-modules v<ver>  ([new / updated])

Dep-graph
  [if no stale nodes]:  All nodes clean
  [if stale nodes]:     <N> stale — [list node keys, one per line, indented]

[if Autonomy level is `autopilot` — this box is the Review Digest for this
 command, see `.claude/PATTERNS.md` § HITL Gate vs Digest:]
⚠ Derived by the agent — not stated explicitly by you
  [render the combined `derived_assumptions` list accumulated in Step 4 across all three
   artifacts; omit this block entirely if the list is empty]

Recommended next
  /asdlc-p4:impl-2-screen
```

[if Autonomy level is `autopilot`:] Anything off? Say so — I'll fix it now. If
the user corrects something, apply the inline-correction + versioning rule from `.claude/PATTERNS.md`,
per artifact (`scaffold` / `entity-models` / `shared-modules` each have their own `ver`).

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Always derive a generation plan first — never open with blank questions
- At Autonomy level `careful`: never skip the Section 3 HITL gate — always wait for GO before generating any files. Do not continue to Section 4 if the user answers STOP.
- At `autopilot`: Section 3 becomes non-blocking — proceed directly to Section 4, then show the Review Digest folded into Step 15's summary (§ HITL Gate vs Digest in `.claude/PATTERNS.md`). Correct inline if the user interrupts.
- Never skip the audit pass at the end of Step 5 — it is what catches `derived_assumptions` entries missed during Step 4, regardless of Autonomy level
- If any file write or mkdir in Step 6 fails → STOP immediately. Report the failed path.
- Do not continue to Step 8 if Step 7 (scaffold write) returns an error
- Do not continue to Step 9 if Step 8 (dep-graph-sync for scaffold) reports an error
- Do not continue to Step 10 if entity-models-agent reports an error
- Do not continue to Step 11 if Step 10 (entity-models write) returns an error
- Do not continue to Step 12 if Step 11 (dep-graph-sync for entity-models) reports an error
- Do not continue to Step 13 if shared-modules-agent reports an error
- Do not continue to Step 14 if Step 13 (shared-modules write) returns an error
- Do not continue to Step 15 if Step 14 (dep-graph-sync for shared-modules) reports an error
- In update mode: always merge `files_generated`, `fe_files_generated`, `entities_implemented`, `modules_implemented`, and `fe_modules_implemented` — never drop previously generated entries
- In update mode: skip unchanged entities/modules — do not regenerate what has not changed
- In update mode: if scaffold is unchanged (not stale and user did not request regeneration) → skip Steps 6–8 and carry over `sc_*` fields from `scaffold_existing`
- Do not re-derive or re-discuss Phase 3 decisions — implement from entity-catalog and shared-decisions as-is
- Process BE modules in dependency order: db-client before auth-middleware (auth may import db-client)
