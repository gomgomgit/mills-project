# Derived Assumptions Log — module-mobile-station-ops.screen-012--form-cages-track.3-tech-spec

## v2 — 2026-08-19

- Full rewrite mirroring screen-010/011 Form v4's business_logic/edge_case_handling/test structure, translated to Cages Track's checklist-grid field set ← structural mirroring per user instruction, field content per entity-catalog v3
- Time dropdown options computed as (0-23 minus hours used by ANY row) intersected with (hours > most-recently-added row's hour) ← direct technical translation of the confirmed ordering rule
- Cages Tipped header locked/disabled once ≥1 detail row exists ← still an OPEN QUESTION per business spec, not yet confirmed by user; implemented as the agent's proposed default, explicitly flagged again at the pre-implementation checkpoint
- Detail row deletion modeled as "mark for deletion, apply at next Simpan/Pause" (queue-for-deletion) ← same OPEN QUESTION status as above, mirrors Grading Detail's established pattern but not independently confirmed for Cages Track
- tippler_stop_time frozen ONLY on Simpan, explicitly NOT on Pause ← direct translation of user's "stop ketika menyimpan" instruction
- Checked By/Acknowledged By role-gating logic is a straightforward carry-over of the pre-v3 spec's existing Checked-By-Supervisor-only rule, extended to Acknowledged-By-Mill-Management-only (mirrors the same role field already present elsewhere in the entity-catalog, e.g. weighbridge-record.acknowledged_by) ← not a new design decision, just applying an existing project-wide convention

## v3 — 2026-08-19

- N (jumlah kolom checklist) sekarang dihitung dari `mill_setting.jumlah_cages` via join station→business_unit_id, dibaca sekali saat form dimuat, bukan lagi dari header `cages_tipped` ← instruksi eksplisit user
- Local reference table `mill_setting` mengikuti pola read-only `station` (stationRepo.ts) — pre-seeded/sync di luar scope screen ini, hanya dibaca ← agent generalized dari pola existing yang sama persis dipakai `station`, bukan pernyataan eksplisit user
- "Cages Tipped header dikunci setelah baris pertama dibuat" (open question v2) DIHAPUS dari implementation_notes — tidak lagi relevan karena N tidak lagi bersumber dari header tsb ← inferensi agent, konsekuensi langsung dari perubahan sumber N
- 6 unit test baru/diubah untuk mencerminkan sumber N yang baru (mill_setting, bukan cages_tipped header), termasuk test eksplisit bahwa nilai header Cages Tipped TIDAK memengaruhi jumlah kolom ← turunan langsung dari perubahan bisnis, derivasi manual (bukan test-spec-writer-agent, fork tidak bisa spawn subagent)
- 1 test_scenario baru "Jumlah Kolom Grid Mengikuti Mills Setting, Bukan Cages Tipped Header" ditambahkan; 12 skenario lain dipertahankan verbatim dari v2 karena tidak terdampak ← sama, derivasi manual
