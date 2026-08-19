# Derived Assumptions Log — module-web-station-data.screen-017--data-browser-grading-web.3-tech-spec

## v1 — 2026-08-17

- Endpoint `GET /api/grading-records` (list) dan `GET /api/grading-records/export` (ekspor) beserta skema request/response ← desain teknis agent, konsisten dengan pola screen-016
- Export dimodelkan sebagai response file stream/binary, bukan JSON ← keputusan desain agent
- Daftar error code: 422 INVALID_DATE_RANGE, 422 EXPORT_FAILED ← diturunkan dari edge_cases business spec
- screen_dependencies ke screen-020 (detail) dan screen-023 (form web) ← disimpulkan dari available_actions business spec
