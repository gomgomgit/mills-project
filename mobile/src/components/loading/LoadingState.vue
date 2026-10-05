<script setup lang="ts">
/**
 * LoadingState — blok "sedang memuat" bersama untuk semua layar mobile
 * (audit loading state 2026-10-05). Menggantikan `<p class="status-text">
 * Memuat…</p>` polos yang tumbuh terpisah di tiap layar.
 *
 * Isi: spinner + teks (slot default, atau prop `label`), lalu — sesuai
 * `variant` — kerangka (skeleton) yang meniru bentuk isi yang akan datang
 * sehingga tata letak tidak melompat ketika data tiba:
 *  - `text`  : hanya baris spinner + teks (default).
 *  - `list`  : baris-baris kartu (daftar draft / daftar record).
 *  - `form`  : pasangan label + kolom isian (Form / detail Data Preview).
 *  - `grid`  : petak 2 kolom (Daftar Stasiun / Pilih Stasiun laporan).
 *  - `card`  : satu kartu ringkasan besar (ringkasan Laporan).
 *
 * Anti-kedip: blok ini SUDAH ada di DOM (role="status", aria-busy) sejak
 * awal, tetapi baru tampak setelah `delay` ms (default 150) lewat animasi
 * CSS — pembacaan SQLite lokal yang selesai dalam beberapa milidetik tidak
 * pernah menampilkan kilatan spinner. Tidak ada timer JS yang harus
 * dibersihkan, dan teksnya tetap dapat dibaca uji/pembaca layar sejak awal.
 */
import LoadingSpinner from './LoadingSpinner.vue'

withDefaults(
  defineProps<{
    label?: string
    variant?: 'text' | 'list' | 'form' | 'grid' | 'card'
    /** Jumlah butir kerangka untuk variant list/form/grid. */
    rows?: number
    /** Jeda (ms) sebelum indikator tampak — mencegah kedip pada baca cepat. */
    delay?: number
    /** Versi ringkas (teks 13px, tanpa jarak vertikal) untuk sisipan inline. */
    compact?: boolean
    testId?: string
  }>(),
  {
    label: 'Memuat…',
    variant: 'text',
    rows: 3,
    delay: 150,
    compact: false,
    testId: 'loading-state',
  },
)
</script>

<template>
  <div
    class="loading-state"
    :class="{ 'loading-state--compact': compact }"
    role="status"
    aria-live="polite"
    aria-busy="true"
    :data-testid="testId"
    :style="{ '--loading-delay': `${delay}ms` }"
  >
    <div class="loading-state-line">
      <LoadingSpinner :size="compact ? 14 : 18" class="loading-state-spinner" />
      <span class="loading-state-label"><slot>{{ label }}</slot></span>
    </div>

    <div v-if="variant !== 'text'" class="loading-skeleton" :class="`loading-skeleton--${variant}`" aria-hidden="true">
      <template v-if="variant === 'form'">
        <div v-for="n in rows" :key="n" class="loading-skeleton-field">
          <span class="loading-skeleton-block loading-skeleton-block--label" />
          <span class="loading-skeleton-block loading-skeleton-block--input" />
        </div>
      </template>
      <template v-else-if="variant === 'card'">
        <span class="loading-skeleton-block loading-skeleton-block--card" />
      </template>
      <template v-else>
        <span v-for="n in rows" :key="n" class="loading-skeleton-block" :class="`loading-skeleton-block--${variant}`" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.loading-state {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  animation: loading-state-reveal 120ms ease-out var(--loading-delay, 150ms) both;
}

.loading-state-line {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 24px;
  color: #249360;
}

.loading-state-label {
  min-width: 0;
  font-size: 14px;
  color: #6b7280;
}

.loading-state--compact {
  gap: 0;
}

.loading-state--compact .loading-state-line {
  gap: 8px;
  min-height: 20px;
}

.loading-state--compact .loading-state-label {
  font-size: 13px;
}

.loading-skeleton {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.loading-skeleton--grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.loading-skeleton-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.loading-skeleton-block {
  display: block;
  border-radius: 8px;
  background: linear-gradient(90deg, #f3f4f6 0%, #e5e7eb 50%, #f3f4f6 100%);
  background-size: 200% 100%;
  animation: loading-skeleton-shimmer 1.4s ease-in-out infinite;
}

.loading-skeleton-block--list {
  height: 56px;
  border-radius: 12px;
}

.loading-skeleton-block--label {
  width: 40%;
  height: 14px;
}

.loading-skeleton-block--input {
  height: 44px;
}

.loading-skeleton-block--grid {
  height: 96px;
  border-radius: 12px;
}

.loading-skeleton-block--card {
  height: 160px;
  border-radius: 12px;
}

@keyframes loading-state-reveal {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes loading-skeleton-shimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: -100% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .loading-state {
    animation-duration: 0s;
  }

  .loading-skeleton-block {
    animation: none;
  }
}
</style>
