<script setup lang="ts">
/**
 * FilterDateInput — filter tanggal bersama (audit desain filter 2026-10-05):
 * `<input type="date">` asli (pemilih tanggal OS + ketik manual tetap ada)
 * ditambah dua pintasan bersegmen, "Hari ini" dan "Semua".
 *
 * Pintasan hanya menulis nilai yang SAMA yang sebelumnya harus dicapai
 * pengguna lewat pemilih tanggal: "Hari ini" = tanggal lokal hari ini
 * (YYYY-MM-DD, sama dengan default yang dipasang layar induk saat dibuka),
 * "Semua" = string kosong (= tanpa filter tanggal). Semantik filter tidak
 * berubah; sebelumnya mengosongkan tanggal di Android berarti membuka
 * pemilih lalu mencari tombol "Hapus", dan kembali ke hari ini berarti
 * menggulir kalender.
 *
 * `testId` dipasang langsung pada <input type="date"> — selector lama
 * (`date-filter` / `date-filter-input`) tetap menunjuk input yang sama.
 */
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    testId?: string
  }>(),
  {
    label: 'Tanggal',
    testId: undefined,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const inputId = `filter-date-${useId()}`

function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

const isToday = computed(() => props.modelValue !== '' && props.modelValue === todayLocalDateString())
const isAll = computed(() => props.modelValue === '')

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function pickToday(): void {
  emit('update:modelValue', todayLocalDateString())
}

function pickAll(): void {
  emit('update:modelValue', '')
}
</script>

<template>
  <div class="filter-date">
    <label class="filter-date-label" :for="inputId">{{ label }}</label>
    <div class="filter-date-row">
      <input
        :id="inputId"
        type="date"
        class="filter-date-input"
        :class="{ 'filter-date-input--empty': isAll }"
        :value="modelValue"
        :data-testid="testId"
        @input="onInput"
      />
      <div class="filter-date-quick" role="group" :aria-label="`Pintasan ${label.toLowerCase()}`">
        <button
          type="button"
          class="filter-date-chip"
          :class="{ 'filter-date-chip--active': isToday }"
          :aria-pressed="isToday"
          data-testid="date-quick-today"
          @click="pickToday"
        >
          Hari ini
        </button>
        <button
          type="button"
          class="filter-date-chip"
          :class="{ 'filter-date-chip--active': isAll }"
          :aria-pressed="isAll"
          data-testid="date-quick-all"
          @click="pickAll"
        >
          Semua
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.filter-date {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.filter-date-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
}

.filter-date-row {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: 8px;
  min-width: 0;
}

.filter-date-input {
  /* Lebar minimum agar "dd/mm/yyyy" + ikon kalender tidak terpotong; bila
     baris tak cukup (≈360px), pintasan turun ke baris sendiri dan melebar
     penuh — tidak pernah memotong tanggal atau menggeser halaman. */
  flex: 10 1 140px;
  min-width: 140px;
  min-height: 44px;
  padding: 0 10px 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background-color: #ffffff;
  color: #1f2937;
  font-size: 15px;
  font-family: inherit;
  box-sizing: border-box;
}

/* Tanpa tanggal = semua tanggal: teks "dd/mm/yyyy" bawaan dibuat redup
   supaya tidak terbaca sebagai nilai yang sedang dipakai. */
.filter-date-input--empty {
  color: #9ca3af;
}

.filter-date-input:focus {
  outline: none;
  border-color: #249360;
  box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
}

.filter-date-quick {
  display: flex;
  flex: 1 0 auto;
  padding: 0;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  border-radius: 10px;
  background-color: #ffffff;
  box-sizing: border-box;
}

.filter-date-chip {
  /* 42px + border wadah 2px = sasaran sentuh 44px. */
  flex: 1 0 auto;
  min-height: 42px;
  padding: 0 10px;
  border: none;
  border-radius: 9px;
  background: transparent;
  color: #4b5563;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  white-space: nowrap;
  cursor: pointer;
}

.filter-date-chip--active {
  background-color: #e8f5ee;
  /* Cincin putih di dalam = pil yang tampak "masuk" ke wadah bersegmen. */
  box-shadow: inset 0 0 0 3px #ffffff;
  color: #1a6f48;
}

.filter-date-chip:focus-visible {
  outline: 2px solid #249360;
  outline-offset: 1px;
}
</style>
