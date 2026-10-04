# Derived Assumptions Log — module-master-data.screen-029--kelola-business-unit.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- code (existing since v1) kept its field name, not renamed to business_unit_code — consistent with entity-catalog v4's decision to only introduce entity-prefixed `*_code` naming for fields that didn't already exist (corporate_code, company_code)
- Logo storage convention: screen-027's implementation established `FILESYSTEM_DISK=local` with Laravel's `local` disk `'serve' => true` auto-registering `/storage/{path}` (no `public` disk / `storage:link` needed) — reused here as `LOGO_DIRECTORY=business-unit-logos`, kept consistent across all 3 master-data screens with a logo field rather than letting each screen invent its own convention
- business_unit_type_code treated as a free-text optional string (not an enum/FK to a lookup table) — the ERD gives no further detail on what values it takes or whether it's governed by a master list; kept as a plain string until such a list is specified

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v6)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (BusinessUnitService.php, KelolaBusinessUnit.php, RealImage.php, UniqueCaseInsensitive.php).
- api_contracts[0].endpoints[2..3].request.body_schema.{code,logo} = case-insensitive / RealImage ← BusinessUnitService::validate().
- api_contracts[0].endpoints[4].description + error_codes[0].condition = 409 BUSINESS_UNIT_HAS_STATIONS untuk User/PL/Station/Period ← delete(). ⚠ kode error tetap BUSINESS_UNIT_HAS_STATIONS (kelas exception tak berganti) — diasumsikan dari kelas exception yang sama.
- api_contracts[0].business_logic[2..4] = validasi baru + penjaga hapus 4 ketergantungan ← kode.
- api_contracts[0].data_operations[4..5], business_rules_applied[1] = penjaga hapus diperluas ← delete().
- api_contracts[0].edge_case_handling[1], [6], (+1) = penjaga hapus, logo palsu, kode beda huruf ← kode.
- api_contracts[0].unit_test_cases[12] = 409 dengan pesan merinci, user tetap punya BU ← MasterDataDeleteGuardTest.
- implementation_notes (+) = REVISI audit-fix.

## v8 — 2026-10-05

Sumber: perbaikan lanjutan audit 2026-10-05 (belum di-commit), code is truth (BusinessUnitHasStationsException.php, KelolaBusinessUnitTest API).
- implementation_notes (+1): body 409 kini benar-benar membawa code BUSINESS_UNIT_HAS_STATIONS (spec sudah menyatakannya; kode menyusul).
