/**
 * optionLabel.spec.ts — audit 2026-10-05: detail Data Preview mobile harus
 * menampilkan label pilihan yang sama dengan Detail web/ekspor
 * (backend App\Support\Display::OPTION_LABELS), bukan nilai mentah y/on/run.
 */
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { OPTION_LABELS, optionLabel } from '@/utils/optionLabel'

describe('optionLabel', () => {
  it.each([
    ['y', 'Ya'],
    ['n', 'Tidak'],
    ['on', 'On'],
    ['off', 'Off'],
    ['fault', 'Fault'],
    ['run', 'Run'],
    ['stop', 'Stop'],
    ['standby', 'Standby'],
  ])('%s -> %s', (raw, label) => {
    expect(optionLabel(raw)).toBe(label)
  })

  it('kosong -> "-", tak dikenal -> apa adanya (sama dengan Display::option)', () => {
    expect(optionLabel(null)).toBe('-')
    expect(optionLabel(undefined)).toBe('-')
    expect(optionLabel('')).toBe('-')
    expect(optionLabel('open_1_4')).toBe('open_1_4')
  })

  it('peta label identik dengan backend Display::OPTION_LABELS (tidak boleh menyimpang)', () => {
    const php = readFileSync(join(__dirname, '..', '..', 'backend', 'app', 'Support', 'Display.php'), 'utf8')
    const block = php.slice(php.indexOf('OPTION_LABELS = ['), php.indexOf('];', php.indexOf('OPTION_LABELS = [')))
    const backend = Object.fromEntries([...block.matchAll(/'([^']+)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]))
    expect(Object.keys(backend).length).toBeGreaterThan(0)
    expect(OPTION_LABELS).toEqual(backend)
  })
})
