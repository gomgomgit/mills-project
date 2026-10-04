# Derived Assumptions Log — module-master-data.screen-028--kelola-company.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- company_code globally unique, name still per-corporate unique ← two independent uniqueness rules coexist, per entity-catalog v4's decision (code mirrors ERD's PK-semantics: globally unique; name keeps its pre-existing screen-028 design point of per-parent scoping)
- logo/logo_url handling identical to screen-027's pattern (jpg/png, max 2MB, stored path exposed as logo_url) — kept consistent across both entities now carrying a logo field
- last_update (date, optional) passed through in request/response per entity-catalog's addition, no additional validation beyond being a valid date

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CompanyService.php, KelolaCompany.php, RealImage.php, UniqueCaseInsensitive.php).
- api_contracts[0].endpoints[2..3].request.body_schema.{company_code,name,logo} = unique case-insensitive / RealImage ← CompanyService::validate().
- api_contracts[0].business_logic[2..3] = urutan validasi dengan case-insensitive + RealImage ← CompanyService::validate().
- api_contracts[0].edge_case_handling[6] (diubah) + (1 baru) = logo palsu ditolak saat dipilih; kode beda huruf ditolak ← kode.
- api_contracts[0].unit_test_cases (+2) ⚠ diturunkan dari tes Livewire (MasterDataValidationAuditTest, ImageUploadValidationTest), bukan tes unit service khusus.
- implementation_notes (+) = REVISI audit-fix.
