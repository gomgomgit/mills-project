<script setup lang="ts">
/**
 * FilterSelectField — bingkai bersama untuk `<select>` filter (Mill,
 * Production Line, Periode di 5 layar Laporan; audit desain filter
 * 2026-10-05): label kecil huruf kapital, ikon di kiri, chevron sendiri di
 * kanan, sasaran sentuh 44px.
 *
 * `<select>` aslinya TETAP milik layar induk (lewat slot) — v-model,
 * `@change`, opsi `:value="null"`, dan data-testid tidak berpindah
 * komponen, sehingga alur pemuatan line/periode tidak tersentuh sama
 * sekali. Pemilih asli dipertahankan dengan sengaja: di Android/iOS ia
 * membuka lembar pilihan OS yang sudah akrab dan dapat diakses.
 *
 * Root berkelas `filter-field` — spec Laporan memeriksa
 * `closest('.filter-field')` untuk memastikan Periode adalah kendali
 * filter, bukan teks lepas.
 */
withDefaults(
  defineProps<{
    label: string
    icon?: 'mill' | 'line' | 'period'
  }>(),
  { icon: 'period' },
)
</script>

<template>
  <label class="filter-field">
    <span class="filter-field-label">{{ label }}</span>
    <span class="filter-field-box">
      <span class="filter-field-icon" aria-hidden="true">
        <svg v-if="icon === 'mill'" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 21V10l6 4v-4l6 4v-4l6 4v7z" />
          <line x1="3" y1="21" x2="21" y2="21" />
        </svg>
        <svg v-else-if="icon === 'line'" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <line x1="6" y1="3" x2="6" y2="15" />
          <circle cx="18" cy="6" r="3" />
          <circle cx="6" cy="18" r="3" />
          <path d="M18 9a9 9 0 0 1-9 9" />
        </svg>
        <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <line x1="16" y1="3" x2="16" y2="7" />
          <line x1="8" y1="3" x2="8" y2="7" />
          <line x1="3" y1="11" x2="21" y2="11" />
        </svg>
      </span>
      <slot />
      <span class="filter-field-chevron" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </span>
    </span>
  </label>
</template>

<style scoped>
.filter-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.filter-field-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
}

.filter-field-box {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
}

.filter-field-icon {
  position: absolute;
  left: 8px;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background-color: #e8f5ee;
  color: #249360;
  pointer-events: none;
}

.filter-field-chevron {
  position: absolute;
  right: 12px;
  display: grid;
  place-items: center;
  color: #9ca3af;
  pointer-events: none;
}

.filter-field-box :slotted(select) {
  appearance: none;
  -webkit-appearance: none;
  width: 100%;
  min-width: 0;
  min-height: 48px;
  padding: 0 40px 0 48px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background-color: #ffffff;
  color: #111827;
  font-size: 15px;
  font-weight: 600;
  font-family: inherit;
  text-overflow: ellipsis;
  box-sizing: border-box;
  cursor: pointer;
}

/* Opsi pertama setiap pemilih filter adalah placeholder "Pilih …" (nilai
   null). Selama itu yang terpilih, teksnya diredupkan supaya tidak terbaca
   sebagai nilai yang sedang berlaku. */
.filter-field-box :slotted(select:has(> option:first-child:checked)) {
  color: #6b7280;
  font-weight: 500;
}

.filter-field-box :slotted(select:focus) {
  outline: none;
  border-color: #249360;
  box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
}
</style>
