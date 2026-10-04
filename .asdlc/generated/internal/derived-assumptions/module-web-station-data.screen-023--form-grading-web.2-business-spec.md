# Derived Assumptions Log — module-web-station-data.screen-023--form-grading-web.2-business-spec

## v1 — 2026-08-20 (new screen, synthesized from mobile screen-011 + screen-022's established web-CRUD pattern)

- **Business Unit selector + station auto-resolve (type=grading)** ← mirrors screen-022 (Form Weighbridge Web) exactly; user's standing instruction was "follow mobile flow" + the two explicit web divergences (editable date default, full edit capability), not a fresh design — this screen's structure is a direct transplant of screen-022's pattern onto Grading's fields.
- **WB Card No dropdown scoped to weighbridge records within the same Business Unit** — not explicitly stated by the user; inferred because Grading is mill-specific (via its own station→business_unit_id) and referencing a Weighbridge record from an unrelated mill would be nonsensical. Mobile doesn't need this scoping since its local SQLite only ever holds one device/mill's synced data.
- **Only Acknowledged By implemented (self-attestation checkbox, Mill Management only) — no Checked By at all** — not a new decision, carried over verbatim from mobile screen-011's existing business rule ("Checked By TIDAK ditampilkan pada form ini") and Detail Grading Web (screen-020)'s existing behavior. Diverges from screen-022 (Form Weighbridge Web), which has both Checked By and Acknowledged By — that divergence is inherited from the entities' own pre-existing business rules, not invented for this screen.
- **UOM and Percentage on Grading Detail rows treated as a data-integrity exception to the "no disabled web inputs" convention** ← direct precedent from screen-022's Net Weight exception; these are server-computed/snapshot values, not user-editable data, same reasoning class.
- **Business Unit immutable after create (edit mode shows it read-only)** ← mirrors screen-022's identical rule for Weighbridge; same underlying reasoning (station/mill reassignment has no UI path in this MVP).

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (form-grading.blade.php, FormGrading.php, GradingRecordService.php).
- information_displayed[0] = BU + Production Line, station dari line, select line tidak pernah nonaktif ← blade (tanpa @disabled), resolveActiveStationForActor(production_line_id). (Spec lama masih menyebut station dari Business Unit.)
- information_displayed[2], business_rules[2] = default WIB, tidak boleh melewati besok ← assertEventDateNotTooFarAhead.
- information_displayed[10], business_rules[8], [9] = UOM/Percentage teks, bukan input nonaktif ← blade fg-computed-value.
- business_rules[10] = BU/Production Line/station immutable.
- edge_cases[2] = berbasis Production Line; += tanggal > besok, id bukan UUID, WB Card/Quality Parameter tidak sah.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (Livewire/Data/FormGrading.php, form-grading.blade.php).
- information_displayed[0], business_rules[10] ← mode edit menampilkan nama BU (mill) dan Production Line sebagai teks read-only.
