<script setup lang="ts">
/**
 * GradingDetailGrid — screen-011--form-grading /
 * usecase-011--form-grading business_logic step 3 ("Allow adding/editing
 * grading_detail rows (category, quantity) in a grid/list UI").
 *
 * Screen-011-specific (not intended for cross-screen reuse yet — per this
 * screen's implementation plan, screen-012 will later follow a similar
 * list-editing UX pattern for a different grid, but as its own component,
 * not this one).
 *
 * v-model-compatible over an array of `GradingDetailFormRow` (same
 * philosophy as FormField.vue / ConfirmDialog.vue — parent owns the
 * state, this stays a pure presentational element): every edit emits a
 * brand-new array via `update:modelValue` rather than mutating the prop
 * in place, so FormGradingView.vue's dirty-tracking (JSON.stringify
 * snapshot comparison, same pattern as FormWeighbridgeView.vue) sees each
 * change.
 *
 * Rows without an `id` are ones added in this editing session (INSERT
 * target); rows with an `id` were loaded from an existing draft (UPDATE
 * target) — gradingRecordRepo.ts's `saveDraft()` upserts on exactly this
 * distinction, so this component deliberately never invents an id for a
 * new row itself.
 *
 * `error` prop surfaces business_logic step 5's distinct "at least one
 * row required" validation message (kept separate from FormField.vue's
 * per-field error styling since it applies to the grid as a whole, not
 * one input).
 */
import { computed } from 'vue'
import type { GradingDetailFormRow } from '@/services/gradingRecordRepo'

const props = withDefaults(
  defineProps<{
    modelValue: GradingDetailFormRow[]
    error?: string
    disabled?: boolean
  }>(),
  {
    error: undefined,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: GradingDetailFormRow[]]
}>()

const rows = computed(() => props.modelValue)

function updateRow(index: number, patch: Partial<GradingDetailFormRow>): void {
  const next = rows.value.map((row, i) => (i === index ? { ...row, ...patch } : row))
  emit('update:modelValue', next)
}

function onCategoryInput(index: number, event: Event): void {
  const target = event.target as HTMLInputElement
  updateRow(index, { category: target.value })
}

function onQuantityInput(index: number, event: Event): void {
  const target = event.target as HTMLInputElement
  const raw = target.value
  updateRow(index, { quantity: raw === '' ? null : Number(raw) })
}

// business_logic step 3 — 'Add' row action. Appends a fresh, id-less row
// (INSERT target once saved) with empty/blank values.
function onAddRow(): void {
  emit('update:modelValue', [...rows.value, { category: '', quantity: null }])
}

function onRemoveRow(index: number): void {
  emit(
    'update:modelValue',
    rows.value.filter((_, i) => i !== index),
  )
}
</script>

<template>
  <div class="grading-detail-grid">
    <div class="grading-detail-header">
      <span class="grading-detail-title">Grading Detail</span>
      <button
        type="button"
        class="grading-detail-add"
        :disabled="disabled"
        data-testid="grading-detail-add"
        @click="onAddRow"
      >
        + Tambah Baris
      </button>
    </div>

    <p v-if="error" class="grading-detail-error" role="alert" data-testid="grading-detail-error">
      {{ error }}
    </p>

    <p v-if="rows.length === 0" class="grading-detail-empty">Belum ada baris grading detail.</p>

    <ul v-else class="grading-detail-list" role="list" aria-label="Daftar grading detail">
      <li
        v-for="(row, index) in rows"
        :key="row.id ?? `new-${index}`"
        class="grading-detail-row"
        role="listitem"
        data-testid="grading-detail-row"
      >
        <div class="grading-detail-field">
          <label :for="`grading-detail-category-${index}`" class="grading-detail-label">Kategori</label>
          <input
            :id="`grading-detail-category-${index}`"
            class="grading-detail-input"
            type="text"
            :value="row.category"
            :disabled="disabled"
            @input="onCategoryInput(index, $event)"
          />
        </div>

        <div class="grading-detail-field">
          <label :for="`grading-detail-quantity-${index}`" class="grading-detail-label">Kuantitas</label>
          <input
            :id="`grading-detail-quantity-${index}`"
            class="grading-detail-input"
            type="number"
            :value="row.quantity ?? ''"
            :disabled="disabled"
            @input="onQuantityInput(index, $event)"
          />
        </div>

        <button
          type="button"
          class="grading-detail-remove"
          :disabled="disabled"
          aria-label="Hapus baris grading detail"
          data-testid="grading-detail-remove"
          @click="onRemoveRow(index)"
        >
          Hapus
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.grading-detail-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-family: 'Inter', sans-serif;
}

.grading-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.grading-detail-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.grading-detail-add {
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

.grading-detail-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.grading-detail-error {
  margin: 0;
  padding: 8px 12px;
  border-radius: 6px;
  background-color: #fee2e2;
  color: #dc2626;
  font-size: 13px;
}

.grading-detail-empty {
  margin: 0;
  padding: 12px;
  border: 1px dashed #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  font-size: 13px;
  text-align: center;
}

.grading-detail-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.grading-detail-row {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  align-items: end;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-sizing: border-box;
}

.grading-detail-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.grading-detail-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}

.grading-detail-input {
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

.grading-detail-input:focus {
  outline: 2px solid #249360;
  outline-offset: 1px;
}

.grading-detail-input:disabled {
  opacity: 0.6;
}

.grading-detail-remove {
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

.grading-detail-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
