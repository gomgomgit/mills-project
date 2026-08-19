# Derived Assumptions Log — project.1-foundation.arch-spec

## v1 — 2026-08-14

- system_type = "Web app (Laravel monolith: REST API + Livewire UI...) + Mobile app (Vue 3 + Capacitor...)" ← question skipped, derived directly from PRD (already explicit there)
- architecture_pattern = "Layered Architecture (Laravel MVC + Service Layer)" with components ← always derived by agent per command rules, not asked to user
- tech_stack: "ORM / data access: Eloquent ORM (Laravel native)" ← sensible default for Laravel stack, not explicitly specified by user
- tech_stack: "migration: Laravel Migrations (Schema Builder)" ← sensible default for Laravel stack, not explicitly specified by user
- tech_stack: "test framework: PHPUnit / Pest (backend), Vitest (mobile Vue components)" ← sensible default, not explicitly specified by user
- tech_stack: "mobile state management: Pinia" ← sensible default for Vue 3 stack, not explicitly specified by user
- deployment.provider = "Server fisik/VM lokal di lingkungan mill, dikelola tim IT internal" ← user only confirmed on-premise model, specific provider detail derived by agent
- nfr (all 5 categories: performance, security, scalability, availability, compliance) ← derived from PRD constraints/goals + scale answer, not individually confirmed field-by-field
- architecture_notes = trade-off summary text ← always derived by agent per command rules, not asked to user
