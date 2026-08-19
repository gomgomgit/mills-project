# Derived Assumptions Log — module-web-station-data.screen-018--data-browser-cages-track-web.3-tech-spec

## v1 — 2026-08-18

- route/api_contracts/business_logic/data_operations/edge_case_handling = seluruh isi diturunkan meniru pola screen-016/017 (Data Browser Weighbridge/Grading Web) persis, substitusi terminologi Cages Track ← autopilot: precedent 2 screen sejenis, bukan re-interview
- response success_schema.data[].tipped_time_count = kolom turunan (COUNT baris cages-tipped-time terkait), bukan field langsung di entity cages-track-record ← diturunkan dari business spec "jumlah cage/lori tercatat" + entity-catalog (cages-track-record tidak punya kolom count langsung)
- test scenarios derived: 3 unit, 5 API, 5 component, 5 browser ← delegated ke test-spec-writer-agent dari Phase 2 bdd_scenarios, tidak ditanyakan ke user (autopilot)
