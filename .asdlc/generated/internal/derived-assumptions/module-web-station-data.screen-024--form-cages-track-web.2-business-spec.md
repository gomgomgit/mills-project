
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (form-cages-track.blade.php, FormCagesTrack.php, CagesTrackRecordService.php).
- information_displayed[0] = BU + Production Line, station dari line ← resolveActiveStationForActor(production_line_id). (Spec lama: station dari Business Unit.)
- information_displayed[2], business_rules[2] = default WIB, Tanggal tidak boleh melewati besok, tippler ber-zona disimpan WIB ← assertEventDateNotTooFarAhead, AppTime::normalizeClientDateTime.
- information_displayed[8], business_rules[7] = Total Cages/Cages Remain teks ← blade fc-computed-value.
- business_rules[8] = BU/Production Line/station immutable.
- edge_cases[2] = berbasis Production Line; += tanggal > besok, id bukan UUID ← GuardsRecordIdShape.
