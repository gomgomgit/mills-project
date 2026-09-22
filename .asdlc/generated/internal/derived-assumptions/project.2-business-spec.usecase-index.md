# Derived Assumptions Log — project.2-business-spec.usecase-index

## v1 — 2026-08-14

- usecases = usecase overview text for all 32 usecases proposed by agent per module, accepted without change ← usecase descriptions are agent's synthesis from PRD/UIUX context, not verbatim user statements

## v13 — 2026-09-22

- usecases.140--tutup-buka-periode-pelaporan = dipisah menjadi usecase tersendiri dari usecase-128 ← agent judged closing/reopening a period to be a distinct business action (Admin-only, irreversible consequences) rather than part of ordinary CRUD; user did not ask for the split
- usecases.141--kunci-input-periode-tertutup = usecase lintas-layar yang menempel pada 15 layar Form & Data Preview kelima stasiun ← user stated the rule ("periode ditutup = tidak boleh masuk/diubah/diverifikasi"); expressing it as a usecase spanning existing screens, and the choice of which 15 screens it touches, is the agent's
- usecases.129–139 = 1 usecase per layar laporan, mengikuti pola 1:1 yang berlaku di seluruh index ← agent followed the existing convention; user did not specify usecase granularity
