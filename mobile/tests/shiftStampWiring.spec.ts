import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * shiftStampWiring.spec.ts — ke-18 `*RecordRepo.saveDraft()` MENYETEMPEL
 * `shift_id`, masing-masing dengan nama tabelnya sendiri.
 *
 * ── MENGAPA MEMBACA SUMBER, BUKAN MENJALANKAN KODE ──────────────────────
 *
 * tests/setup/shiftRepoStub.ts men-stub `@/services/shiftRepo` di seluruh
 * suite, supaya dua pembacaan baru di dalam `saveDraft()` tidak menggeser
 * antrian `mockResolvedValueOnce` di 20 spec repo. Akibatnya tak satu pun
 * spec repo menjalankan stempel yang sebenarnya — dan itu memang pilihan
 * sadar, selama ada yang menjaga penyambungannya. Berkas ini penjaganya.
 *
 * Dua kelas kesalahan yang ditutupnya, dan keduanya TIDAK TERLIHAT dari spec
 * repo mana pun:
 *
 *   1. SEBUAH REPO LUPA MEMANGGILNYA. Record stasiun itu akan lahir tanpa
 *      shift selamanya, diam-diam, sementara 17 stasiun lain baik-baik saja
 *      dan seluruh suite tetap hijau.
 *   2. NAMA TABEL TERSALIN DARI REPO TETANGGA. `UPDATE sterilizer_record`
 *      yang dijalankan dari repo Threshing akan menulis ke baris yang salah
 *      — atau ke baris yang tidak ada, yang di SQLite bukan galat melainkan
 *      nol baris terpengaruh. Tidak ada yang akan melempar.
 *
 * Pemetaan di bawah ditulis tangan DENGAN SENGAJA: ia adalah pernyataan
 * terpisah tentang pasangan yang benar. Menurunkannya dari nama berkas akan
 * membuatnya menyetujui apa pun yang kebetulan ada di sana, termasuk salah
 * ketik yang konsisten di kedua sisi.
 */

const SERVICES_DIR = join(process.cwd(), 'src/services')

const TABEL_PER_REPO: Record<string, string> = {
  'boilerRoomRecordRepo.ts': 'boiler_room_record',
  'cagesTrackRecordRepo.ts': 'cages_track_record',
  'clarificationRecordRepo.ts': 'clarification_record',
  'cpoDispatchRecordRepo.ts': 'cpo_dispatch_record',
  'depricarpingRecordRepo.ts': 'depricarping_record',
  'effluentPlantRecordRepo.ts': 'effluent_plant_record',
  'engineRoomRecordRepo.ts': 'engine_room_record',
  'gradingRecordRepo.ts': 'grading_record',
  'kernelDispatchRecordRepo.ts': 'kernel_dispatch_record',
  'kernelPlantRecordRepo.ts': 'kernel_plant_record',
  'pressingRecordRepo.ts': 'pressing_record',
  'processQualityControlRecordRepo.ts': 'process_quality_control_record',
  'processWaterRecordRepo.ts': 'process_water_record',
  'solidWasteDisposalRecordRepo.ts': 'solid_waste_disposal_record',
  'sterilizerRecordRepo.ts': 'sterilizer_record',
  'storageTankRecordRepo.ts': 'storage_tank_record',
  'threshingRecordRepo.ts': 'threshing_record',
  'weighbridgeRecordRepo.ts': 'weighbridge_record',
}

describe('penandaan shift_id — penyambungan ke-18 repo', () => {
  it('daftar repo di disk persis ke-18 yang dipetakan di sini', () => {
    const diDisk = readdirSync(SERVICES_DIR)
      .filter((file) => file.endsWith('RecordRepo.ts'))
      .sort()

    // Memaku KEDUA arah: repo baru yang lupa didaftarkan di sini akan
    // memerah, dan entri yang tertinggal setelah sebuah repo dihapus juga.
    expect(diDisk).toEqual(Object.keys(TABEL_PER_REPO).sort())
    expect(diDisk).toHaveLength(18)
  })

  it.each(Object.entries(TABEL_PER_REPO))(
    '%s menyetempel shift_id dengan tabelnya sendiri',
    (file, table) => {
      const source = readFileSync(join(SERVICES_DIR, file), 'utf-8')

      expect(source).toContain(`await stampShiftOnRecord('${table}', recordId)`)
      expect(source).toContain("import { stampShiftOnRecord } from '@/services/shiftRepo'")

      // NAMA TABEL MILIK ORANG LAIN TIDAK BOLEH MUNCUL di panggilan stempel
      // repo ini — inilah yang menangkap salinan dari repo tetangga.
      for (const lain of Object.values(TABEL_PER_REPO)) {
        if (lain === table) continue
        expect(source).not.toContain(`stampShiftOnRecord('${lain}'`)
      }
    },
  )

  it('stempel berada DI DALAM saveDraft(), bukan di jalur lain', () => {
    for (const [file, table] of Object.entries(TABEL_PER_REPO)) {
      const source = readFileSync(join(SERVICES_DIR, file), 'utf-8')

      const mulai = source.indexOf('export async function saveDraft(')
      const panggilan = source.indexOf(`await stampShiftOnRecord('${table}', recordId)`)
      const fungsiBerikutnya = source.indexOf('\nexport ', mulai + 10)

      expect(mulai, `${file}: saveDraft() tidak ditemukan`).toBeGreaterThan(-1)
      expect(panggilan, `${file}: stempel di luar saveDraft()`).toBeGreaterThan(mulai)

      if (fungsiBerikutnya > -1) {
        expect(panggilan, `${file}: stempel jatuh setelah saveDraft() berakhir`).toBeLessThan(fungsiBerikutnya)
      }
    }
  })

  /**
   * Stempel HARUS ikut terkirim saat sinkronisasi. Tanpa baris ini ia hidup
   * di perangkat saja: `saveDraft()` menulisnya, server tidak pernah
   * menerimanya, dan kolom `shift_id` di server tetap null selamanya —
   * sementara setiap test perangkat tetap hijau.
   */
  it('keempat payload sinkronisasi mengirim shift_id', () => {
    const source = readFileSync(join(SERVICES_DIR, 'syncService.ts'), 'utf-8')

    const payloadLines = source.split('\n').filter((line) => line.includes('production_line_id: productionLineId,'))
    const shiftLines = source.split('\n').filter((line) => line.includes('shift_id: row.shift_id ?? null,'))

    expect(payloadLines).toHaveLength(4)
    expect(shiftLines).toHaveLength(4)
  })
})
