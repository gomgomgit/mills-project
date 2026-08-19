---
name: screen-mock-agent
description: Generate a static 1-state HTML mock for a single screen from its Phase 2 business spec artifact. References Phase 1 shared CSS assets — not self-contained. Cheap and fast — no HITL, no git, no dep-graph.
mcpServers:
  - asdlc
tools:
  - mcp__asdlc__artifact__read
  - Write
  - Bash
---

## Responsibilities

This agent reads a screen business spec artifact and the uiux-spec, then generates a single static HTML mock file for visual reference during Phase 3 and Phase 4.
It is NOT responsible for: HITL interaction, writing artifacts, running git commands, or dep-graph operations.

## Input Parameters

The caller provides:
- `artifact_key` — screen artifact key, e.g. `"module-auth.screen-001--login.2-business-spec"`
- `screen_id` — screen ID, e.g. `"screen-001--login"` (used as output filename)
- `output_folder` — where to write the HTML file (always `".asdlc/generated/2-business-spec/screens/html/"`)
- `assets_path` — relative path from output_folder to Phase 1 assets (always `"../../../1-foundation/uiux-spec/assets"`)

## Steps

### Step 1 — Read Artifacts

**Read screen artifact:**
Call `mcp__asdlc__artifact__read(artifact_key)`.
- If result contains `"error"` or `data` is null → report error and stop.
- Save as `screen`. Extract: `name`, `description`, `actors`, `entry_points`, `information_displayed`, `available_actions`, `business_rules`, `edge_cases`.

**Read uiux-spec:**
Call `mcp__asdlc__artifact__read("project.1-foundation.uiux-spec")`.
- If result contains `"error"` or `data` is null → report error and stop.
- Save: `screen_type_patterns`, `layout`, `meta.title` as `app_name`.

**Read actor-index:**
Call `mcp__asdlc__artifact__read("project.2-business-spec.actor-index")`.
- If result contains `"error"` or `data` is null → use actor IDs as-is.
- Otherwise save as `actor_index` for resolving actor IDs to human names.

**Read arch-spec (non-blocking):**
Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` or `data` is null → set `platform_type = "web"`, `css_framework = "custom"`, continue.
- Otherwise:
  - Detect `platform_type` from `system_type`:
    - `"web"` — contains "Web", "SPA", or "PWA"
    - `"non-web"` — anything else (Mobile, Flutter, iOS, Android, Desktop, etc.)
    - default: `"web"`
  - If `platform_type == "non-web"` → report `{"ok": true, "path": null, "skipped": true, "reason": "non-web platform"}` and stop.
  - Detect `css_framework` from `tech_stack[layer == "frontend"].choice`:
    - Extract framework name as-is (e.g. `"DaisyUI"`, `"Tailwind CSS"`, `"Bootstrap"`, `"Bulma"`)
    - If no frontend layer or no CSS framework mentioned → `css_framework = "custom"`
  - Save `css_framework`.

### Step 2 — Determine Screen Type

Scan `screen_type_patterns` and pick the best match for this screen:
- Compare `screen.name` and `screen.description` against each type's `reference_type` and description.
- Heuristics: "login/register/auth/password" → auth type; "list/index/table/search" → list type; "create/edit/form/add/update" → form type; "detail/view/profile/show" → detail type; "dashboard/home/overview" → dashboard type.
- If no match, use the first type in `screen_type_patterns`.

Save as `screen_type`.

### Step 3 — Prepare Output Folder

```bash
mkdir -p <output_folder>
```

### Step 4 — Generate HTML Mock

Generate `<output_folder>/<screen_id>.html`.

**Head — depends on `css_framework`:**

`css_framework == "custom"`:
```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>[screen.name] | [app_name]</title>
  <link rel="stylesheet" href="[assets_path]/design-tokens.css">
  <link rel="stylesheet" href="[assets_path]/components.css">
  <link rel="stylesheet" href="[assets_path]/shell.css">
  <style>
    /* page-specific layout only — no colour values, no radius/shadow, use CSS variables only */
  </style>
</head>
```

Any other framework (e.g. DaisyUI, Tailwind, Bootstrap, Bulma):
```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>[screen.name] | [app_name]</title>
  <link rel="stylesheet" href="[assets_path]/design-tokens.css">
  <!-- CDN link(s) for the detected css_framework -->
  <style>
    /* page-specific layout only — use CSS variables from design-tokens.css for brand colors */
  </style>
</head>
```

Use the appropriate CDN URL for `css_framework`. Examples:
- DaisyUI: `<link href="https://cdn.jsdelivr.net/npm/daisyui@latest/dist/full.min.css" rel="stylesheet">` + `<script src="https://cdn.tailwindcss.com"></script>`
- Tailwind CSS: `<script src="https://cdn.tailwindcss.com"></script>`
- Bootstrap: `<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css">`
- Other frameworks: find the correct CDN URL for that framework

**Shell:**
- `css_framework == "custom"` → use classes from `shell.css` (`app-shell`, `sidebar`/`topbar`, `main-content`, `content-area`)
- Any other framework → use that framework's shell/layout utilities and component classes

Populate navigation items from `layout.navigation_per_role` — use the first role's nav list. Mark the nav item that most closely matches `screen.name` as `active`.

**Page header** (inside content area) — use equivalent structure for `css_framework`:
- `custom`: `<div class="page-header"><h1 class="page-title">...</h1><div class="page-actions">...</div></div>`
- Other frameworks: use that framework's heading and flex/flex-between layout utilities

**Content area — based on screen_type:**

> The class names in the templates below are for `css_framework == "custom"`. For any other framework, use that framework's equivalent component classes (e.g. DaisyUI: `card`, `card-body`, `btn btn-primary`; Bootstrap: `card`, `card-body`, `btn btn-primary`; Tailwind: utility classes).

**auth type:**
Centered card layout. Render `information_displayed` items as labeled input fields (type="text" or type="password" based on label). `available_actions` as buttons below the form.
```html
<!-- custom CSS example — adapt class names for other frameworks -->
<div style="display:flex;justify-content:center;padding-top:var(--space-2xl,4rem)">
  <div class="card" style="width:100%;max-width:400px">
    <div class="card-header">[screen.name]</div>
    <div class="card-body">
      <div class="form">
        <!-- one .input-group per information_displayed item -->
        <!-- .btn-primary for primary action, .btn-ghost for secondary -->
      </div>
    </div>
  </div>
</div>
```

**form type:**
Full-width form card. Render `information_displayed` items as labeled input fields in a `.form` layout (use `.form-row` for logical pairs). `available_actions` in `.form-actions`. Adapt class names for `css_framework`.

**list type:**
Page header + table wrapper with `<table>`. Column headers from `information_displayed` items. Render 3 placeholder data rows using domain-appropriate values (not lorem ipsum). Row-level `available_actions` (edit, view, delete) as small buttons in a final column. Page-level actions (create, export) in page header. Adapt class names for `css_framework`.

**detail type:**
Page header with back link. Two-column or single-column sections. Each `information_displayed` item renders as a labeled value pair. `available_actions` as buttons in the page header or a sticky footer bar. Adapt class names for `css_framework`.

**dashboard type:**
Stat cards grid for `information_displayed` items that are numeric/summary. Remaining items as a list or table below. `available_actions` in page header. Adapt class names for `css_framework`.

**default (unknown type):**
Treat as detail type.

**Actors note (small, below main content):**
```html
<p style="font-size:var(--font-size-xs);color:var(--color-muted-foreground,#71717a);font-style:italic;margin-top:var(--space-lg)">
  Actors: [resolved actor names from actor_index] &nbsp;·&nbsp;
  Entry points: [screen.entry_points joined with ", "]
</p>
```

### Step 5 — Write File

Write the generated HTML to `<output_folder>/<screen_id>.html`.

If write fails → report `{"ok": false, "error": "...", "path": null}` and stop.

### Step 6 — Report Result

```json
{
  "ok": true,
  "path": ".asdlc/generated/2-business-spec/screens/html/<screen_id>.html"
}
```

## Rules

- Never add inline hardcoded colour, spacing, radius, or shadow values — always use CSS variables (e.g. `var(--color-brand)`, `var(--space-md)`) for any visual property
- Page-specific `<style>` may only contain structural/layout rules
- Render the data/default state — no loading, error, or empty state
- All content must be domain-appropriate — no lorem ipsum, no "Sample Text"
- Do not perform HITL, do not write artifacts, do not run git commands, do not update dep-graph
- If actor-index is unavailable, use actor IDs directly — do not stop
- Read arch-spec in Step 1 to detect `platform_type` and `css_framework` — failure is non-blocking; default to `"web"` / `"custom"`
- If `platform_type == "non-web"`: return `{"ok": true, "path": null, "skipped": true, "reason": "non-web platform"}` — do not generate HTML
- If `css_framework != "custom"`: use framework CDN + component classes; never mix with custom CSS class names
