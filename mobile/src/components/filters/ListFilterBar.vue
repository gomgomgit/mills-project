<script setup lang="ts">
/**
 * ListFilterBar — filter daftar record lokal yang dipakai SEMUA 18 layar
 * Data Preview (audit desain filter 2026-10-05): FilterPanel berisi
 * FilterDateInput (tanggal + pintasan Hari ini/Semua) dan FilterSearchInput,
 * plus ringkasan "N dari M data" dan satu tombol Reset Filter.
 *
 * Menggantikan dua markup berbeda yang tumbuh terpisah: `filter-bar` (4
 * layar: tombol Reset di bar, label "Cari (X ID)") dan `filter-row` (14
 * layar: grid 2 kolom, Reset hanya muncul di kotak "tidak ada data").
 *
 * Komponen ini TIDAK menyaring apa pun. Layar induk tetap memegang
 * `dateFilter`/`searchFilter` (v-model:date / v-model:search), default
 * tanggal hari ini, computed `filteredRecords`, dan `onResetFilter()` —
 * sehingga semantik tiap layar (kolom yang dicari, slice tanggal) tidak
 * tersentuh. Testid input diteruskan apa adanya agar spec lama tetap jalan.
 */
import { computed } from 'vue'
import FilterPanel from './FilterPanel.vue'
import FilterDateInput from './FilterDateInput.vue'
import FilterSearchInput from './FilterSearchInput.vue'

const props = withDefaults(
  defineProps<{
    date: string
    search: string
    searchPlaceholder?: string
    dateTestId?: string
    searchTestId?: string
    /** Jumlah record setelah filter. */
    filteredCount?: number
    /** Jumlah seluruh record lokal (sebelum filter). */
    totalCount?: number
    /** Ringkasan jumlah hanya bermakna setelah daftar selesai dimuat. */
    showCount?: boolean
    /** Ada filter aktif — menampilkan tombol Reset Filter. */
    active?: boolean
    ariaLabel?: string
  }>(),
  {
    searchPlaceholder: '',
    dateTestId: 'date-filter-input',
    searchTestId: 'search-filter-input',
    filteredCount: 0,
    totalCount: 0,
    showCount: false,
    active: false,
    ariaLabel: 'Filter data',
  },
)

const emit = defineEmits<{
  'update:date': [value: string]
  'update:search': [value: string]
  reset: []
}>()

const resultText = computed(() => {
  if (!props.showCount || props.totalCount === 0) {
    return ''
  }
  if (props.filteredCount === props.totalCount) {
    return `${props.totalCount} data`
  }
  return `${props.filteredCount} dari ${props.totalCount} data`
})
</script>

<template>
  <FilterPanel :aria-label="ariaLabel" :result-text="resultText" :show-reset="active" @reset="emit('reset')">
    <FilterDateInput :model-value="date" :test-id="dateTestId" @update:model-value="emit('update:date', $event)" />
    <FilterSearchInput
      :model-value="search"
      :placeholder="searchPlaceholder"
      :test-id="searchTestId"
      @update:model-value="emit('update:search', $event)"
    />
  </FilterPanel>
</template>
