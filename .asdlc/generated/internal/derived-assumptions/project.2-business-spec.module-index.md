# Derived Assumptions Log — project.2-business-spec.module-index

## v1 — 2026-08-14

- modules = 6 modules: Autentikasi & Akun, Operasional Stasiun (Mobile), Data Stasiun (Web), Dashboard & Laporan, Master Data, User & Role Management ← module→screen grouping proposed by agent, not stated by user (user confirmed without changing grouping, only clarified mobile dashboard is future-phase)

## v2 — 2026-08-19

- New module "module-mills-setting" (Pengaturan Mill) created as its own module rather than folded into "module-master-data" ← agent decision: user asked for Mills Setting to be accessible by Admin + Mill Management, while module-master-data's existing description scopes it to Admin-only master data CRUD — a new module keeps that scoping description accurate instead of blurring it

## v6 — 2026-09-22

- modules.module-dashboard.screen_ids += 11 layar laporan periode + menu Dashboard & Reporting mobile ← agent chose to extend the existing dashboard module rather than create a new reporting module; user never named a module
- modules.module-master-data.screen_ids += screen-128--kelola-periode-pelaporan ← agent placed Periode Pelaporan under Master Data (it is master data, though outside the Corporate→Station hierarchy); user never named a module
- modules.*.screen_ids = direkonsiliasi penuh dari screen-index ← 67 screens (061–127) existed in screen-index with a correct module_id but had never been added back to module-index.screen_ids (module-index had been stuck at v5/2026-08-23 while screen-index reached v9). Agent repaired the back-reference; user did not ask for this
- modules.module-mobile-station-ops.description / module-web-station-data.description = "18 stasiun aktif" menggantikan daftar 7 stasiun MVP ← descriptions were stale against the actual screen list; agent updated them, user did not ask
