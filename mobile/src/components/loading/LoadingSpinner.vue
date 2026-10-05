<script setup lang="ts">
/**
 * LoadingSpinner — cincin berputar kecil yang dipakai bersama oleh semua
 * indikator loading mobile (audit loading state 2026-10-05): LoadingState,
 * BusyLabel (isi tombol), dan LoadingOverlay.
 *
 * Murni dekoratif (`aria-hidden`): makna "sedang memuat" selalu dibawa oleh
 * teks pendampingnya (role="status" / label tombol), jadi pembaca layar tidak
 * mendengar dua kali. Warna mengikuti `currentColor` — hijau #249360 pada
 * latar putih, putih pada tombol primer — tanpa prop warna tambahan.
 *
 * `prefers-reduced-motion: reduce` menghentikan putaran; busur statis plus
 * teks tetap cukup sebagai tanda.
 */
withDefaults(defineProps<{ size?: number }>(), { size: 16 })
</script>

<template>
  <svg
    class="loading-spinner"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
    focusable="false"
    data-testid="loading-spinner"
  >
    <circle class="loading-spinner-track" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" />
    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
  </svg>
</template>

<style scoped>
.loading-spinner {
  flex-shrink: 0;
  animation: loading-spinner-rotate 0.8s linear infinite;
}

.loading-spinner-track {
  opacity: 0.25;
}

@keyframes loading-spinner-rotate {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .loading-spinner {
    animation: none;
  }
}
</style>
