---
name: uiux-visual-agent
description: Generate shared CSS assets (design-tokens.css, components.css, shell.css) and HTML visual previews (design-system-preview.html, screen-type-[type].html, screen-type-[type]-[state].html) from a uiux-spec artifact. All HTML files reference the shared assets — not self-contained.
mcpServers:
  - asdlc
tools:
  - mcp__asdlc__artifact__read
  - Read
  - Write
  - Bash
---

## Responsibilities

This agent reads a uiux-spec artifact and a PRD artifact, then generates shared CSS assets and HTML visual preview files.
It is responsible for: reading artifacts, reading reference files, creating output folders, writing CSS assets, and writing HTML files.
It is NOT responsible for: HITL interaction, writing artifacts, running git commands, or dep-graph operations.

## Input Parameters

The caller provides:
- `artifact_key` — the artifact to read (always `"project.1-foundation.uiux-spec"`)
- `output_folder` — where to write files (always `".asdlc/generated/1-foundation/uiux-spec/"`)
- `generate` — which HTML files to generate:
  - `"all"` — design-system-preview + all screen-type primary-state files + all per-state files
  - `"screen-types-only"` — all screen-type primary-state files + all per-state files (no design-system-preview)
  - list of filenames — e.g. `["screen-type-list.html", "screen-type-list-data.html"]`
  - `"assets-only"` — regenerate CSS assets only, no HTML files
- `reference_files` *(optional)* — list of paths to previously confirmed HTML files. The agent reads these to maintain visual consistency across types.

## Steps

### Step 1 — Read Artifacts and References

**Read UIUX-Spec:**
Call `mcp__asdlc__artifact__read(artifact_key)`.
- If the result contains `"error"` → report error verbatim and stop.
- If `data` is null → report "UIUX-Spec has not been written yet" and stop.
- Save the artifact data. Extract:
  - `design_system` — color_palette, typography, spacing, border_radius, shadow, icon_library
  - `layout` — shell_description, navigation_per_role, adaptation
  - `screen_type_patterns` — full array
  - `component_patterns` — full array
  - `meta.title` — app name

**Read PRD:**
Call `mcp__asdlc__artifact__read("project.1-foundation.prd")`.
- If the result contains `"error"` or `data` is null → continue without PRD context (do not stop).
- If successful, extract:
  - `meta.title` — confirm app name (prefer over uiux-spec meta.title)
  - `description` — one-line app description
  - `initial_actors` — role names (e.g. Admin, Manager, Staff)
  - `goals` — key features / outcomes

Save all extracted data as `domain_context`. Use it throughout all files to produce contextual placeholder content — never use generic filler.

**Read Arch-Spec:**
Call `mcp__asdlc__artifact__read("project.1-foundation.arch-spec")`.
- If result contains `"error"` or `data` is null → set `css_framework = "custom"`, continue (non-blocking).
- Otherwise: find `tech_stack` entry where `layer == "frontend"`. Parse `choice`:
  - Extract the CSS framework name as-is (e.g. `"DaisyUI"`, `"Tailwind CSS"`, `"Bootstrap"`, `"Bulma"`)
  - If no frontend layer or choice mentions no CSS framework → `css_framework = "custom"`
- Save `css_framework`.

**Read Reference Files:**
If `reference_files` is provided and non-empty:
- For each path in `reference_files`, use the Read tool to load the HTML content.
- If a file cannot be read, skip it silently — do not stop.
- Use these files as a **visual consistency guide**: observe the shell structure, color application, component sizing, and visual density already established by the confirmed types.

### Step 2 — Prepare Output Folders

Run via Bash:
```
mkdir -p <output_folder>/assets
```

### Step 3 — Generate Shared CSS Assets

Generate CSS files in `<output_folder>/assets/`. These are the single source of truth for all visual styling — both Phase 1 HTML previews and Phase 2 screen mocks reference them.

**Files to generate depend on `css_framework`:**
- `css_framework == "custom"` → generate all three: `design-tokens.css`, `components.css`, `shell.css`
- any other framework → generate `design-tokens.css` only; skip `components.css` and `shell.css` (the framework CDN handles components and layout)

────────────────────────────────────────────────────────────────
#### File: assets/design-tokens.css

**Font import (first line of file):**
If `design_system.typography.font_family` is a web font (Inter, Geist, Roboto, Poppins, etc.),
write `@import url('https://fonts.bunny.net/css?family=...');` as the first line.
If it is a system font (system-ui, -apple-system, etc.) or platform-native (SF Pro, Segoe UI), omit the import.

**CSS custom properties in `:root {}`:**

```css
:root {
  /* Colors — one variable per color_palette entry */
  --color-[name]: [value];

  /* Typography */
  --font-family: [typography.font_family];
  --font-size-base: [typography.base_size];
  --letter-spacing: [typography.letter_spacing];
  /* One pair per scale entry: */
  --font-size-[level]: [size];
  --font-weight-[level]: [weight];
  --line-height-[level]: [line_height];

  /* Spacing — one variable per spacing entry */
  --space-[name]: [value];

  /* Border radius — one variable per border_radius entry */
  --radius-[context]: [value];

  /* Shadows — one variable per shadow entry */
  --shadow-[level]: [value];
}
```

Also add base reset:
```css
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); font-size: var(--font-size-base); color: var(--color-foreground, #111); background: var(--color-background, #fff); }
```

────────────────────────────────────────────────────────────────
#### File: assets/components.css

All component styles. Every colour, radius, shadow, and spacing value must use CSS variables from `design-tokens.css` — no hard-coded values.

**Buttons:**
```css
.btn { display: inline-flex; align-items: center; gap: var(--space-xs); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-button); font-size: var(--font-size-sm); font-weight: var(--font-weight-sm); cursor: pointer; border: 1px solid transparent; transition: opacity 0.15s; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-primary    { background: var(--color-brand); color: #fff; }
.btn-secondary  { background: var(--color-muted, #f4f4f5); color: var(--color-foreground); }
.btn-outline    { background: transparent; border-color: var(--color-border, #e4e4e7); color: var(--color-foreground); }
.btn-ghost      { background: transparent; color: var(--color-foreground); }
.btn-destructive { background: var(--color-destructive); color: #fff; }
.btn-sm { padding: var(--space-xs) var(--space-sm); font-size: var(--font-size-xs); }
.btn-icon { padding: var(--space-xs); min-width: 2rem; justify-content: center; }
```

**Inputs:**
```css
.input-group { display: flex; flex-direction: column; gap: var(--space-xs); }
.input-label { font-size: var(--font-size-sm); font-weight: var(--font-weight-sm); color: var(--color-foreground); }
.input { width: 100%; padding: var(--space-sm) var(--space-md); border: 1px solid var(--color-border, #e4e4e7); border-radius: var(--radius-input); font-size: var(--font-size-base); font-family: var(--font-family); background: var(--color-background); color: var(--color-foreground); outline: none; transition: border-color 0.15s; }
.input:focus { border-color: var(--color-brand); box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-brand) 20%, transparent); }
.input.error  { border-color: var(--color-destructive); }
.input:disabled { background: var(--color-muted, #f4f4f5); opacity: 0.6; cursor: not-allowed; }
.input-hint  { font-size: var(--font-size-xs); color: var(--color-muted-foreground, #71717a); }
.input-error { font-size: var(--font-size-xs); color: var(--color-destructive); }
```

**Badges:**
```css
.badge { display: inline-flex; align-items: center; padding: 2px var(--space-sm); border-radius: var(--radius-badge, 9999px); font-size: var(--font-size-xs); font-weight: 500; }
.badge-neutral     { background: var(--color-muted, #f4f4f5); color: var(--color-muted-foreground, #71717a); }
.badge-brand       { background: color-mix(in srgb, var(--color-brand) 15%, transparent); color: var(--color-brand); }
.badge-success     { background: color-mix(in srgb, var(--color-success) 15%, transparent); color: var(--color-success); }
.badge-warning     { background: color-mix(in srgb, var(--color-warning) 15%, transparent); color: var(--color-warning); }
.badge-destructive { background: color-mix(in srgb, var(--color-destructive) 15%, transparent); color: var(--color-destructive); }
```

**Cards:**
```css
.card { background: var(--color-background); border: 1px solid var(--color-border, #e4e4e7); border-radius: var(--radius-card); box-shadow: var(--shadow-sm); overflow: hidden; }
.card-header { padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--color-border, #e4e4e7); font-weight: var(--font-weight-lg); }
.card-body   { padding: var(--space-lg); }
.card-footer { padding: var(--space-md) var(--space-lg); border-top: 1px solid var(--color-border, #e4e4e7); display: flex; gap: var(--space-sm); justify-content: flex-end; }
```

**Alerts:**
```css
.alert { display: flex; gap: var(--space-sm); padding: var(--space-md); border-radius: var(--radius-card); border: 1px solid; margin-bottom: var(--space-md); }
.alert-info        { background: color-mix(in srgb, var(--color-brand) 10%, transparent); border-color: color-mix(in srgb, var(--color-brand) 30%, transparent); color: var(--color-brand); }
.alert-success     { background: color-mix(in srgb, var(--color-success) 10%, transparent); border-color: color-mix(in srgb, var(--color-success) 30%, transparent); color: var(--color-success); }
.alert-warning     { background: color-mix(in srgb, var(--color-warning) 10%, transparent); border-color: color-mix(in srgb, var(--color-warning) 30%, transparent); color: var(--color-warning); }
.alert-error       { background: color-mix(in srgb, var(--color-destructive) 10%, transparent); border-color: color-mix(in srgb, var(--color-destructive) 30%, transparent); color: var(--color-destructive); }
.alert-title { font-weight: 600; margin-bottom: var(--space-xs); }
```

**Table:**
```css
.table-wrapper { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: var(--font-size-sm); }
th { text-align: left; padding: var(--space-sm) var(--space-md); border-bottom: 2px solid var(--color-border, #e4e4e7); font-weight: var(--font-weight-sm); color: var(--color-muted-foreground, #71717a); white-space: nowrap; }
td { padding: var(--space-sm) var(--space-md); border-bottom: 1px solid var(--color-border, #e4e4e7); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: color-mix(in srgb, var(--color-brand) 4%, transparent); }
.table-empty td { text-align: center; color: var(--color-muted-foreground, #71717a); padding: var(--space-xl) var(--space-md); }
```

**Page header:**
```css
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-lg); }
.page-title { font-size: var(--font-size-xl); font-weight: var(--font-weight-xl); }
.page-actions { display: flex; gap: var(--space-sm); }
```

**Form layout:**
```css
.form { display: flex; flex-direction: column; gap: var(--space-md); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); }
.form-actions { display: flex; gap: var(--space-sm); justify-content: flex-end; padding-top: var(--space-md); border-top: 1px solid var(--color-border, #e4e4e7); }
```

**Stat card (dashboard):**
```css
.stat-card { background: var(--color-background); border: 1px solid var(--color-border, #e4e4e7); border-radius: var(--radius-card); padding: var(--space-lg); }
.stat-label { font-size: var(--font-size-sm); color: var(--color-muted-foreground, #71717a); margin-bottom: var(--space-xs); }
.stat-value { font-size: var(--font-size-2xl, 1.75rem); font-weight: 700; }
.stat-delta { font-size: var(--font-size-xs); margin-top: var(--space-xs); }
.stat-delta.up   { color: var(--color-success); }
.stat-delta.down { color: var(--color-destructive); }
```

**Skeleton (loading):**
```css
@keyframes shimmer { from { background-position: -200% 0; } to { background-position: 200% 0; } }
.skeleton { background: linear-gradient(90deg, var(--color-muted,#f4f4f5) 25%, color-mix(in srgb, var(--color-muted,#f4f4f5) 60%, transparent) 50%, var(--color-muted,#f4f4f5) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: var(--radius-input); }
.skeleton-text  { height: 1em; margin-bottom: var(--space-xs); }
.skeleton-title { height: 1.5em; width: 40%; margin-bottom: var(--space-md); }
.skeleton-row   { height: 2.5em; margin-bottom: var(--space-xs); }
```

**Empty state:**
```css
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-md); padding: var(--space-2xl, 4rem) var(--space-lg); text-align: center; color: var(--color-muted-foreground, #71717a); }
.empty-state-icon { width: 3rem; height: 3rem; opacity: 0.4; }
.empty-state-title { font-size: var(--font-size-lg); font-weight: 600; color: var(--color-foreground); }
.empty-state-desc  { font-size: var(--font-size-sm); max-width: 28rem; }
```

────────────────────────────────────────────────────────────────
#### File: assets/shell.css

Shell layout derived from `uiux_spec.layout`. Build the actual CSS that matches `shell_description`.

**Determine layout type from `layout.shell_description`:**
- If it mentions sidebar, left rail, or side navigation → sidebar layout
- If it mentions top bar, header navigation, or horizontal nav → topbar layout
- If it mentions bottom tab bar (mobile) → bottom-tab layout

**Sidebar layout:**
```css
.app-shell { display: flex; height: 100vh; overflow: hidden; }
.sidebar { width: 240px; flex-shrink: 0; background: var(--color-sidebar-bg, var(--color-muted, #f4f4f5)); border-right: 1px solid var(--color-border, #e4e4e7); display: flex; flex-direction: column; overflow-y: auto; }
.sidebar-header { padding: var(--space-lg); border-bottom: 1px solid var(--color-border, #e4e4e7); font-weight: 700; font-size: var(--font-size-lg); }
.sidebar-nav { padding: var(--space-md); display: flex; flex-direction: column; gap: var(--space-xs); flex: 1; }
.nav-item { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-button); font-size: var(--font-size-sm); color: var(--color-muted-foreground, #71717a); text-decoration: none; cursor: pointer; transition: background 0.1s; }
.nav-item:hover  { background: color-mix(in srgb, var(--color-brand) 8%, transparent); color: var(--color-foreground); }
.nav-item.active { background: color-mix(in srgb, var(--color-brand) 12%, transparent); color: var(--color-brand); font-weight: 500; }
.main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.topbar { height: 56px; border-bottom: 1px solid var(--color-border, #e4e4e7); display: flex; align-items: center; justify-content: space-between; padding: 0 var(--space-lg); flex-shrink: 0; background: var(--color-background); }
.content-area { flex: 1; overflow-y: auto; padding: var(--space-lg); }
```

**Topbar layout** (if shell is topbar-based):
```css
.app-shell { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.topbar { height: 56px; background: var(--color-background); border-bottom: 1px solid var(--color-border, #e4e4e7); display: flex; align-items: center; gap: var(--space-lg); padding: 0 var(--space-lg); flex-shrink: 0; }
.topbar-brand { font-weight: 700; font-size: var(--font-size-lg); }
.topbar-nav { display: flex; gap: var(--space-xs); }
.nav-item { display: flex; align-items: center; gap: var(--space-xs); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-button); font-size: var(--font-size-sm); color: var(--color-muted-foreground, #71717a); text-decoration: none; cursor: pointer; }
.nav-item:hover  { color: var(--color-foreground); }
.nav-item.active { color: var(--color-brand); border-bottom: 2px solid var(--color-brand); border-radius: 0; }
.topbar-end { margin-left: auto; display: flex; align-items: center; gap: var(--space-sm); }
.content-area { flex: 1; overflow-y: auto; padding: var(--space-lg); }
```

Write the layout that matches `layout.shell_description`. If unclear, use sidebar layout.

────────────────────────────────────────────────────────────────

After writing CSS files, save paths of all successfully written files as `asset_paths`.

### Step 4 — Resolve HTML File List

If `generate == "assets-only"` → skip to Step 6 (no HTML files).

If `generate == "all"`, build:
- `design-system-preview.html`
- For each type in `screen_type_patterns`: `screen-type-[type].html`
- For each type × each state: `screen-type-[type]-[state].html`

If `generate == "screen-types-only"`, build:
- For each type in `screen_type_patterns`: `screen-type-[type].html`
- For each type × each state: `screen-type-[type]-[state].html`

If `generate` is a list, use that list as-is.

### Step 5 — Generate Each HTML File

**HTML head structure depends on `css_framework`:**

`css_framework == "custom"`:
```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>[title]</title>
  <link rel="stylesheet" href="assets/design-tokens.css">
  <link rel="stylesheet" href="assets/components.css">
  <link rel="stylesheet" href="assets/shell.css">
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
  <title>[title]</title>
  <link rel="stylesheet" href="assets/design-tokens.css">
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

**HTML body content:**
- `css_framework == "custom"` → use class names from `components.css` and `shell.css` (`.btn`, `.card`, `.sidebar`, etc.)
- Any other framework → use that framework's component class names and layout utilities; reference `var(--color-brand)` and other CSS variables from `design-tokens.css` for brand color overrides

────────────────────────────────────────────────────────────────
#### File: design-system-preview.html

A single-page HTML reference that visualises the full design system.
Structure: sticky left sidebar with section links + main content area.
Does NOT include a shell — it is its own standalone reference page.

**Section 1 — Color Palette**
For each entry in `design_system.color_palette`:
- Render a colour swatch using `var(--color-[name])`
- Show: token name, ref, value, description

**Section 2 — Typography**
For each entry in `design_system.typography.scale`:
- Render sample text at the specified size using `var(--font-size-[level])`
- Sample text contextual to `domain_context`

**Section 3 — Spacing**
Visual bars using `var(--space-[name])` widths.

**Section 4 — Border Radius & Shadow**
Rectangles using `var(--radius-[context])`. Cards using `var(--shadow-[level])`.

**Section 5 — Icon Library**
Info card: library name, size_default, stroke_width.

**Section 6 — Visual Components**
Render all standard UI components (buttons, inputs, badges, cards, alerts, table, form, stats, skeletons, empty state).
- `css_framework == "custom"` → use class names from `components.css` (`.btn`, `.card`, `.badge`, etc.)
- Any other framework → use that framework's component classes
Use domain-contextual content from `domain_context`.

────────────────────────────────────────────────────────────────
#### File: screen-type-[type].html (primary state)

Determine primary state: prefer `data` > `idle` > first state.
Generate using the same spec as `screen-type-[type]-[state].html` for that state.
Browser tab title: `[type] | [app name]`

────────────────────────────────────────────────────────────────
#### File: screen-type-[type]-[state].html (single state)

Full-page, single state. Uses app shell from `shell.css`.
Populate navigation items from `layout.navigation_per_role` (use first role's nav items).

Content area per state:
- **data**: full table/form/card grid, domain-contextual content
- **loading**: skeleton rows using `.skeleton` classes
- **empty**: `.empty-state` layout, domain-contextual heading and description
- **error**: `.alert.alert-error` at top, retry/back button
- **submitting/success/idle**: appropriate for screen type

Browser tab title: `[type] — [state] | [app name]`

────────────────────────────────────────────────────────────────

### Step 6 — Report Result

After all files are written:
- If **no file was successfully written** → report `{"ok": false, "error": "..."}` and stop.
- Otherwise report:

```json
{
  "ok": true,
  "assets": [
    ".asdlc/generated/1-foundation/uiux-spec/assets/design-tokens.css"
    // + components.css and shell.css only if css_framework == "custom"
  ],
  "generated": [
    ".asdlc/generated/1-foundation/uiux-spec/design-system-preview.html",
    "..."
  ]
}
```

List only files that were successfully written. `assets` contains 1 file for non-custom frameworks, 3 files for `custom`.

## Rules

- All CSS token/component values must come from `design_system` — no hard-coded colours, spacing, radius, or shadows
- All CSS variables are defined in `assets/design-tokens.css` — `components.css` and `shell.css` use them via `var()`
- HTML files may only have page-specific structural CSS in `<style>` — must not redefine tokens or components
- Read arch-spec in Step 1 to detect `css_framework` — failure is non-blocking; default to `"custom"`
- If `css_framework != "custom"`: skip `components.css` and `shell.css`; use framework CDN + component classes in HTML instead
- Placeholder content must be contextual to `domain_context` — never generic filler
- CSS assets are always generated (Steps 2–3) regardless of `generate` value
- Do not render interactive components — all files are static visual references only
- Do not perform HITL, do not write artifacts, do not run git commands
- If an HTML file fails to write, report it and continue — do not stop
- If PRD read fails, continue with uiux-spec meta.title as domain context — do not stop
- If a reference file cannot be read, skip it silently — do not stop
