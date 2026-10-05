/**
 * Ruang aman bawah (px) yang harus disisakan setiap layar agar elemen
 * mengambang global tidak menutupi tombol/teks penting (audit 2026-10-04).
 *
 * Sejak audit 2026-10-05 ruang ini juga tinggi DOK bawah (App.vue,
 * `.floating-dock`): pita buram selebar layar tempat bubble AI dan jam
 * duduk. Padding-bottom saja hanya menjamin UJUNG gulir — di posisi awal
 * layar (belum digulir) bubble tetap menimpa kontrol di pojok kanan bawah
 * (Clear/Simpan Form Sterilizer di 390px, "+ Tambah Baris" di 360px).
 * Dengan dok, konten tidak pernah tampak/terjangkau di bawah elemen
 * mengambang pada posisi gulir mana pun.
 *
 * Angka mengikuti posisi tetap di komponennya — ubah bersamaan bila posisi
 * di sana diubah:
 *  - AiAssistantBubble.vue: `bottom: 16px`, tinggi 48px → tepi atas 64px
 *    dari bawah layar.
 *  - FloatingClock.vue: `bottom: 16px`, tinggi ±34px (padding 8px + teks
 *    14px) → tepi atas ±50px. Saat bubble juga aktif, jam duduk DI SAMPING
 *    kiri bubble (`bottom: 23px`, tengahnya sejajar bubble) — tidak lagi
 *    ditumpuk, supaya dok tidak setinggi dua elemen.
 * Ditambah jarak 12px agar konten tidak menempel ke elemen mengambang.
 */
export const FLOATING_EDGE_PX = 16
export const AI_BUBBLE_SIZE_PX = 48
export const AI_BUBBLE_TOP_FROM_BOTTOM_PX = FLOATING_EDGE_PX + AI_BUBBLE_SIZE_PX
export const FLOATING_CLOCK_TOP_FROM_BOTTOM_PX = FLOATING_EDGE_PX + 34
export const FLOATING_SAFE_GAP_PX = 12

export function floatingSafeBottomPx(state: { bubbleEnabled: boolean; clockEnabled: boolean }): number {
  if (state.bubbleEnabled) return AI_BUBBLE_TOP_FROM_BOTTOM_PX + FLOATING_SAFE_GAP_PX
  if (state.clockEnabled) return FLOATING_CLOCK_TOP_FROM_BOTTOM_PX + FLOATING_SAFE_GAP_PX
  return 0
}
