<script setup lang="ts">
/**
 * BusyLabel — isi tombol yang sedang menjalankan aksi lambat (audit loading
 * state 2026-10-05): spinner kecil + label sibuk ("Menyimpan…",
 * "Menyinkronkan…", "Mengekspor…") menggantikan label biasa selama `busy`.
 *
 * Komponen ini HANYA isi tombol. Tombol induk tetap memegang `:disabled` dan
 * `:aria-busy` serta penjaga di handler-nya (abaikan klik kedua selama aksi
 * berjalan) — itu yang menjamin tepat satu simpan/sinkron/ekspor.
 *
 * Spinner baru tampak setelah 150 ms (animasi CSS, sama dengan
 * LoadingState) supaya simpan lokal yang cepat tidak berkedip. Teks tombol
 * (textContent) tetap persis label / label sibuk — tanpa spasi tambahan —
 * agar selektor uji berbasis nama tombol tidak berubah.
 *
 * `iconOnly` — untuk tombol sempit yang berbagi satu baris (footer Form:
 * Back · Pause · Clear · Simpan di 390px). Label sibuk yang lebih panjang
 * dulu melebarkan tombolnya dan mendorong Simpan ke baris baru. Dalam mode
 * ini lebar tombol DIKUNCI oleh label biasa (dirender lewat `::before`
 * sehingga tidak ikut textContent), label itu tetap tampak selama 150 ms
 * pertama, lalu digantikan spinner di tengah; label sibuk tetap ada sebagai
 * teks tersembunyi-visual untuk pembaca layar dan uji.
 */
import LoadingSpinner from './LoadingSpinner.vue'

withDefaults(
  defineProps<{
    busy: boolean
    label: string
    busyLabel?: string
    iconOnly?: boolean
  }>(),
  { busyLabel: 'Memproses…', iconOnly: false },
)
</script>

<template>
  <span v-if="iconOnly && busy" class="busy-label busy-label--icon-only" :data-label="label"><span class="busy-label-spinner busy-label-spinner--centered"><LoadingSpinner :size="18" /></span><span class="busy-label-sr">{{ busyLabel }}</span></span>
  <span v-else class="busy-label"><span v-if="busy" class="busy-label-spinner"><LoadingSpinner :size="16" /></span><span class="busy-label-text">{{ busy ? busyLabel : label }}</span></span>
</template>

<style scoped>
.busy-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  max-width: 100%;
  min-width: 0;
  vertical-align: middle;
}

.busy-label-spinner {
  display: inline-flex;
  flex-shrink: 0;
  animation: busy-label-reveal 120ms ease-out 150ms both;
}

.busy-label-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Mode iconOnly: label biasa memegang lebar, spinner menimpanya di tengah. */
.busy-label--icon-only {
  position: relative;
}

.busy-label--icon-only::before {
  content: attr(data-label);
  white-space: nowrap;
  animation: busy-label-conceal 0s linear 150ms both;
}

.busy-label-spinner--centered {
  position: absolute;
  inset: 0;
  align-items: center;
  justify-content: center;
}

.busy-label-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

@keyframes busy-label-reveal {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes busy-label-conceal {
  to {
    visibility: hidden;
  }
}

@media (prefers-reduced-motion: reduce) {
  .busy-label-spinner {
    animation-duration: 0s;
  }
}
</style>
