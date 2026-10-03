# Derived Assumptions Log — module-auth.screen-002--login-mobile.4-implement

## v1 — 2026-08-17

- Web-session dan mobile-token path digabung dalam satu AuthService::login() via parameter opsional $deviceName ← keputusan desain agent untuk menghindari duplikasi, sesuai seam yang ditinggalkan di screen-001
- CSRF dikecualikan khusus untuk path api/login ← diperlukan karena request pertama mobile tidak punya CSRF token; dikonfirmasi tidak memengaruhi alur Livewire web screen-001
- Migration personal_access_tokens (Sanctum) ditambahkan ← infra yang diperlukan tapi tidak eksplisit di entity-catalog/tech-spec, dependency dari fitur createToken()
- Device name detection pakai navigator.userAgent + random suffix cache (bukan @capacitor/device) ← placeholder karena package belum terpasang
- Offline session-expiry pakai heuristik grace period 7 hari ← tech-spec eksplisit menyebut durasi validitas token offline sebagai open question, agent memilih default konkret
- **Gap ditemukan (major)**: endpoint GET /api/business-units tidak ada di api-index/tech-spec manapun — dropdown Business Area di LoginForm mobile akan kosong di runtime nyata sampai endpoint ini ditambahkan. Perlu ditambahkan sebagai endpoint shared (dipakai screen-001 web login juga) di iterasi berikutnya.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar
