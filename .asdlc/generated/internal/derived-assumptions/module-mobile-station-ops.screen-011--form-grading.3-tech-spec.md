# Derived Assumptions Log — module-mobile-station-ops.screen-011--form-grading.3-tech-spec

## v2 — 2026-08-18

- Full rewrite mirroring screen-010--form-weighbridge v4's business_logic/edge_case_handling/test structure, translated to Grading's mock-driven field set ← structural mirroring per user instruction, field content per entity-catalog v2
- WB Card No dropdown query: SELECT all local weighbridge_record rows (any status), ORDER BY arrival_datetime DESC ← no explicit scope instruction from user; documented explicitly as an assumption, not a confirmed decision, pending checkpoint review
- Grading Detail row deletion modeled as "mark for deletion, apply at next Simpan/Pause" rather than immediate DB delete on click ← consistent with the form's general "nothing persists until Simpan/Pause" pattern used elsewhere (e.g. Weighbridge's dirty-state Back confirmation), not stated explicitly by user but a natural extension of that pattern
- Percentage/UOM explicitly documented as read-only/computed fields, never directly user-editable ← direct translation of business_rules, not a new assumption but called out for implementer clarity

## v3 — 2026-08-19

- Quality Parameter dropdown exclusion implemented as a per-row reactive computed over ALL detailRows (not just the row's own state) ← technical translation of the new business rule; the row itself always keeps its own current selection visible in its own dropdown (so it doesn't visually vanish), only OTHER rows exclude it
- No change to grading_detail's persisted shape — this is purely a UI-selection constraint (which options are offered), not a data-model or validation-on-save change; two rows with the same grading_parameter_id are prevented at selection time, not re-validated at Simpan

## v4 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- Dikonfirmasi dari kode: form ini tidak mengimpor syncAfterSave/apiClient — stasiun ini tidak punya write-through (pushSavedRecordNow hanya mencakup 15 stasiun seragam).
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.
- Menambahkan kaskade Weighbridge→Grading pada edge case (temuan dari syncService.ts).

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (FormGradingView.vue, gradingRecordRepo.ts, gradingParameterSync.ts, syncService.ts, localSchema.ts, stores/auth.ts, GradingParameterController.php, GradingParameterService.php, routes/api.php, GradingRecordService.php, tests/Feature/Api/MobileReadEndpointsTest.php).
- api_contracts[0].endpoints += GET /api/grading-parameters (auth:web,sanctum; role admin/supervisor/mill_management/operator; {data:[{id,name,uom,sort_order}]}; global, urut sort_order lalu name; 401 UNAUTHENTICATED / 403 FORBIDDEN) ← routes/api.php blok "ENDPOINT BACA MOBILE", GradingParameterService::listForMobile. (⚠ 403 FORBIDDEN disimpulkan dari middleware role, tidak diuji eksplisit.)
- business_logic[1] = date auto-set jam LOKAL ← FormGradingView.nowLocalDateTimeString (sudah ada sebelumnya; spec lama hanya "waktu saat ini").
- business_logic[2] = WB options setelah draft, filter saved/synced + wb_card_number, includeId, urut record_datetime ← gradingRecordRepo.getWeighbridgeRecordOptions, onMounted.
- business_logic[7] = label/id Indonesia (field-grading-no, field-license-plate-no, field-vehicle-code, field-note) ← diff FormGradingView.vue.
- business_logic += 18 sinkron master parameter (login + sebelum sinkron Grading, peta nama, hapus baris buatan, seed guard) ← gradingParameterSync.fetchAndCacheGradingParameters, auth.ts:138, syncService:784, localSchema.seedGradingParametersIfNeeded.
- business_logic += 19 pushGradingRow (WB belum tersinkron / id tak terpetakan → localFailure + sync_error; line per record; date lokal) ← syncService.pushGradingRow.
- data_operations[2].description = filter WB saved/synced ← repo query.
- edge_case_handling[2] = dropdown kosong bila tak ada WB saved/synced ber-kartu ← repo query.
- edge_case_handling[10].handling += 422 tanggal > besok, sync_error/SyncFailureHint ← GradingRecordService assertEventDateNotTooFarAhead('date'), syncService.failure.
- edge_case_handling += pilihan WB lama tetap tampil; master parameter belum tersinkron → ditolak di perangkat ← includeId, resolveServerGradingParameterIds.
- business_rules_applied[0] diperbarui (filter WB); += id parameter buatan tak pernah dikirim ← syncService/gradingParameterSync.
- unit_test_cases[0].expect = jam lokal; [2] = filter WB saved/synced + includeId; += 3 kasus (peta id buatan, tolak di perangkat, seed guard) ← syncService.sqljs.spec.ts #3 dan #7.
- implementation_notes[2] = cakupan dropdown WB direvisi (asumsi lama "semua status" kini salah) ← repo query + komentar audit.
- implementation_notes += REVISI 2026-10-04 (label, filter WB, endpoint grading-parameters, gradingParameterSync, seed guard, sync line/date/sync_error, validasi UUID server) ← diff terkait.
