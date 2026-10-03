import { expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './auth'
import { statefulHeaders } from './periods'

/**
 * Skenario filter Production Line yang SERAGAM untuk ke-18 spec
 * `data-browser-*.spec.ts`.
 *
 * ── MENGAPA INI ADA, SEJAK 2026-10-03 ───────────────────────────────────
 *
 * Ke-18 Data Browser web punya filter Production Line (default '' =
 * "Semua Line") yang menyaring berdasarkan `production_line_id` MILIK
 * RECORD, kolom Production Line sebagai kolom PERTAMA tabel dan ekspor, dan
 * filter aktif yang ikut terbawa ke tautan ekspor. Tidak satu pun dari ke-18
 * spec menyentuh filter itu.
 *
 * ── DATANYA ─────────────────────────────────────────────────────────────
 *
 * BrowserTestFixtureSeeder::productionLineFilterFixtures() menanam, untuk
 * setiap jenis stasiun, `<AWALAN>-BROWSER-PL-A` di "PL Mill A" dan
 * `<AWALAN>-BROWSER-PL-B` di "PL Mill B" — mill yang sama, tanggal
 * 2025-03-10. Ditanam lewat seeder, bukan lewat form: tidak ada spec
 * data-browser yang menulis record, dan menulis lewat form berarti
 * prasyarat Periode Pelaporan terbuka (usecase-141) yang, bila gagal,
 * membuat hook MENGGANTUNG alih-alih gagal.
 *
 * Tabel disaring tepat ke tanggal itu supaya yang tampil hanya dua record
 * tersebut. Kehadiran record line B di "Semua Line" DIASERSI LEBIH DULU
 * sebelum ketidakhadirannya di tampilan tersaring — tanpa itu
 * `toHaveCount(0)` lulus juga di atas data kosong.
 */

export const FILTER_RECORD_DATE = '2025-03-10'
export const FILTER_LINE_A = 'PL Mill A'
export const FILTER_LINE_B = 'PL Mill B'
const FIXTURE_BUSINESS_UNIT = 'BU Browser Test'

export interface ProductionLineFilterCase {
  /** Akun yang login — Supervisor (terikat mill) atau Admin (semua mill). */
  username: string
  /** Rute Data Browser, mis. '/data/sterilizer'. */
  path: string
  /** Awalan kelas BEM layar, mis. 'sf' untuk .sf-browser / .sf-table__row. */
  cls: string
  /** Awalan id bisnis record fixture, mis. 'STER' -> STER-BROWSER-PL-A. */
  idPrefix: string
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Memilih satu line pada combobox #production_line_id.
 *
 * Label opsinya bergantung peran: Supervisor melihat "PL Mill A", Admin
 * tanpa Business Unit terpilih melihat "BU Browser Test — PL Mill A"
 * (ScopesToActorMill::productionLineOptionsForReadActor). Regex berjangkar
 * menerima keduanya tanpa pernah cocok ke line bernama sama di mill lain.
 */
async function pickLine(page: Page, line: string): Promise<void> {
  const input = page.locator('#production_line_id')
  const name = new RegExp(`^(${escapeRegExp(FIXTURE_BUSINESS_UNIT)} — )?${escapeRegExp(line)}$`)

  await input.click()
  await input.fill(line)

  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    page.locator('#production_line_id-listbox').getByRole('option', { name }).click(),
  ])
}

/**
 * (a) "Semua Line" memuat baris kedua line dan kolom pertama "Production
 * Line"; (b) memilih satu line menyempitkan tabel ke line itu saja;
 * (c) tautan ekspor membawa production_line_id, dan CSV-nya ikut tersaring
 * dengan "Production Line" sebagai kolom pertama.
 */
export async function assertProductionLineFilter(page: Page, c: ProductionLineFilterCase): Promise<void> {
  const idA = `${c.idPrefix}-BROWSER-PL-A`
  const idB = `${c.idPrefix}-BROWSER-PL-B`

  await login(page, c.username, PASSWORD)
  await page.goto(c.path)

  await page.locator('#date_from').fill(FILTER_RECORD_DATE)
  await page.locator('#date_to').fill(FILTER_RECORD_DATE)

  const browser = page.locator(`.${c.cls}-browser`)
  const rows = page.locator(`.${c.cls}-table__row`)
  const rowA = rows.filter({ hasText: idA })
  const rowB = rows.filter({ hasText: idB })
  const csvLink = page.locator(`.${c.cls}-browser__export a`, { hasText: 'Ekspor CSV' })

  await expect(browser).not.toHaveClass(new RegExp(`${c.cls}-browser--busy`))

  // (a) Default "Semua Line": kedua record ADA — inilah yang membuat
  // asersi ketidakhadiran di langkah (b) bermakna.
  await expect(page.locator('#production_line_id')).toHaveValue('Semua Line')
  await expect(page.locator(`.${c.cls}-table thead th`).first()).toHaveText('Production Line')
  await expect(rowA).toHaveCount(1)
  await expect(rowB).toHaveCount(1)
  await expect(rowA.locator('td').first()).toHaveText(FILTER_LINE_A)
  await expect(rowB.locator('td').first()).toHaveText(FILTER_LINE_B)
  await expect(csvLink).not.toHaveAttribute('href', /production_line_id=/)

  // (b) Pilih PL Mill B — line yang BUKAN line utama fixture, sehingga
  // lolosnya asersi ini tidak bisa kebetulan dari data line utama.
  await pickLine(page, FILTER_LINE_B)

  await expect(browser).not.toHaveClass(new RegExp(`${c.cls}-browser--busy`))
  await expect(rowA).toHaveCount(0)
  await expect(rowB).toHaveCount(1)
  // Setiap baris yang tersisa milik line terpilih, bukan hanya fixture-nya.
  const firstCells = rows.locator('td:first-child')
  await expect(firstCells).not.toHaveCount(0)
  for (const text of await firstCells.allInnerTexts()) {
    expect(text.trim()).toBe(FILTER_LINE_B)
  }

  // (c) Filter aktif terbawa ke tautan ekspor.
  await expect(csvLink).toHaveAttribute('href', /[?&]production_line_id=[0-9a-f-]{36}(&|$)/)
  const href = await csvLink.getAttribute('href')
  expect(href).toContain(`date_from=${FILTER_RECORD_DATE}`)

  // ...dan CSV yang dihasilkannya memang tersaring: kolom pertama
  // "Production Line", baris line B ada, baris line A tidak.
  const response = await page.request.get(href!, { headers: await statefulHeaders(page) })
  expect(response.ok(), `ekspor CSV gagal (${response.status()})`).toBe(true)

  const body = (await response.text()).replace(/^﻿/, '')
  const [header, ...lines] = body.split(/\r?\n/).filter((line) => line.trim() !== '')

  expect(header.split(/[,;]/)[0].replace(/"/g, '').trim()).toBe('Production Line')
  expect(body).toContain(idB)
  expect(body).not.toContain(idA)
  for (const line of lines) {
    expect(line.split(/[,;]/)[0].replace(/"/g, '').trim()).toBe(FILTER_LINE_B)
  }
}
