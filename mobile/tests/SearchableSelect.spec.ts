/**
 * SearchableSelect.spec.ts — component tests for
 * mobile/src/components/SearchableSelect.vue, the shared typeable/
 * searchable dropdown replacing every plain `<select>` in the mobile app
 * (see that file's own header comment for the full design rationale).
 *
 * Covers:
 *   1. Closed-state display: shows the selected option's label; shows the
 *      placeholder when nothing is selected.
 *   2. Typing filters the visible option list (case-insensitive substring
 *      match against `label`), and re-opens the popup if it were closed.
 *   3. Clicking a filtered option emits BOTH `update:modelValue` (the
 *      option's `value`) and `select` (the FULL option object, including
 *      any extra fields beyond `value`/`label` — the auto-fill contract
 *      FormGradingView.vue's WB Card No picker depends on).
 *   4. Keyboard: ArrowDown/ArrowUp move the active option (wrapping at the
 *      ends) and Enter selects the active option; Escape closes the popup
 *      without changing the selection.
 *   5. Empty states: `options` entirely empty shows one message; a
 *      non-empty `options` list with zero matches for the typed query
 *      shows a different message.
 *   6. Disabled: input is disabled, focus/keydown do not open the popup.
 *   7. `data-value` on the root element mirrors the current `modelValue`
 *      the same way a native `<select>` element's `.value` would — this is
 *      the testability hook other specs (FormGradingView.spec.ts etc.) use
 *      in place of reading `(el as HTMLSelectElement).value`.
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'

const OPTIONS: SearchableSelectOption[] = [
  { value: 'wb-1', label: 'WB-2001', vehicle_number: 'B 1234 CD', estate_supplier: 'Estate A' },
  { value: 'wb-2', label: 'WB-2002', vehicle_number: 'B 5678 EF', estate_supplier: 'Estate B' },
  { value: 'wb-3', label: 'WB-3003', vehicle_number: 'B 9999 ZZ', estate_supplier: 'Estate C' },
]

function mountSelect(props: Partial<InstanceType<typeof SearchableSelect>['$props']> = {}) {
  return mount(SearchableSelect, {
    props: {
      modelValue: null,
      options: OPTIONS,
      id: 'test-select',
      ...props,
    },
  })
}

describe('SearchableSelect', () => {
  // 1
  it('shows the placeholder when nothing is selected, and the selected label when closed', async () => {
    const wrapper = mountSelect({ placeholder: 'Pilih WB Card No' })
    const input = wrapper.find('input')

    expect((input.element as HTMLInputElement).value).toBe('')
    expect(input.attributes('placeholder')).toBe('Pilih WB Card No')

    await wrapper.setProps({ modelValue: 'wb-2' })
    expect((input.element as HTMLInputElement).value).toBe('WB-2002')
  })

  // 2
  it('filters the visible options as the user types, case-insensitively', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    expect(wrapper.findAll('[role="option"]')).toHaveLength(3)

    await input.setValue('3003')
    let optionLabels = wrapper.findAll('[role="option"]').map((el) => el.text())
    expect(optionLabels).toEqual(['WB-3003'])

    await input.setValue('wb-')
    optionLabels = wrapper.findAll('[role="option"]').map((el) => el.text())
    expect(optionLabels).toEqual(['WB-2001', 'WB-2002', 'WB-3003'])
  })

  // 2b
  it('re-opens the popup on typing if it had been closed', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)

    await input.setValue('WB-2001')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
  })

  // 3
  it('emits update:modelValue with the value AND select with the full option object on click', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await wrapper.findAll('[role="option"]')[1].trigger('mousedown')

    expect(wrapper.emitted('update:modelValue')).toEqual([['wb-2']])
    expect(wrapper.emitted('select')).toEqual([
      [{ value: 'wb-2', label: 'WB-2002', vehicle_number: 'B 5678 EF', estate_supplier: 'Estate B' }],
    ])
  })

  // 3b
  it('closes the popup and shows the newly selected label after a click selection', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await wrapper.findAll('[role="option"]')[2].trigger('mousedown')
    await wrapper.setProps({ modelValue: 'wb-3' })

    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    expect((input.element as HTMLInputElement).value).toBe('WB-3003')
  })

  // 4a
  it('ArrowDown/ArrowUp move the active option (wrapping at both ends)', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    // Nothing selected yet, so the first ArrowDown lands on index 0.
    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.findAll('[role="option"]')[0].classes()).toContain('searchable-select-option--active')
    expect(wrapper.findAll('[role="option"]')[0].attributes('aria-selected')).toBe('false')

    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.findAll('[role="option"]')[1].classes()).toContain('searchable-select-option--active')

    await input.trigger('keydown', { key: 'ArrowUp' })
    expect(wrapper.findAll('[role="option"]')[0].classes()).toContain('searchable-select-option--active')

    // Wraps from the first option to the last on ArrowUp.
    await input.trigger('keydown', { key: 'ArrowUp' })
    expect(wrapper.findAll('[role="option"]')[2].classes()).toContain('searchable-select-option--active')
  })

  // 4b
  it('Enter selects the active option', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')).toEqual([['wb-2']])
    expect(wrapper.emitted('select')?.[0][0]).toMatchObject({ value: 'wb-2', label: 'WB-2002' })
  })

  // 4c
  it('Escape closes the popup without emitting a selection', async () => {
    const wrapper = mountSelect({ modelValue: 'wb-1' })
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Escape' })

    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.emitted('select')).toBeUndefined()
    expect((input.element as HTMLInputElement).value).toBe('WB-2001')
  })

  // 5a
  it('shows an empty-data message when options is entirely empty', async () => {
    const wrapper = mountSelect({ options: [] })
    const input = wrapper.find('input')

    await input.trigger('focus')

    expect(wrapper.find('.searchable-select-empty').text()).toBe('Tidak ada data tersedia.')
  })

  // 5b
  it('shows a no-match message when typing filters every option out', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('nonexistent-query')

    expect(wrapper.find('.searchable-select-empty').text()).toBe('Tidak ada hasil yang cocok.')
    expect(wrapper.findAll('[role="option"]')).toHaveLength(0)
  })

  // 6
  it('stays disabled: input is disabled and focus/keydown do not open the popup', async () => {
    const wrapper = mountSelect({ disabled: true })
    const input = wrapper.find('input')

    expect(input.attributes('disabled')).toBeDefined()

    await input.trigger('focus')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)

    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  // 7
  it('exposes the current modelValue via data-value on the root element, like a native select.value', async () => {
    const wrapper = mountSelect({ modelValue: 'wb-2' })
    expect(wrapper.attributes('data-value')).toBe('wb-2')

    await wrapper.setProps({ modelValue: null })
    expect(wrapper.attributes('data-value')).toBe('')

    // A modelValue with no matching option also falls back to ''.
    await wrapper.setProps({ modelValue: 'does-not-exist' })
    expect(wrapper.attributes('data-value')).toBe('')
  })

  // Combobox a11y wiring.
  it('sets combobox aria attributes correctly', async () => {
    const wrapper = mountSelect()
    const input = wrapper.find('input')

    expect(input.attributes('role')).toBe('combobox')
    expect(input.attributes('aria-expanded')).toBe('false')

    await input.trigger('focus')
    expect(input.attributes('aria-expanded')).toBe('true')
    expect(input.attributes('aria-controls')).toBe(wrapper.find('[role="listbox"]').attributes('id'))

    await input.trigger('keydown', { key: 'ArrowDown' })
    const activeOption = wrapper.findAll('[role="option"]')[0]
    expect(input.attributes('aria-activedescendant')).toBe(activeOption.attributes('id'))
  })

  // data-testid (or any non-prop attribute) falls through to the root
  // element, matching FormField.vue/ConfirmDialog.vue's plain-component
  // attribute-inheritance convention (no inheritAttrs: false override).
  it('forwards data-testid to the root element', () => {
    const wrapper = mount(SearchableSelect, {
      props: { modelValue: null, options: OPTIONS, id: 'wb-card-no-select' },
      attrs: { 'data-testid': 'wb-card-no-select' },
    })

    expect(wrapper.find('[data-testid="wb-card-no-select"]').exists()).toBe(true)
  })
})
