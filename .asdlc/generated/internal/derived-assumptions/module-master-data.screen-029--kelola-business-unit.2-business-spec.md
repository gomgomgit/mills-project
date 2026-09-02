# Derived Assumptions Log — module-master-data.screen-029--kelola-business-unit.2-business-spec

## v1 — 2026-08-19

- actors = ["actor-admin"] only ← same reasoning as screen-027/028
- business_rules includes "Business Unit tidak dapat dihapus jika masih memiliki satu atau lebih Station terkait" ← inferred referential-integrity guard, same pattern as prior two master-data screens
- business_rules includes "Kode Business Unit harus unik di seluruh sistem (bukan hanya dalam satu Company)" ← derived from entity-catalog's constraint "code harus unik" (no scoping clause, unlike Company's explicit "dalam satu corporate") — this is a DELIBERATE contrast to screen-028: uniqueness here is on `code`, is GLOBAL not per-parent, whereas screen-028's Company uniqueness is on `name` and IS per-parent. Flagged explicitly since the two screens' uniqueness rules differ in both which field and what scope.
- edge_cases includes "Belum ada Company sama sekali" ← same FK-dependency pattern as screen-028's "Belum ada Corporate"
- test_priority = "medium" ← 4 business rules, same bucket reasoning as screen-027/028

## v4 — 2026-08-31 (asdlc-revise-screen correction)

- business_rules' 15-canonical-station breakdown changed from "3 aktif + 12 placeholder" to "7 aktif (Weighbridge, Grading, Cages Track, Threshing, Pressing, Depricarping, Kernel Plant) + 8 placeholder (Sterilizer, Clarification, Boiler, Effluent Treatment, Loading Ramp, Engine Room, Water Treatment, Bulking Storage)" ← not stated directly by the user; cross-referenced against mobile/src/services/localSchema.ts's DEFAULT_STATIONS (already updated) and entity-catalog v12 / PRD v3 (2026-08-24, promoting Threshing/Pressing/Depricarping/Kernel Plant to active MVP stations on 2026-08-23) — this screen's business spec (ver 3, written 2026-08-20) had not been refreshed after that promotion and still listed the pre-promotion 3+12 split, including now-dropped names (Thresher, Press, Digester) that don't appear in the current placeholder list at all.

## v5 — 2026-08-31 (further correction — feature relocated, not just re-numbered)

- Entire "Business Unit auto-creates 15 canonical Stations" business rule removed from description/available_actions/business_rules/edge_cases ← v4's fix (above) corrected the station breakdown numbers but missed that the whole feature no longer belongs on this screen at all: `backend/app/Services/BusinessUnitService.php`'s own docblock (dated 2026-08-20) states create() "NO LONGER auto-provisions any stations — that behavior moved to ProductionLineService::create()", confirmed by `stations.production_line_id` being a NOT NULL FK (a brand-new Business Unit has zero Production Lines, so no Station can be inserted at Business-Unit-creation time) and by an existing, currently-passing test `it('does not auto-create any stations when a new business unit is created', ...)`. The code and its tests were already correct; only this screen's spec artifacts had not caught up. User confirmed this understanding directly ("berarti dipindahkan, ketika membuat production line baru otomatis terbuat juga semua stasiun untuk production line itu") before this removal was applied.
