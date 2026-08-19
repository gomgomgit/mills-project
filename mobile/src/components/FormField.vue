<script setup lang="ts">
/**
 * FormField — generic reusable labeled input, first built for
 * screen-010--form-weighbridge but deliberately generic/decoupled from any
 * one screen's specific fields (label/required/error/type are all props,
 * no weighbridge-specific logic) so screens 011/012 (Form Grading, Form
 * Cages Track) can reuse it for the same "required-asterisk + inline
 * validation error" input pattern (uiux-spec "form" screen_type pattern,
 * mobile) instead of each screen re-implementing its own field markup.
 *
 * v-model-compatible (`modelValue` prop + `update:modelValue` emit), same
 * philosophy as ConfirmDialog.vue (parent owns state, this stays a pure
 * presentational element). Supports text/number/datetime-local (and any
 * other native `<input type>`) via the `type` prop.
 */
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    modelValue: string | number | null
    required?: boolean
    error?: string
    type?: string
    id?: string
    disabled?: boolean
  }>(),
  {
    required: false,
    error: undefined,
    type: 'text',
    id: undefined,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
}>()

// Falls back to a slug derived from `label` when no explicit `id` is
// given, so <label for> / input id stay linked without every call site
// having to invent its own id.
const fieldId = computed(
  () =>
    props.id ??
    `field-${props.label
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')}`,
)

const errorId = computed(() => `${fieldId.value}-error`)

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement
  const raw = target.value

  if (props.type === 'number') {
    emit('update:modelValue', raw === '' ? null : Number(raw))
    return
  }

  emit('update:modelValue', raw)
}
</script>

<template>
  <div class="form-field">
    <label :for="fieldId" class="form-field-label">
      {{ label }}
      <span v-if="required" class="form-field-required" aria-hidden="true">*</span>
    </label>
    <input
      :id="fieldId"
      class="form-field-input"
      :class="{ 'form-field-input--error': error }"
      :type="type"
      :value="modelValue ?? ''"
      :disabled="disabled"
      :aria-required="required"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error ? errorId : undefined"
      @input="onInput"
    />
    <p v-if="error" :id="errorId" class="form-field-error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-family: 'Inter', sans-serif;
}

.form-field-label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.form-field-required {
  color: #dc2626;
}

.form-field-input {
  min-height: 44px;
  padding: 0 12px;
  background-color: #edebeb;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 16px;
  font-family: inherit;
  color: #1f2937;
  box-sizing: border-box;
}

.form-field-input:focus {
  outline: 2px solid #249360;
  outline-offset: 1px;
}

.form-field-input:disabled {
  opacity: 0.6;
}

.form-field-input--error {
  outline: 1px solid #dc2626;
}

.form-field-error {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}
</style>
