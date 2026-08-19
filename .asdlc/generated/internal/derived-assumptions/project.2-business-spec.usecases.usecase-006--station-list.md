# Derived Assumptions Log — project.2-business-spec.usecases.usecase-006--station-list

## v2 — 2026-08-18

- main_flow/alternative_flows/postconditions/business_rules = diperluas dari revisi screen-006--station-list (warna draft, breadcrumb navigasi, hamburger) ← user secara eksplisit menyatakan requirement baru; detail flow diturunkan agent
- bdd_scenarios: 2 skenario baru dari bdd-spec-writer-agent (Tap Breadcrumb, Buka Menu Hamburger) ← agent men-skip skenario untuk business_rule "warna mencerminkan status draft" karena dianggap rule display/rendering, bukan validating rule
- bdd_scenarios: 2 skenario TAMBAHAN ditulis manual ("Indikator Warna: Ada Draft" / "Tidak Ada Draft") ← override/pelengkap atas keputusan agent di atas — warna adalah perilaku yang jelas dapat diuji (given data draft → then warna tertentu) dan merupakan fitur inti yang diminta user, sehingga tetap perlu scenario eksplisit agar Phase 3 (test-spec-writer-agent) menurunkan test_scenarios untuk itu
