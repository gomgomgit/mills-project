<script setup lang="ts">
/**
 * FilterSearchInput — kolom cari bersama (audit desain filter 2026-10-05):
 * ikon kaca pembesar di kiri, tombol hapus (×) di kanan saat ada isi.
 *
 * Tetap `<input type="text">` biasa (bukan type="search") supaya perilaku
 * `setValue`/`fill` di vitest & Playwright identik dengan sebelumnya dan
 * tidak muncul tombol batal bawaan WebKit yang dobel dengan tombol × ini.
 * `testId` dipasang LANGSUNG pada <input> — selector lama
 * (`search-filter` / `search-filter-input`) tetap menunjuk elemen yang sama
 * jenisnya.
 *
 * Tidak ada logika pencarian di sini: komponen hanya meneruskan teks ke
 * induk lewat v-model; induk yang menyaring data lokalnya.
 */
import { ref, useId } from 'vue'

withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    testId?: string
  }>(),
  {
    label: 'Cari',
    placeholder: '',
    testId: undefined,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const inputEl = ref<HTMLInputElement | null>(null)

const inputId = `filter-search-${useId()}`

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function onClear(): void {
  emit('update:modelValue', '')
  inputEl.value?.focus()
}

</script>

<template>
  <div class="filter-search">
    <label class="filter-search-label" :for="inputId">{{ label }}</label>
    <div class="filter-search-box">
      <svg class="filter-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <input
        :id="inputId"
        ref="inputEl"
        type="text"
        class="filter-search-input"
        :class="{ 'filter-search-input--has-value': modelValue !== '' }"
        autocomplete="off"
        enterkeyhint="search"
        :placeholder="placeholder"
        :value="modelValue"
        :data-testid="testId"
        @input="onInput"
      />
      <button
        v-if="modelValue !== ''"
        type="button"
        class="filter-search-clear"
        aria-label="Hapus kata kunci"
        :data-testid="testId ? `${testId}-clear` : undefined"
        @click="onClear"
      >
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.filter-search {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.filter-search-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
}

.filter-search-box {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
}

.filter-search-icon {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  pointer-events: none;
}

.filter-search-input {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  padding: 0 12px 0 40px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background-color: #ffffff;
  color: #1f2937;
  font-size: 15px;
  font-family: inherit;
  box-sizing: border-box;
}

/* Ruang untuk tombol × hanya disisihkan saat tombolnya ada, supaya
   placeholder panjang ("No. WB Card / Nama Sopir") tidak terpotong di 360px. */
.filter-search-input--has-value {
  padding-right: 44px;
}

.filter-search-input::placeholder {
  color: #9ca3af;
}

.filter-search-input:focus {
  outline: none;
  border-color: #249360;
  box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
}

.filter-search-clear {
  position: absolute;
  right: 0;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
}

.filter-search-clear:active {
  color: #1f2937;
}
</style>
