## v1 — 2026-08-19

- entry_points = "Menu sidebar 'Mills Setting'" ← not stated by user; inferred navigation path, consistent with other Admin-facing web screens. Sidebar nav config itself (uiux-spec.layout.navigation_per_role) does not yet list this item — flagged as a follow-up for the coordinator/Phase 4, not written here (out of scope for a single-screen business spec).
- Auto-create default Mills Setting row (app_name = Business Unit name, jumlah_cages = 1, images empty) on first access for a Business Unit that predates this feature ← not stated by user; inferred to avoid a hard blocking state for existing mills with no Mills Setting row yet, and to keep entity-catalog's `app_name` (required) always non-empty.
- Mill Management scoped to own Business Unit only (via `user.business_unit_id`); Admin can select any mill ← derived directly from actor-index descriptions already written by the scope-update pass (not re-stated by the user in this screen-level pass).
- Station image upload restricted to stations already registered under the selected mill (via Kelola Station) ← inferred; no station creation happens from this screen.
- test_priority = "high" ← derived: multiple actors with different permission levels (Admin: all mills; Mill Management: own mill only) per the standard derivation rubric.

## v2 — 2026-08-19

- REVISI from user (relayed by coordinator): `station.image` (file upload) replaced with `station.icon` (optional Lucide icon-name override, string) per entity-catalog v7. Business spec updated: "Unggah/Ganti Gambar Station" action → "Pilih Icon Station" (picker from available Lucide icon names); business_rules/edge_cases updated to scope file-upload validation to logo/home_page_image only, and to state the icon default-fallback behavior (Gauge/Layers/Package per station type) explicitly — this default-fallback rule already existed in uiux-spec component_patterns 'station-tile', now cross-referenced here for the settings screen's own context.
- No existing icon-picker UI convention found elsewhere in the codebase — concrete widget choice (simple dropdown of icon names vs. searchable icon select) deferred to Phase 4 implementation notes in the tech spec, not specified at business-spec level (business spec stays UI-widget-agnostic per this command's own rules).
