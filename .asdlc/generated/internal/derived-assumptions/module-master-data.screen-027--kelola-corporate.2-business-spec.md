# Derived Assumptions Log — module-master-data.screen-027--kelola-corporate.2-business-spec

## v1 — 2026-08-19

- actors = ["actor-admin"] only ← derived from PRD's confirmed assumption "Master data (Station, Machinery, dst) dikelola sepenuhnya oleh Admin via web, mobile hanya read-only" + actor-index's Admin permissions listing Corporate/Company/Business Unit/Station/Machinery explicitly
- business_rules includes "Corporate tidak dapat dihapus jika masih memiliki satu atau lebih Company terkait" ← inferred referential-integrity guard from the Corporate→Company hierarchy (entity-catalog relationship), not stated directly by the user
- test_priority = "medium" ← derived: 3 business rules (uniqueness, delete-guard, admin-only access) falls in the "meaningful business rules (2-4)" bucket, not "high" (no payment/auth/personal-data handling) nor "low" (has create/edit/delete, not read-only)
- edge_cases (all 3) proposed by agent from standard CRUD failure modes (duplicate name, delete-with-children, empty list), not user-specified

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/MasterData/KelolaCorporate.php, backend/app/Services/CorporateService.php, backend/app/Rules/UniqueCaseInsensitive.php, backend/app/Rules/RealImage.php, kelola-corporate.blade.php).
- business_rules[0..1] = kode & nama unik tidak peka huruf besar/kecil ← rules() memakai UniqueCaseInsensitive::on('corporates', ...).
- business_rules (+) = email format email, website regex domain (skema opsional) ← $rules['form.email'][]='email', 'regex:'.CorporateService::WEBSITE_PATTERN.
- business_rules (+) = logo harus JPG/PNG sungguhan, dicek saat dipilih & saat Simpan ← new RealImage('Logo') + updatedLogo() → validateUploadNow().
- business_rules (+) = pesan sukses setelah simpan/hapus, dibersihkan saat aksi baru ← successMessage di save()/delete(), di-null-kan di openCreateForm/openEditForm/askDelete.
- edge_cases[1], edge_cases[3], edge_cases (+ email/website) = sesuai aturan di atas; "pratinjau tidak ditampilkan" ← blade `! $errors->has('logo')`.
- information_displayed (+) = pesan sukses ← blade alert data-testid=success-message.
