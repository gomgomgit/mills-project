/**
 * Regresi browser temuan audit 2026-10-05 (web & API), dijalankan terhadap
 * server e2e (:8001, PostgreSQL mill_smart_log_e2e) — beberapa temuan ini
 * HANYA muncul di PostgreSQL (SQLSTATE 22P02), tidak di SQLite suite PHP.
 *
 * Setiap test memasang watchProblems(): pageerror, console.error, dan respons
 * aplikasi >= 400 menggagalkan test.
 *
 *  #1 Kelola Station: hapus station yang punya record stasiun → pesan ramah,
 *     bukan 500.
 *  #2 Form Grading mode edit: label Business Unit = nama mill (bukan nama
 *     stasiun), Production Line tampil.
 *  #5 Detail Boiler Room / Effluent Plant / Engine Room menampilkan label
 *     pilihan (Ya/Tidak, Fault, Standby, ...) dan ekspor CSV-nya sama; CPO
 *     Dispatch Time In/Out HH:MM.
 *  #6 Data Browser (18 stasiun): production_line_id bukan UUID diabaikan.
 *  #7 GET /api/stations?production_line_id menyaring per line.
 * #11 404 verifikasi membawa code NOT_FOUND.
 *  #8b Reset Password oleh Admin mengeluarkan sesi web user itu; Admin yang
 *     mereset password-nya sendiri tetap login.
 *
 * Record yang ditanam (#5) bertanggal di lajur masa lalu (2019-12-15, lihat
 * support/period-lanes.ts) dan dihapus lagi di afterAll; globalTeardown juga
 * menyapu lajur itu.
 */

import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { artisan } from './support/backend'
import { watchProblems } from './support/page-health'
import { statefulHeaders } from './support/periods'
import { findRow } from './support/paged-table'

const SUPERVISOR = 'brtest-supervisor01' // BU Browser Test
const ADMIN = 'stest-admin01'
const LANE_DATE = '2019-12-15'

interface Seeded {
  boiler: string
  effluent: string
  engine: string
  cpo: string
  productionLineId: string
}

let seeded: Seeded

async function tinker(code: string): Promise<string> {
  return artisan(['tinker', '--execute', code])
}

test.describe('Audit 2026-10-05', () => {
  let problems: string[] = []

  test.beforeAll(async () => {
    test.setTimeout(120_000)
    const out = await tinker(`
      $pl = App\\Models\\ProductionLine::where('name', 'PL Mill A')->firstOrFail();
      $st = fn ($t) => App\\Models\\Station::where('production_line_id', $pl->id)->where('type', $t)->firstOrFail();
      $b = App\\Models\\BoilerRoomRecord::factory()->forStation($st('boiler-room'))->onDate('${LANE_DATE}')->create(['boiler_room_id' => 'BLR-AUDIT05']);
      App\\Models\\BoilerRoomDetail::factory()->forRecord($b)->create(['time_slot' => '07:00:00', 'blowdown_executed' => 'y', 'sootblowing_executed' => 'n']);
      $e = App\\Models\\EffluentPlantRecord::factory()->forStation($st('effluent-plant'))->onDate('${LANE_DATE}')->create();
      App\\Models\\EffluentPlantDetail::factory()->forRecord($e)->create(['time_slot' => '07:00:00', 'biogas_flare_status' => 'fault', 'dosing_pump_1_status' => 'run', 'sludge_dewatering_status' => 'stop']);
      $g = App\\Models\\EngineRoomRecord::factory()->forStation($st('engine-room'))->onDate('${LANE_DATE}')->create();
      App\\Models\\EngineRoomDetail::factory()->forRecord($g)->create(['time_slot' => '07:00:00', 'diesel_gen_1_status' => 'standby', 'diesel_gen_2_status' => 'off']);
      $c = App\\Models\\CpoDispatchRecord::factory()->forStation($st('cpo-dispatch'))->onDate('${LANE_DATE}')->create();
      App\\Models\\CpoDispatchDetail::factory()->forRecord($c)->create(['event_date' => '${LANE_DATE}', 'time_in' => '07:05:00', 'time_out' => '08:40:00']);
      echo 'SEEDED=' . json_encode(['boiler' => $b->id, 'effluent' => $e->id, 'engine' => $g->id, 'cpo' => $c->id, 'productionLineId' => $pl->id]);
    `)
    const line = out.split('\n').find((l) => l.startsWith('SEEDED='))
    if (!line) throw new Error(`seed gagal: ${out}`)
    seeded = JSON.parse(line.slice('SEEDED='.length))
  })

  test.afterAll(async () => {
    if (!seeded) return
    await tinker(`
      App\\Models\\BoilerRoomRecord::whereKey('${seeded.boiler}')->get()->each(function ($r) { $r->boilerRoomDetails()->delete(); $r->delete(); });
      App\\Models\\EffluentPlantRecord::whereKey('${seeded.effluent}')->get()->each(function ($r) { $r->effluentPlantDetails()->delete(); $r->delete(); });
      App\\Models\\EngineRoomRecord::whereKey('${seeded.engine}')->get()->each(function ($r) { $r->engineRoomDetails()->delete(); $r->delete(); });
      App\\Models\\CpoDispatchRecord::whereKey('${seeded.cpo}')->get()->each(function ($r) { $r->cpoDispatchDetails()->delete(); $r->delete(); });
    `)
  })

  test.afterEach(() => {
    expect(problems).toEqual([])
  })

  test('#1 hapus station yang masih punya record stasiun: pesan ramah, baris tetap, tanpa 500', async ({ page }) => {
    problems = watchProblems(page)
    await login(page, ADMIN, PASSWORD)
    await page.goto('/master-data/stations')

    await pick(page, 'filterBusinessUnitId', 'BU Browser Test')
    await pick(page, 'filterProductionLineId', 'PL Mill A')

    const row = page.locator('.kc-table__row', { hasText: 'Cpo Dispatch' }).filter({ hasText: 'PL Mill A' })
    await expect(row).toHaveCount(1)
    await row.locator('button', { hasText: 'Hapus' }).click()
    await row.locator('button', { hasText: 'Ya, Hapus' }).click()

    await expect(page.locator('.kc-alert')).toContainText('record stasiun')
    await expect(page.locator('.kc-table__row', { hasText: 'Cpo Dispatch' }).filter({ hasText: 'PL Mill A' })).toHaveCount(1)
  })

  test('#2 Form Grading mode edit: Business Unit = nama mill, Production Line tampil', async ({ page }) => {
    problems = watchProblems(page)
    await login(page, SUPERVISOR, PASSWORD)
    await page.goto('/data/grading')
    await page.locator('.gr-table__row', { hasText: 'GR-BROWSER-EDIT' }).click()
    await page.locator('[data-testid="edit-button"]').click()
    await page.waitForURL((url) => url.pathname.endsWith('/edit'))

    await expect(page.locator('[data-testid="business-unit-readonly"]')).toHaveText('BU Browser Test')
    await expect(page.locator('[data-testid="production-line-readonly"]')).toHaveText('PL Mill A')
  })

  test('#5 Detail menampilkan label pilihan, ekspor CSV memakai label & format yang sama', async ({ page }) => {
    problems = watchProblems(page)
    await login(page, SUPERVISOR, PASSWORD)

    await page.goto(`/data/boiler-room/${seeded.boiler}`)
    const boilerCells = await page.locator('td').allInnerTexts()
    expect(boilerCells.map((t) => t.trim())).toEqual(expect.arrayContaining(['Ya', 'Tidak']))
    expect(boilerCells.map((t) => t.trim())).not.toContain('y')

    await page.goto(`/data/effluent-plant/${seeded.effluent}`)
    expect((await page.locator('td').allInnerTexts()).map((t) => t.trim())).toEqual(expect.arrayContaining(['Fault', 'Run', 'Stop']))

    await page.goto(`/data/engine-room/${seeded.engine}`)
    expect((await page.locator('td').allInnerTexts()).map((t) => t.trim())).toEqual(expect.arrayContaining(['Standby', 'Off']))

    const csv = async (endpoint: string) => {
      const response = await page.request.get(`/api/${endpoint}/export?date_from=${LANE_DATE}&date_to=${LANE_DATE}&format=csv`)
      expect(response.status()).toBe(200)
      const lines = (await response.text()).replace(/^﻿/, '').trim().split('\n')
      const header = parseCsvLine(lines[0])
      const rows = lines.slice(1).map(parseCsvLine)
      return { col: (name: string) => header.indexOf(name), rows }
    }

    const cpo = await csv('cpo-dispatch-records')
    const cpoRow = cpo.rows.find((r) => r[cpo.col('Time In')] !== '')!
    expect(cpoRow[cpo.col('Time In')]).toBe('07:05')
    expect(cpoRow[cpo.col('Time Out')]).toBe('08:40')

    const boiler = await csv('boiler-room-records')
    const boilerRow = boiler.rows.find((r) => r.includes('BLR-AUDIT05'))!
    expect(boilerRow[boiler.col('Blowdown Executed')]).toBe('Ya')
    expect(boilerRow[boiler.col('Sootblowing Executed')]).toBe('Tidak')

    const effluent = await csv('effluent-plant-records')
    expect(effluent.rows.some((r) => r[effluent.col('Biogas Flare Status')] === 'Fault' && r[effluent.col('Sludge Dewatering Status')] === 'Stop')).toBe(true)

    const engine = await csv('engine-room-records')
    expect(engine.rows.some((r) => r[engine.col('Diesel Gen 1 Status')] === 'Standby' && r[engine.col('Diesel Gen 2 Status')] === 'Off')).toBe(true)
  })

  test('#6 Data Browser ke-18 stasiun: production_line_id bukan UUID diabaikan tanpa error (PostgreSQL)', async ({ page }) => {
    test.setTimeout(180_000)
    problems = watchProblems(page)
    await login(page, ADMIN, PASSWORD)

    for (const path of DATA_BROWSERS) {
      await page.goto(`/data/${path}`)
      const [response] = await Promise.all([
        page.waitForResponse((r) => r.url().includes('/livewire/update')),
        page.evaluate(() => {
          const lw = (window as any).Livewire
          const component = lw.all().find((c: any) => c.snapshot?.data && 'production_line_id' in c.snapshot.data)
          return component.$wire.set('production_line_id', 'bukan-uuid')
        }),
      ])
      expect(response.status(), path).toBe(200)
      const value = await page.evaluate(() => {
        const lw = (window as any).Livewire
        return lw.all().find((c: any) => c.snapshot?.data && 'production_line_id' in c.snapshot.data).$wire.production_line_id
      })
      expect(value, path).toBe('')
    }
  })

  test('#7 GET /api/stations?production_line_id menyaring per line; nilai bukan UUID diabaikan', async ({ page }) => {
    problems = watchProblems(page)
    await login(page, ADMIN, PASSWORD)
    const headers = await statefulHeaders(page)

    const filtered = await page.request.get(`/api/stations?per_page=100&production_line_id=${seeded.productionLineId}`, { headers })
    expect(filtered.status()).toBe(200)
    const body = await filtered.json()
    expect(body.data.length).toBeGreaterThan(0)
    expect(new Set(body.data.map((s: any) => s.production_line_id))).toEqual(new Set([seeded.productionLineId]))

    const invalid = await page.request.get('/api/stations?production_line_id=bukan-uuid', { headers })
    expect(invalid.status()).toBe(200)
  })

  test('#11 404 verifikasi membawa amplop error standar (code NOT_FOUND)', async ({ page }) => {
    problems = watchProblems(page, { allowStatus: [404] })
    await login(page, SUPERVISOR, PASSWORD)
    const headers = await statefulHeaders(page)

    const get = await page.request.get(`/api/records/bukan-stasiun/verification?ids[]=${seeded.boiler}`, { headers })
    expect(get.status()).toBe(404)
    expect(await get.json()).toEqual({ message: 'Jenis stasiun tidak dikenal.', code: 'NOT_FOUND' })

    const patch = await page.request.patch('/api/records/boiler-room/00000000-0000-4000-8000-000000000000/verification', {
      headers,
      data: { level: 'checked', value: true },
    })
    expect(patch.status()).toBe(404)
    expect(await patch.json()).toEqual({ message: 'Record tidak ditemukan.', code: 'NOT_FOUND' })
  })
})

test.describe('Audit 2026-10-05 #8b reset password', () => {
  const suffix = Date.now()
  const otherUser = `a05-sup-${suffix}`
  const selfAdmin = `a05-adm-${suffix}`

  test.beforeAll(async () => {
    await tinker(`
      $bu = App\\Models\\BusinessUnit::where('name', 'BU Browser Test')->firstOrFail();
      foreach ([['${otherUser}', 'supervisor', $bu->id], ['${selfAdmin}', 'admin', null]] as [$u, $r, $b]) {
        App\\Models\\User::create(['username' => $u, 'name' => 'Audit 05 '.$u, 'role' => $r, 'business_unit_id' => $b, 'is_active' => true, 'password_hash' => Illuminate\\Support\\Facades\\Hash::make('${PASSWORD}')]);
      }
    `)
  })

  test.afterAll(async () => {
    await tinker(`App\\Models\\User::whereIn('username', ['${otherUser}', '${selfAdmin}'])->get()->each(function ($u) { $u->tokens()->delete(); $u->delete(); });`)
  })

  test('reset password user lain mengeluarkan sesinya; reset password sendiri tidak', async ({ browser }) => {
    test.setTimeout(120_000)
    const victimContext = await browser.newContext()
    const victim = await victimContext.newPage()
    const victimProblems = watchProblems(victim)
    await login(victim, otherUser, PASSWORD)
    await victim.goto('/data/weighbridge')
    await expect(victim).toHaveURL(/\/data\/weighbridge$/)

    const adminContext = await browser.newContext()
    const admin = await adminContext.newPage()
    const adminProblems = watchProblems(admin)
    await login(admin, selfAdmin, PASSWORD)
    await admin.goto('/users')

    for (const [username, password] of [[otherUser, 'BaruSekali1!'], [selfAdmin, 'AdminBaru1!']]) {
      const row = await findRow(admin, username)
      await row.locator('button', { hasText: 'Edit' }).click()
      await admin.locator('#password').fill(password)
      await admin.locator('button[type="submit"]', { hasText: 'Simpan' }).click()
      await expect(admin.locator('body')).toContainText('password telah direset')
    }

    // Admin tetap login sesudah mereset password-nya sendiri.
    await admin.goto('/users')
    await expect(admin).toHaveURL(/\/users$/)

    // Sesi user lain dikeluarkan pada request berikutnya, dengan pesan.
    await victim.goto('/data/weighbridge')
    await expect(victim).toHaveURL(/\/login/)
    await expect(victim.locator('body')).toContainText('password direset oleh Admin')

    expect(victimProblems).toEqual([])
    expect(adminProblems).toEqual([])
    await victimContext.close()
    await adminContext.close()
  })
})

const DATA_BROWSERS = [
  'weighbridge', 'grading', 'cages-track', 'threshing', 'pressing', 'depricarping', 'kernel-plant',
  'solid-waste-disposal', 'process-water', 'kernel-dispatch', 'cpo-dispatch', 'effluent-plant',
  'storage-tank', 'engine-room', 'boiler-room', 'clarification', 'process-quality-control', 'sterilizer',
]

async function pick(page: Page, id: string, label: string): Promise<void> {
  await page.locator(`#${id}`).click()
  await page.locator(`#${id}`).fill(label)
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('/livewire/update')),
    page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click(),
  ])
}

function parseCsvLine(line: string): string[] {
  const cells: string[] = []
  let cell = ''
  let quoted = false
  for (let i = 0; i < line.length; i++) {
    const ch = line[i]
    if (quoted) {
      if (ch === '"' && line[i + 1] === '"') { cell += '"'; i++ } else if (ch === '"') { quoted = false } else { cell += ch }
    } else if (ch === '"') {
      quoted = true
    } else if (ch === ',') {
      cells.push(cell); cell = ''
    } else {
      cell += ch
    }
  }
  cells.push(cell.replace(/\r$/, ''))
  return cells
}
