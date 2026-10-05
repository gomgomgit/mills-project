<script setup lang="ts">
/**
 * FilterPanel — wadah bersama semua filter di aplikasi mobile (audit desain
 * filter 2026-10-05). Sebelumnya tiap layar menata filternya sendiri: dua
 * gaya berbeda di 18 Data Preview (filter-bar flex vs filter-row grid,
 * tombol Reset kadang di bar, kadang hanya di kotak kosong), dan select
 * polos tanpa pengelompokan di 5 layar Laporan. Panel ini memberi satu
 * bentuk yang sama: kartu abu-abu lembut berisi kendali (slot default),
 * baris chip cakupan opsional (slot `chips`), dan baris ringkasan berisi
 * jumlah hasil + tombol Reset Filter.
 *
 * Murni presentasional: panel tidak tahu apa pun tentang semantik filter.
 * Layar induk tetap memegang state, query lokal, dan default (mis. tanggal
 * hari ini) — panel hanya memancarkan `reset` saat tombolnya ditekan.
 *
 * `resetTestId` default `reset-filter-button` — testid yang sudah dipakai
 * vitest & Playwright di seluruh Data Preview. Tombol hanya dirender sekali
 * per layar (di sini), sehingga `getByTestId` tetap tunggal (strict mode).
 */
import { Comment, Fragment, useSlots, type VNode } from 'vue'

withDefaults(
  defineProps<{
    /** Teks ringkasan hasil, mis. "2 dari 5 data". Kosong = tidak tampil. */
    resultText?: string
    /** Tampilkan tombol Reset Filter (biasanya = ada filter aktif). */
    showReset?: boolean
    resetLabel?: string
    resetTestId?: string
    /** aria-label untuk region filter. */
    ariaLabel?: string
  }>(),
  {
    resultText: '',
    showReset: false,
    resetLabel: 'Reset Filter',
    resetTestId: 'reset-filter-button',
    ariaLabel: 'Filter',
  },
)

const emit = defineEmits<{ reset: [] }>()

function onReset(): void {
  emit('reset')
}

const slots = useSlots()

// Chip cakupan sering bersyarat (v-if) — slot `chips` yang terisi hanya
// komentar `<!--v-if-->` tidak boleh menghasilkan baris kosong ber-gap.
function isRenderable(nodes: VNode[]): boolean {
  return nodes.some((node) => {
    if (node.type === Comment) return false
    if (node.type === Fragment) return isRenderable((node.children as VNode[]) ?? [])
    return true
  })
}

function hasChips(): boolean {
  return slots.chips ? isRenderable(slots.chips()) : false
}
</script>

<template>
  <section class="filter-panel" role="search" :aria-label="ariaLabel">
    <div class="filter-panel-controls">
      <slot />
    </div>

    <div v-if="hasChips()" class="filter-panel-chips">
      <slot name="chips" />
    </div>

    <div v-if="resultText || showReset" class="filter-panel-summary">
      <span v-if="resultText" class="filter-panel-result" role="status" aria-live="polite" data-testid="filter-result-count">
        {{ resultText }}
      </span>
      <button
        v-if="showReset"
        type="button"
        class="filter-panel-reset"
        :data-testid="resetTestId"
        @click="onReset"
      >
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 12a9 9 0 1 0 3-6.7" />
          <polyline points="3 4 3 9 8 9" />
        </svg>
        {{ resetLabel }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.filter-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background-color: #f9fafb;
  box-sizing: border-box;
}

.filter-panel-controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.filter-panel-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 0;
}

.filter-panel-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 36px;
  margin: 0 -12px -12px;
  padding: 4px 4px 4px 12px;
  border-top: 1px solid #e5e7eb;
}

.filter-panel-result {
  min-width: 0;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
}

.filter-panel-reset {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  margin-left: auto;
  padding: 0 12px;
  border: none;
  border-radius: 8px;
  background-color: transparent;
  color: #249360;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  white-space: nowrap;
  cursor: pointer;
}

.filter-panel-reset:active {
  background-color: #e8f5ee;
}

.filter-panel-reset:focus-visible {
  outline: 2px solid #249360;
  outline-offset: 1px;
}
</style>
