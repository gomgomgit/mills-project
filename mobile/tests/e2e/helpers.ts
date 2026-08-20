import type { Page } from '@playwright/test'

/**
 * Seeded demo accounts (admin / supervisor01 / operator01, all password
 * `Passw0rd!`, same business unit). Managed by
 * `backend/database/seeders/DemoAccountSeeder.php` (registered in
 * `DatabaseSeeder.php`) — idempotent via firstOrCreate(), so if the dev DB
 * is ever reset (`php artisan migrate:fresh`), just re-run
 * `php artisan db:seed --class=DemoAccountSeeder` (or `db:seed` for
 * everything) before re-running this suite. This seeder was added after
 * these accounts got silently wiped by a plain `migrate:fresh` (no
 * `--seed`) more than once — see its own doc comment.
 */
export const USERS = {
  operator: { username: 'operator01', password: 'Passw0rd!' },
  supervisor: { username: 'supervisor01', password: 'Passw0rd!' },
}

/**
 * Logs in via the real LoginForm (screen-002--login-mobile) against the
 * live backend (POST /api/login). Business Area is no longer collected in
 * this form — the account's own `business_unit_id` is auto-derived
 * server-side (AuthService::login() step 5), so there's no picker step
 * here anymore.
 */
export async function login(page: Page, user: { username: string; password: string } = USERS.operator): Promise<void> {
  await page.goto('/login')
  await page.locator('#username').fill(user.username)
  await page.locator('#password').fill(user.password)

  await page.getByRole('button', { name: 'Login' }).click()
  await page.waitForURL('**/home')
}

export async function getAuthUserId(page: Page): Promise<string> {
  return page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_user')
    return raw ? (JSON.parse(raw).id ?? '') : ''
  })
}

export async function getBusinessUnitId(page: Page): Promise<string> {
  return page.evaluate(() => {
    const raw = localStorage.getItem('msl_auth_business_unit')
    return raw ? (JSON.parse(raw).id ?? '') : ''
  })
}

/**
 * Seeds the local (offline) `station` reference table directly via the
 * dev-only `window.__mslTestDb` bridge (see main.ts) — there is no
 * in-app sync flow yet that populates this table (see
 * stationRepo.ts/localSchema.ts's header comments, which explicitly defer
 * that to "a separate sync flow, not yet built"), so screen-006's
 * "active station" / "disabled station" scenarios have no real data
 * source to exercise without this. This is test-fixture seeding, not a
 * product feature.
 */
export async function seedStations(page: Page, businessUnitId: string): Promise<void> {
  await page.evaluate(async (buId) => {
    const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
      .__mslTestDb
    const now = new Date().toISOString()
    const rows: Array<[string, string, string, number]> = [
      ['e2e-station-weighbridge', 'Timbangan 01', 'weighbridge', 1],
      ['e2e-station-grading', 'Grading 01', 'grading', 1],
      ['e2e-station-cages-track', 'Cages Track 01', 'cages-track', 1],
      ['e2e-station-placeholder', 'Stasiun Cadangan 01', 'other', 0],
    ]

    for (const [id, name, type, isActive] of rows) {
      await db.run(
        'INSERT OR REPLACE INTO station (id, business_unit_id, name, type, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [id, buId, name, type, isActive, now, now],
      )
    }
  }, businessUnitId)
}

/**
 * Seeds `count` draft_paused rows directly into one of the 3 local draft
 * tables for the given user — used only by the Home "Banyak Draft
 * Menumpuk" scenario, which needs more paused drafts
 * (`PAUSED_DRAFTS_DISPLAY_THRESHOLD` = 10, see useHomeSummary.ts) than is
 * practical to create by repeatedly driving the real Monitor UI (which
 * only ever tracks one current draft per station per user).
 */
export async function seedPausedDrafts(
  page: Page,
  table: 'weighbridge_record' | 'grading_record' | 'cages_track_record',
  userId: string,
  count: number,
): Promise<void> {
  await page.evaluate(
    async ({ table, userId, count }) => {
      const db = (window as unknown as { __mslTestDb: { run: (sql: string, params?: unknown[]) => Promise<unknown> } })
        .__mslTestDb

      for (let i = 0; i < count; i += 1) {
        const id = `e2e-seed-${table}-${i}-${Date.now()}`
        const updatedAt = new Date(Date.now() - i * 60_000).toISOString()
        await db.run(
          `INSERT INTO ${table} (id, status, created_by, created_at, updated_at) VALUES (?, 'draft_paused', ?, ?, ?)`,
          [id, userId, updatedAt, updatedAt],
        )
      }
    },
    { table, userId, count },
  )
}
