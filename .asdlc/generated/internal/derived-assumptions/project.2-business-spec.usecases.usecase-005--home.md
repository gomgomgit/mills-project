# Derived Assumptions Log — project.2-business-spec.usecases.usecase-005--home

## v2 — 2026-08-18

- name/description/main_flow/alternative_flows/postconditions/business_rules = diturunkan penuh dari revisi screen-005--home (Home berubah dari "lihat status draft" menjadi "navigation launcher") ← user secara eksplisit menyatakan tujuan baru Home; detail flow (step-by-step, alternative flow) diturunkan agent dari deskripsi user
- bdd_scenarios: 3 skenario lama ("Lihat Status Draft & Navigasi — success/empty-state/banyak-draft-menumpuk") DIGANTI (bukan dipertahankan berdampingan) dengan 4 skenario baru dari bdd-spec-writer-agent ← override kebijakan default agen "never remove" — 3 skenario lama menguji perilaku (tampilkan ringkasan status draft) yang secara eksplisit dan sengaja dihapus oleh user dari Home ("tidak perlu ada info draft di halaman home... DIHAPUS TOTAL"); mempertahankan keduanya sekaligus akan membuat bdd_scenarios kontradiktif terhadap business_rules baru sendiri ("Home tidak menampilkan status draft/record")
