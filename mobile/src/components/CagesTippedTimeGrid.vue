<script setup lang="ts">
/**
 * CagesTippedTimeGrid — screen-012--form-cages-track /
 * usecase-012--form-cages-track business_logic step 3 ("Allow
 * adding/editing cages_tipped_time rows (cage_number, tipped_time) in a
 * grid/list UI").
 *
 * Screen-012-specific (not intended for cross-screen reuse), mirroring
 * GradingDetailGrid.vue's (screen-011) list-editing UX/structure pattern
 * as closely as sensible rather than reusing that component directly —
 * per this screen's implementation plan, GradingDetailGrid.vue stays
 * grading-specific.
 *
 * v-model-compatible over an array of `CagesTippedTimeFormRow` (same
 * philosophy as FormField.vue / ConfirmDialog.vue / GradingDetailGrid.vue
 * — parent owns the state, this stays a pure presentational element):
 * every edit emits a brand-new array via `update:modelValue` rather than
 * mutating the prop in place, so FormCagesTrackView.vue's dirty-tracking
 * (JSON.stringify snapshot comparison, same pattern as
 * FormGradingView.vue) sees each change.
 *
 * Rows without an `id` are ones added in this editing session (INSERT
 * target); rows with an `id` were loaded from an existing draft (UPDATE
 * target) — cagesTrackRecordRepo.ts's `saveDraft()` upserts on exactly
 * this distinction, so this component deliberately never invents an id
 * for a new row itself.
 *
 * `error` prop surfaces business_logic step 5's distinct "at least one
 * row required" validation message (kept separate from FormField.vue's
 * per-field error styling since it applies to the grid as a whole, not
 * one input) — same pattern as GradingDetailGrid.vue's `error` prop.
 */
import { computed } from 'vue'
import type { CagesTippedTimeFormRow } from '@/services/cagesTrackRecordRepo'

const props = withDefaults(
  defineProps<{
    modelValue: CagesTippedTimeFormRow[]
    error?: string
    disabled?: boolean
  }>(),
  {
    error: undefined,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: CagesTippedTimeFormRow[]]
}>()

const rows = computed(() => props.modelValue)

function updateRow(index: number, patch: Partial<CagesTippedTimeFormRow>): void {
  const next = rows.value.map((row, i) => (i === index ? { ...row, ...patch } : row))
  emit('update:modelValue', next)
}

function onCageNumberInput(index: number, event: Event): void {
  const target = event.target as HTMLInputElement
  updateRow(index, { cage_number: target.value })
}

function onTippedTimeInput(index: number, event: Event): void {
  const target = event.target as HTMLInputElement
  updateRow(index, { tipped_time: target.value })
}

// business_logic step 3 — 'Add' row action. Appends a fresh, id-less row
// (INSERT target once saved) with empty/blank values.
function onAddRow(): void {
  emit('update:modelValue', [...rows.value, { cage_number: '', tipped_time: '' }])
}

function onRemoveRow(index: number): void {
  emit(
    'update:modelValue',
    rows.value.filter((_, i) => i !== index),
  )
}
</script>

<template>
  <div class="cages-tipped-time-grid">
    <div class="cages-tipped-time-header">
      <span class="cages-tipped-time-title">Cages Tipped Time</span>
      <button
        type="button"
        class="cages-tipped-time-add"
        :disabled="disabled"
        data-testid="cages-tipped-time-add"
        @click="onAddRow"
      >
        + Tambah Baris
      </button>
    </div>

    <p v-if="error" class="cages-tipped-time-error" role="alert" data-testid="cages-tipped-time-error">
      {{ error }}
    </p>

    <p v-if="rows.length === 0" class="cages-tipped-time-empty">Belum ada baris cages tipped time.</p>

    <ul v-else class="cages-tipped-time-list" role="list" aria-label="Daftar cages tipped time">
      <li
        v-for="(row, index) in rows"
        :key="row.id ?? `new-${index}`"
        class="cages-tipped-time-row"
        role="listitem"
        data-testid="cages-tipped-time-row"
      >
        <div class="cages-tipped-time-field">
          <label :for="`cages-tipped-time-cage-number-${index}`" class="cages-tipped-time-label">
            No. Cage
          </label>
          <input
            :id="`cages-tipped-time-cage-number-${index}`"
            class="cages-tipped-time-input"
            type="text"
            :value="row.cage_number"
            :disabled="disabled"
            @input="onCageNumberInput(index, $event)"
          />
        </div>

        <div class="cages-tipped-time-field">
          <label :for="`cages-tipped-time-tipped-time-${index}`" class="cages-tipped-time-label">
            Waktu Tipping
          </label>
          <input
            :id="`cages-tipped-time-tipped-time-${index}`"
            class="cages-tipped-time-input"
            type="time"
            :value="row.tipped_time"
            :disabled="disabled"
            @input="onTippedTimeInput(index, $event)"
          />
        </div>

        <button
          type="button"
          class="cages-tipped-time-remove"
          :disabled="disabled"
          aria-label="Hapus baris cages tipped time"
          data-testid="cages-tipped-time-remove"
          @click="onRemoveRow(index)"
        >
          Hapus
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.cages-tipped-time-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-family: 'Inter', sans-serif;
}

.cages-tipped-time-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cages-tipped-time-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.cages-tipped-time-add {
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid #249360;
  border-radius: 8px;
  background-color: #ffffff;
  color: #249360;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-sizing: border-box;
}

.cages-tipped-time-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cages-tipped-time-error {
  margin: 0;
  padding: 8px 12px;
  border-radius: 6px;
  background-color: #fee2e2;
  color: #dc2626;
  font-size: 13px;
}

.cages-tipped-time-empty {
  margin: 0;
  padding: 12px;
  border: 1px dashed #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  font-size: 13px;
  text-align: center;
}

.cages-tipped-time-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cages-tipped-time-row {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  align-items: end;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-sizing: border-box;
}

.cages-tipped-time-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.cages-tipped-time-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}

.cages-tipped-time-input {
  min-height: 44px;
  padding: 0 10px;
  background-color: #edebeb;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 15px;
  font-family: inherit;
  color: #1f2937;
  box-sizing: border-box;
  width: 100%;
}

.cages-tipped-time-input:focus {
  outline: 2px solid #249360;
  outline-offset: 1px;
}

.cages-tipped-time-input:disabled {
  opacity: 0.6;
}

.cages-tipped-time-remove {
  min-height: 44px;
  padding: 0 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #dc2626;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-sizing: border-box;
}

.cages-tipped-time-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
