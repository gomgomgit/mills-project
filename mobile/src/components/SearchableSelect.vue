<script setup lang="ts">
/**
 * SearchableSelect — generic reusable typeable/searchable dropdown,
 * replacing every plain `<select>` in the mobile app (LoginForm.vue's
 * Business Area, FormGradingView.vue's WB Card No + per-row Quality
 * Parameter, FormCagesTrackView.vue's per-row Time) with one shared
 * combobox component, same "parent owns state, this stays a pure
 * presentational element" philosophy as FormField.vue/ConfirmDialog.vue.
 *
 * Option shape is deliberately fixed to `{ value, label }` (plus whatever
 * extra fields a call site wants to attach) rather than a configurable
 * `valueKey`/`labelKey` pair — every call site found in this app already
 * derives a display label from a lookup/record (e.g.
 * FormGradingView.vue's `wbOptionLabel()`), so it's simpler for the parent
 * to map its own records to `{ value, label, ...raw fields }` once than for
 * this component to special-case option shapes. Attaching extra fields
 * (e.g. `{ value: option.id, label: wbOptionLabel(option), ...option }`) is
 * exactly how a call site gets the FULL matched record back out of the
 * `select` event below — this component simply re-emits whatever object it
 * was given in `options`, unmodified.
 *
 * This component NEVER filters/computes availability of options itself —
 * `options` is always the full, already-filtered array the parent wants
 * shown right now (e.g. FormGradingView.vue's per-row
 * `availableParameterOptions()`, FormCagesTrackView.vue's per-row
 * `availableHourOptions()`); this component only re-filters that array
 * further, client-side, against what the user has typed. No network call.
 *
 * v-model-compatible (`modelValue` prop + `update:modelValue` emit,
 * carrying the selected option's `value`) PLUS a dedicated `select` event
 * carrying the full matched option object — screens with an
 * auto-fill-on-selection side effect (WB Card No -> License Plate
 * No/Estate/Divisi) listen to `select`, not `update:modelValue`, to get the
 * whole record in one event rather than re-deriving it from the emitted
 * value.
 *
 * Combobox pattern (WAI-ARIA): `role="combobox"` on the input,
 * `aria-expanded`/`aria-controls`/`aria-autocomplete="list"`, a
 * `role="listbox"` popup, `role="option"` items, `aria-activedescendant`
 * tracking keyboard navigation. Keyboard: typing filters, ArrowDown/Up
 * moves the active option (opening the popup if closed), Enter selects the
 * active option (or opens the popup if closed and nothing is active yet),
 * Escape closes the popup and discards the in-progress query (reverting
 * the input to the currently selected option's label, same as clicking
 * away). Enter is always `preventDefault()`-ed so this component never
 * accidentally submits an ancestor `<form>` — every call site here lives
 * inside a `<form @submit.prevent>`, and a plain `<select>` never submits
 * on Enter either, so this preserves that expectation.
 *
 * Testability: besides the standard DOM structure, the root element carries
 * `data-value` (mirrors the current `modelValue`, empty string when
 * unset/no match) so component/view tests can assert the current selection
 * the same way they'd read a native `<select>` element's `.value`, without
 * depending on the open/closed popup's internal markup. Each rendered
 * `role="option"` item also carries its own `data-value` (that option's
 * `value`), so a test can assert list membership by the underlying id even
 * when two options share a visible label. Non-prop attributes
 * (e.g. `data-testid`) fall through to this root element by default (Vue's
 * standard attribute inheritance — no `inheritAttrs: false` here), and
 * `id` is an explicit prop bound to the `<input>` itself so `<label for>`
 * association (mirroring FormField.vue's pattern) points at the actually
 * focusable/interactive element.
 */
import { computed, nextTick, ref, watch } from 'vue'

export interface SearchableSelectOption {
  value: string | number
  label: string
  [key: string]: unknown
}

let idCounter = 0

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null
    options: SearchableSelectOption[]
    placeholder?: string
    disabled?: boolean
    id?: string
  }>(),
  {
    placeholder: 'Pilih atau ketik untuk mencari…',
    disabled: false,
    id: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
  select: [option: SearchableSelectOption]
}>()

const fieldId = computed(() => props.id ?? `searchable-select-${++idCounter}`)
const listboxId = computed(() => `${fieldId.value}-listbox`)

function optionDomId(index: number): string {
  return `${fieldId.value}-option-${index}`
}

const isOpen = ref(false)
const query = ref('')
const activeIndex = ref(-1)

const selectedOption = computed<SearchableSelectOption | undefined>(() =>
  props.options.find((option) => option.value === props.modelValue),
)

// The <select>-equivalent testable value — empty string when nothing
// matches the current modelValue, same "no selection" convention
// FormField.vue/native <select> already use.
const currentValue = computed(() => selectedOption.value?.value ?? '')

// While open, the input shows whatever the user has typed (the live
// filter query); while closed, it shows the selected option's label (or
// nothing at all if no option is selected) — same "shows the selected
// option's label when closed" requirement a native <select> gives for
// free.
const displayValue = computed(() => (isOpen.value ? query.value : (selectedOption.value?.label ?? '')))

const filteredOptions = computed<SearchableSelectOption[]>(() => {
  const normalizedQuery = query.value.trim().toLowerCase()

  if (!normalizedQuery) {
    return props.options
  }

  return props.options.filter((option) => option.label.toLowerCase().includes(normalizedQuery))
})

const activeOptionDomId = computed(() =>
  isOpen.value && activeIndex.value >= 0 && activeIndex.value < filteredOptions.value.length
    ? optionDomId(activeIndex.value)
    : undefined,
)

// Keeps activeIndex valid whenever the filtered list changes size (e.g.
// typing narrows the list down past the previously active index).
watch(filteredOptions, (options) => {
  if (activeIndex.value >= options.length) {
    activeIndex.value = options.length > 0 ? 0 : -1
  }
})

function openDropdown(): void {
  if (props.disabled) {
    return
  }

  isOpen.value = true
  query.value = ''
  activeIndex.value = filteredOptions.value.findIndex((option) => option.value === props.modelValue)
}

function closeDropdown(): void {
  isOpen.value = false
  query.value = ''
  activeIndex.value = -1
}

function onFocus(): void {
  openDropdown()
}

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement

  if (!isOpen.value) {
    isOpen.value = true
  }

  query.value = target.value
  activeIndex.value = filteredOptions.value.length > 0 ? 0 : -1
}

function choose(option: SearchableSelectOption): void {
  emit('update:modelValue', option.value)
  emit('select', option)
  closeDropdown()
}

// mousedown (not click) so this fires BEFORE the input's blur handler —
// otherwise the popup would already be closed (blur) by the time a click
// event landed on it.
function onOptionMouseDown(option: SearchableSelectOption): void {
  choose(option)
}

function onBlur(): void {
  // Deferred so a mousedown-triggered selection above still lands first;
  // also lets Escape/click-away close without fighting this handler.
  nextTick(() => {
    closeDropdown()
  })
}

function onKeydown(event: KeyboardEvent): void {
  if (props.disabled) {
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()

    if (!isOpen.value) {
      openDropdown()
      return
    }

    if (filteredOptions.value.length > 0) {
      activeIndex.value = (activeIndex.value + 1) % filteredOptions.value.length
    }

    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()

    if (!isOpen.value) {
      openDropdown()
      return
    }

    if (filteredOptions.value.length > 0) {
      activeIndex.value = activeIndex.value <= 0 ? filteredOptions.value.length - 1 : activeIndex.value - 1
    }

    return
  }

  if (event.key === 'Enter') {
    // Always prevented — this component never submits an ancestor <form>
    // on Enter, matching a native <select>'s behavior.
    event.preventDefault()

    if (!isOpen.value) {
      openDropdown()
      return
    }

    const active = filteredOptions.value[activeIndex.value]

    if (active) {
      choose(active)
    }

    return
  }

  if (event.key === 'Escape') {
    event.preventDefault()
    closeDropdown()
    ;(event.target as HTMLInputElement).blur()
  }
}
</script>

<template>
  <div class="searchable-select" :class="{ 'searchable-select--disabled': disabled }" :data-value="currentValue">
    <input
      :id="fieldId"
      class="searchable-select-input"
      type="text"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      aria-haspopup="listbox"
      :aria-expanded="isOpen"
      :aria-controls="listboxId"
      :aria-activedescendant="activeOptionDomId"
      :placeholder="placeholder"
      :disabled="disabled"
      :value="displayValue"
      @focus="onFocus"
      @input="onInput"
      @keydown="onKeydown"
      @blur="onBlur"
    />

    <ul v-if="isOpen" :id="listboxId" class="searchable-select-listbox" role="listbox">
      <li
        v-for="(option, index) in filteredOptions"
        :id="optionDomId(index)"
        :key="option.value"
        class="searchable-select-option"
        :class="{ 'searchable-select-option--active': index === activeIndex }"
        role="option"
        :aria-selected="option.value === modelValue"
        :data-value="option.value"
        @mousedown.prevent="onOptionMouseDown(option)"
      >
        {{ option.label }}
      </li>
      <li v-if="filteredOptions.length === 0" class="searchable-select-empty" role="presentation">
        {{ options.length === 0 ? 'Tidak ada data tersedia.' : 'Tidak ada hasil yang cocok.' }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.searchable-select {
  position: relative;
  width: 100%;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.searchable-select-input {
  width: 100%;
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

.searchable-select-input:focus {
  outline: 2px solid #249360;
  outline-offset: 1px;
}

.searchable-select-input:disabled {
  opacity: 0.6;
}

.searchable-select--disabled {
  cursor: not-allowed;
}

.searchable-select-listbox {
  position: absolute;
  z-index: 20;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  max-height: 220px;
  margin: 0;
  padding: 4px;
  overflow-y: auto;
  list-style: none;
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  box-sizing: border-box;
}

.searchable-select-option {
  min-height: 40px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  border-radius: 6px;
  font-size: 15px;
  color: #1f2937;
  cursor: pointer;
}

.searchable-select-option--active {
  background-color: #eef6f1;
  color: #249360;
}

.searchable-select-empty {
  min-height: 40px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: 13px;
  color: #6b7280;
}
</style>
