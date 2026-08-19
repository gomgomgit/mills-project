# Derived Assumptions Log — project.4-implement.entity-models

## v1 — 2026-08-17

- entity-models.fk_delete_behavior: hierarki (corporate→company→business-unit→station→machinery) dan detail table (grading-record→grading-detail, cages-track-record→cages-tipped-time) pakai cascade on delete; referensi user opsional (checked_by/acknowledged_by/business_unit_id di user) pakai nullOnDelete; referensi wajib (created_by, station_id) pakai restrictOnDelete ← tidak dispesifikasikan di entity-catalog, keputusan default agent
- entity-models.net_weight_enforcement: constraint `net_weight = gross_weight - tare_weight` diberlakukan via model saving() hook, bukan hanya dokumentasi ← keputusan implementasi agent
- entity-models.min_detail_row_enforcement: constraint "minimal satu detail row sebelum status=saved" (grading-record, cages-track-record) diberlakukan via saving() hook yang menolak transisi ke status=saved jika child row kosong ← keputusan implementasi agent, bukan pernyataan eksplisit di entity-catalog soal mekanisme penegakan
- entity-models.user_auth_password_column: User model override getAuthPasswordName()/getAuthPassword() agar Laravel auth memakai kolom `password_hash` (bukan konvensi default `password`) ← konsekuensi dari penamaan field di entity-catalog, ditangani secara eksplisit oleh agent
- entity-models.unique_constraints_as_db_index: constraint uniqueness dari entity-catalog (corporate.name, company.name per-corporate, business-unit.code, user.username) diimplementasikan sebagai unique index di migration, bukan hanya validasi level aplikasi ← keputusan implementasi agent
- entity-models.machinery_table_name: `Machinery` model set `$table = 'machinery'` eksplisit karena "machinery" adalah uncountable noun yang bisa salah diinfer Eloquent menjadi "machineries" ← detail teknis agent
