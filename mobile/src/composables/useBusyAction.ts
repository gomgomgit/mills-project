import { ref } from 'vue'

/**
 * useBusyAction — penjaga aksi ganda untuk tombol yang menjalankan
 * baca/tulis lambat (audit loading state 2026-10-05).
 *
 * `run(action)` menolak (mengembalikan `undefined` tanpa memanggil
 * `action`) selama aksi sebelumnya masih berjalan, dan `busy` dipakai
 * template untuk `:disabled`, `:aria-busy`, dan BusyLabel. Penjaga di
 * handler — bukan hanya atribut `disabled` — yang menjamin ketukan ganda
 * tetap menghasilkan tepat satu tulis: dua klik dapat tiba sebelum Vue
 * sempat merender ulang tombolnya.
 *
 * Bila aksi diakhiri navigasi, `await router.push(...)` DI DALAM action
 * agar `busy` bertahan sampai layar berganti — tanpa celah di mana tombol
 * sudah aktif lagi tetapi layar belum pindah.
 */
export function useBusyAction() {
  const busy = ref(false)

  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    if (busy.value) {
      return undefined
    }

    busy.value = true

    try {
      return await action()
    } finally {
      busy.value = false
    }
  }

  return { busy, run }
}
