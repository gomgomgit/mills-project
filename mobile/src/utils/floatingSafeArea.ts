/**
 * Ruang aman bawah (px) yang harus disisakan setiap layar agar elemen
 * mengambang global tidak menutupi tombol/teks penting (audit 2026-10-04).
 *
 * Angka mengikuti posisi tetap di komponennya — ubah bersamaan bila posisi
 * di sana diubah:
 *  - AiAssistantBubble.vue: `bottom: 58px`, tinggi 48px → tepi atas 106px
 *    dari bawah layar.
 *  - FloatingClock.vue: `bottom: 16px`, tinggi ±34px (padding 8px + teks
 *    14px) → tepi atas ±50px dari bawah layar.
 * Ditambah jarak 12px agar footer tidak menempel ke elemen mengambang.
 */
export const AI_BUBBLE_TOP_FROM_BOTTOM_PX = 58 + 48
export const FLOATING_CLOCK_TOP_FROM_BOTTOM_PX = 16 + 34
export const FLOATING_SAFE_GAP_PX = 12

export function floatingSafeBottomPx(state: { bubbleEnabled: boolean; clockEnabled: boolean }): number {
  if (state.bubbleEnabled) return AI_BUBBLE_TOP_FROM_BOTTOM_PX + FLOATING_SAFE_GAP_PX
  if (state.clockEnabled) return FLOATING_CLOCK_TOP_FROM_BOTTOM_PX + FLOATING_SAFE_GAP_PX
  return 0
}
