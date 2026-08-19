# Derived Assumptions Log — project.1-foundation.test-strategy

## v1 — 2026-08-14

- unit_test.run_command = "php artisan test --testsuite=Unit" ← user asked for "simple", agent chose the exact command
- integration_test.seed_command = "php artisan migrate:fresh --seed --env=testing" ← user confirmed the suggested example
- integration_test.run_command = "php artisan test --testsuite=Feature" ← user confirmed the suggested example
- component_test.run_command = "vitest run" ← user gave a partial answer ("vitest aja"), agent completed the flag
- browser_test.start_command = "php artisan serve" ← user gave a partial answer ("php artisan aja"), agent completed the subcommand
- browser_test.base_url = "http://localhost:8000" ← user confirmed the suggested example
- browser_test.run_command = "playwright test" ← user confirmed the suggested example
