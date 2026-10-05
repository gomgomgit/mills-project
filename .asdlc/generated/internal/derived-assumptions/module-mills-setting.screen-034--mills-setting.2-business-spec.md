## v1 — 2026-08-19

- entry_points = "Menu sidebar 'Mills Setting'" ← not stated by user; inferred navigation path, consistent with other Admin-facing web screens. Sidebar nav config itself (uiux-spec.layout.navigation_per_role) does not yet list this item — flagged as a follow-up for the coordinator/Phase 4, not written here (out of scope for a single-screen business spec).
- Auto-create default Mills Setting row (app_name = Business Unit name, jumlah_cages = 1, images empty) on first access for a Business Unit that predates this feature ← not stated by user; inferred to avoid a hard blocking state for existing mills with no Mills Setting row yet, and to keep entity-catalog's `app_name` (required) always non-empty.
- Mill Management scoped to own Business Unit only (via `user.business_unit_id`); Admin can select any mill ← derived directly from actor-index descriptions already written by the scope-update pass (not re-stated by the user in this screen-level pass).
- Station image upload restricted to stations already registered under the selected mill (via Kelola Station) ← inferred; no station creation happens from this screen.
- test_priority = "high" ← derived: multiple actors with different permission levels (Admin: all mills; Mill Management: own mill only) per the standard derivation rubric.

## v2 — 2026-08-19

- REVISI from user (relayed by coordinator): `station.image` (file upload) replaced with `station.icon` (optional Lucide icon-name override, string) per entity-catalog v7. Business spec updated: "Unggah/Ganti Gambar Station" action → "Pilih Icon Station" (picker from available Lucide icon names); business_rules/edge_cases updated to scope file-upload validation to logo/home_page_image only, and to state the icon default-fallback behavior (Gauge/Layers/Package per station type) explicitly — this default-fallback rule already existed in uiux-spec component_patterns 'station-tile', now cross-referenced here for the settings screen's own context.
- No existing icon-picker UI convention found elsewhere in the codebase — concrete widget choice (simple dropdown of icon names vs. searchable icon select) deferred to Phase 4 implementation notes in the tech spec, not specified at business-spec level (business spec stays UI-widget-agnostic per this command's own rules).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (MillsSetting.php, mills-setting.blade.php, MillSettingService.php, RealImage.php, ValidatesUploadOnSelect.php, migrasi immediate_sync_enabled).
- description = tanpa jumlah cages, + pengiriman data langsung ← jumlah_cages dihapus 2026-08-20, immediate_sync_enabled ada (drift pra-audit, ikut dikoreksi)
- information_displayed[0] = pemilih mill searchable select; [4] = toggle immediate_sync_enabled; [5] = kolom Production Line/Nama/Tipe/icon picker searchable, urut line lalu nama ← blade + mapStations()
- available_actions[4] = Atur Pengiriman Data Langsung (menggantikan Atur Jumlah Cages); [5].description = picker searchable, langsung tersimpan ← updatedStationIcons()
- business_rules[1] = default tanpa jumlah_cages, immediate_sync false ← findOrCreateRow + migrasi default(false)
- business_rules[3] = nama aplikasi wajib (menggantikan aturan jumlah cages) ← rules() app_name required
- business_rules[6] = logo/gambar harus JPG/PNG sungguhan by content, maks 2MB, dicek saat dipilih ← RealImage + ValidatesUploadOnSelect
- business_rules += pesan validasi Indonesia ← messages()
- edge_cases[1] = file palsu bernama .png ditolak saat dipilih; [2] = nama aplikasi kosong (menggantikan jumlah cages ≤ 0)

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit c321f32), code is truth (backend/resources/views/livewire/settings/mills-setting.blade.php).
- edge_cases[5] ← Simpan nonaktif selama unggahan logo/home_page_image (wire:target save,logo,home_page_image) dan selama simpan; form & daftar station meredup saat mill diganti.
- ⚠ information_displayed[0]/available_actions[0] tidak diubah: pemilih mill kini di x-filter.bar (label 'Mill', placeholder 'Pilih mill') tetapi tanpa total/Reset — teks spec tetap benar.
