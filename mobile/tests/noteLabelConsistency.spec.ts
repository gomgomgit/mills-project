/**
 * noteLabelConsistency.spec.ts — kolom catatan di SEMUA form stasiun dan
 * detail Data Preview harus berlabel "Catatan" (audit 2026-10-04: KETUJUH
 * BELAS form stasiun yang punya kolom catatan — termasuk Grading; Weighbridge
 * tidak punya kolom ini — masih berlabel "Note" berbahasa Inggris, sementara
 * 13 detail Data Preview sudah "Catatan" dan 4 sisanya — CPO Dispatch,
 * Kernel Dispatch, Solid Waste Disposal, Sterilizer — menulis "Note:").
 *
 * Sengaja membaca sumber SFC (bukan mount 36 layar satu per satu): yang
 * diuji adalah teks label yang dirender apa adanya dari template, dan id
 * input `field-note` yang dipakai e2e harus tetap stabil — FormField
 * menurunkan id dari label bila `id` tidak diisi, jadi mengganti label
 * tanpa `id` eksplisit akan diam-diam mengubah id menjadi `field-catatan`.
 */
import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const VIEWS_DIR = join(__dirname, '..', 'src', 'views')

function templateOf(file: string): string {
  const source = readFileSync(join(VIEWS_DIR, file), 'utf8')
  const start = source.indexOf('<template>')
  const end = source.lastIndexOf('</template>')
  return source.slice(start, end)
}

const formViews = readdirSync(VIEWS_DIR).filter((f) => /^Form.+View\.vue$/.test(f))
const previewViews = readdirSync(VIEWS_DIR).filter((f) => /^DataPreview.+View\.vue$/.test(f))

describe('label kolom catatan konsisten "Catatan"', () => {
  it.each(formViews.filter((f) => templateOf(f).includes('v-model="form.note"')))(
    '%s: FormField catatan berlabel "Catatan" dengan id field-note',
    (file) => {
      const field = templateOf(file).match(/<FormField[^>]*v-model="form\.note"[^>]*>/)?.[0] ?? ''

      expect(field).toContain('label="Catatan"')
      expect(field).toContain('id="field-note"')
    },
  )

  it.each([...formViews, ...previewViews])('%s: tidak ada label "Note" berbahasa Inggris', (file) => {
    const template = templateOf(file)

    expect(template).not.toMatch(/label="Note"/)
    expect(template).not.toMatch(/>\s*Note:?\s*</)
  })
})
