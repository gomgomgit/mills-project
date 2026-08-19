---
description: Check which ASDLC artifacts are stale and what needs to be re-run
allowed-tools:
  - mcp__asdlc__dep_graph__get_stale_nodes
  - mcp__asdlc__artifact__list
---

You are running the `asdlc-check-stale` command.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 1 — Fetch Data

Call `mcp__asdlc__artifact__list`.
- If it fails → STOP. Report: "MCP server is not running. Start it or register `.asdlc/mcp/server.py` in `.mcp.json` first."
- Save result as `artifact_index`.

Call `mcp__asdlc__dep_graph__get_stale_nodes`.
- Save result as `stale_nodes`.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Step 2 — Report

If `stale_nodes` is empty:

> ✅ **All clean.** No stale artifacts detected.

Stop here.

---

If `stale_nodes` is not empty, display:

> ⚠️ **Stale Artifacts Detected**
>
> [N] artifact(s) need to be re-run.

Group nodes into two sections: **Project-level** and **Screen-level**.

### Project-level stale nodes
(path starts with `project.`)

For each node:
> **[path]**
> Stale because: [stale_keys joined with ", "]
> Fix: run `[fix_command]`

### Screen-level stale nodes
(path matches `{module}.{screen}.{phase}`)

Group by module, then by screen. For each screen with stale phases:
> **[module] / [screen]**
> - `[phase]` — stale because: [stale_keys joined with ", "] → run `[fix_command]`

---

## Fix Command Mapping

Use this mapping to determine `fix_command` for each stale node:

| Artifact key pattern              | Fix command                              |
|-----------------------------------|------------------------------------------|
| `project.1-foundation.prd`        | `/asdlc-p1:fnd-1-prd`         |
| `project.1-foundation.arch-spec`  | `/asdlc-p1:fnd-2-arch-spec`   |
| `project.1-foundation.uiux-spec`  | `/asdlc-p1:fnd-3-uiux-spec`   |
| `project.1-foundation.test-strategy` | `/asdlc-p1:fnd-4-test-strategy` |
| `project.2-business-spec.*`       | `/asdlc-p2:bus-1-scope` |
| `project.3-tech-spec.entity-catalog` | `/asdlc-p3:tech-1-core`      |
| `project.3-tech-spec.shared-decisions` | `/asdlc-p3:tech-1-core`    |
| `project.3-tech-spec.api-index`   | `/asdlc-p3:tech-2-screen` (re-run for each affected screen) |
| `project.4-implement.*`           | `/asdlc-p4:impl-1-core`    |
| `*.2-business-spec`               | `/asdlc-p2:bus-2-screen`   |
| `*.3-tech-spec`                   | `/asdlc-p3:tech-2-screen`       |
| `*.4-implement`                   | `/asdlc-p4:impl-2-screen`  |

Match from most specific to least specific.

────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
## Rules

- Read-only — do not write or modify any artifact
- Do not attempt to fix stale artifacts — only report and suggest
- If a stale node's path does not match any known pattern, report it as-is with: "Fix: unknown — check dep-graph manually"
- Keep output concise: one line per stale node within a screen group
