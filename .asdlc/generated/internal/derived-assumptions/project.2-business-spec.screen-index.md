# Derived Assumptions Log — project.2-business-spec.screen-index

## v1 — 2026-08-14

- screens.module-auth = 4 screens: Login Web, Login Mobile, Ganti Password Web, Ganti Password Mobile ← screen breakdown proposed by agent, accepted without change
- screens.module-mobile-station-ops = 11 screens: Home, Station List, Monitor x3, Form x3, Data Preview x3 ← screen breakdown proposed by agent, accepted without change
- screens.module-web-station-data = 9 screens: Data Browser x3, Detail x3, Form x3 (web) ← screen breakdown proposed by agent, accepted without change
- screens.module-dashboard = 2 screens: Dashboard Web, Laporan Manajemen (mobile dashboard deferred to future phase) ← screen breakdown proposed by agent, accepted without change
- screens.module-master-data = 5 screens: Kelola Corporate/Company/Business Unit/Station/Machinery ← screen breakdown proposed by agent, accepted without change
- screens.module-user-management = 1 screen: Kelola User & Role ← screen breakdown proposed by agent, accepted without change

## v10 — 2026-09-22

- screens.128–139 = 12 layar baru untuk Full Cycle per Stasiun ← user stated the feature and the 5 stations; the split into 1 master + 5 web + 1 mobile container + 5 mobile screens is the agent's breakdown
- screens.135–139 = 5 layar laporan mobile TERPISAH per stasiun (bukan satu layar dengan pemilih stasiun) ← agent mirrored the existing per-station screen pattern (Monitor/Form/Data Preview are all per-station); user only said reports must exist on mobile
- screens.134--dashboard-reporting-mobile = layar wadah tersendiri ← user said mobile reports live "di menu dashboard & reporting"; modelling that menu as its own screen with a station list is the agent's choice
- screens.129–133 isi laporan per stasiun (siklus rebus, antrean lori, blowdown/sootblowing, produksi minyak murni, pergerakan stok) ← derived by the agent from each station's recorded columns; user never specified report contents
