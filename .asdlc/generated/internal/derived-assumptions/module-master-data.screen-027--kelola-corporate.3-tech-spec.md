# Derived Assumptions Log — module-master-data.screen-027--kelola-corporate.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- logo validation rule (jpg/png, max 2MB) ← not specified anywhere, inferred as a reasonable default file-upload constraint, mirrors typical Laravel validation patterns; flagged since no source document specifies exact limits
- logo_url as the response field name (vs `logo` as the request/upload field name) ← mirrors machinery.picture_url's request-vs-response naming split, kept consistent across the two entities that now have image uploads
- corporate_code and name both required+unique independently (not one superseding the other) ← direct continuation of the entity-catalog v4 decision already logged there

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CorporateService.php, KelolaCorporate.php, RealImage.php, UniqueCaseInsensitive.php, ValidatesUploadOnSelect.php).
- api_contracts[0].endpoints[1..2].request.body_schema.{corporate_code,name,website,logo} = unique case-insensitive / WEBSITE_PATTERN / RealImage ← CorporateService::validate().
- api_contracts[0].endpoints[1..2].response.error_codes[0].condition = mencakup email/website/logo palsu ← aturan validasi baru.
- api_contracts[0].business_logic[1..2] = urutan validasi baru ← CorporateService::validate().
- api_contracts[0].edge_case_handling (+3) = beda huruf, format email/website, file logo palsu ← kode + ImageUploadValidationTest/MasterDataValidationAuditTest.
- api_contracts[0].unit_test_cases (+3) = kasus di atas ⚠ diturunkan dari tes Livewire/Feature (MasterDataValidationAuditTest, ImageUploadValidationTest), bukan dari tes unit service khusus.
- implementation_notes (+) = catatan REVISI audit-fix.
