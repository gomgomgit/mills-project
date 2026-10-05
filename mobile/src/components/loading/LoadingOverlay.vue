<script setup lang="ts">
/**
 * LoadingOverlay — lapisan penuh layar untuk aksi global yang menunggu
 * jaringan tanpa tombol pemicu yang tetap terlihat (audit loading state
 * 2026-10-05). Dipakai App.vue untuk Logout: menu navigasi sudah tertutup
 * saat POST /api/logout berjalan, sehingga tanpa lapisan ini layar tampak
 * diam beberapa detik dan pengguna mengetuk lagi.
 *
 * Lapisan langsung menangkap ketukan sejak dirender (mencegah aksi ganda),
 * tetapi tirai + kartunya baru tampak setelah `delay` ms agar logout yang
 * cepat tidak berkedip. Kartu diletakkan di tengah dengan memperhitungkan
 * safe-area perangkat; z-index di atas jam mengambang dan bubble AI.
 */
import LoadingSpinner from './LoadingSpinner.vue'

withDefaults(defineProps<{ label?: string; delay?: number }>(), { label: 'Memproses…', delay: 150 })
</script>

<template>
  <Teleport to="body">
    <div class="loading-overlay" data-testid="loading-overlay" :style="{ '--loading-delay': `${delay}ms` }">
      <div class="loading-overlay-card" role="status" aria-live="polite" aria-busy="true">
        <LoadingSpinner :size="24" class="loading-overlay-spinner" />
        <span class="loading-overlay-label">{{ label }}</span>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: env(safe-area-inset-top, 0px) 16px env(safe-area-inset-bottom, 0px);
  background-color: rgba(17, 24, 39, 0.35);
  box-sizing: border-box;
  animation: loading-overlay-reveal 120ms ease-out var(--loading-delay, 150ms) both;
}

.loading-overlay-card {
  display: flex;
  align-items: center;
  gap: 12px;
  max-width: 100%;
  padding: 16px 20px;
  border-radius: 12px;
  background-color: #ffffff;
  box-shadow: 0 10px 30px rgba(17, 24, 39, 0.18);
  color: #249360;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.loading-overlay-label {
  min-width: 0;
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

@keyframes loading-overlay-reveal {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .loading-overlay {
    animation-duration: 0s;
  }
}
</style>
