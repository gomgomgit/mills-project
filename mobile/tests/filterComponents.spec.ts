/**
 * filterComponents.spec.ts — keluarga komponen filter bersama
 * (src/components/filters/, audit desain filter 2026-10-05).
 *
 * Komponen-komponen ini murni presentasional: yang diuji di sini adalah
 * kontrak v-model/emit-nya (nilai yang dipancarkan pintasan tanggal, tombol
 * hapus pencarian, reset) dan aturan tampilnya (ringkasan jumlah, baris chip
 * yang tidak boleh muncul kosong). Semantik penyaringan tetap diuji di spec
 * tiap layar Data Preview / Laporan.
 */
import { defineComponent, h, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ListFilterBar from '@/components/filters/ListFilterBar.vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import FilterChip from '@/components/filters/FilterChip.vue'
import FilterDateInput from '@/components/filters/FilterDateInput.vue'
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue'
import FilterSelectField from '@/components/filters/FilterSelectField.vue'

function todayLocalDateString(): string {
  const today = new Date()
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
}

describe('FilterDateInput', () => {
  it('pintasan "Semua" memancarkan string kosong dan "Hari ini" memancarkan tanggal lokal hari ini', async () => {
    const wrapper = mount(FilterDateInput, { props: { modelValue: '2020-01-01', testId: 'date-filter' } })

    await wrapper.get('[data-testid="date-quick-all"]').trigger('click')
    await wrapper.get('[data-testid="date-quick-today"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([[''], [todayLocalDateString()]])
  })

  it('menandai pintasan yang sesuai nilai saat ini (aria-pressed), dan tidak keduanya untuk tanggal lain', async () => {
    const wrapper = mount(FilterDateInput, { props: { modelValue: todayLocalDateString() } })
    expect(wrapper.get('[data-testid="date-quick-today"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('[data-testid="date-quick-all"]').attributes('aria-pressed')).toBe('false')

    await wrapper.setProps({ modelValue: '' })
    expect(wrapper.get('[data-testid="date-quick-today"]').attributes('aria-pressed')).toBe('false')
    expect(wrapper.get('[data-testid="date-quick-all"]').attributes('aria-pressed')).toBe('true')

    await wrapper.setProps({ modelValue: '2020-01-01' })
    expect(wrapper.get('[data-testid="date-quick-today"]').attributes('aria-pressed')).toBe('false')
    expect(wrapper.get('[data-testid="date-quick-all"]').attributes('aria-pressed')).toBe('false')
  })

  it('memasang testId pada <input type="date"> asli dan meneruskan ketikan apa adanya', async () => {
    const wrapper = mount(FilterDateInput, { props: { modelValue: '', testId: 'date-filter-input' } })
    const input = wrapper.get('[data-testid="date-filter-input"]')

    expect(input.element.tagName).toBe('INPUT')
    expect(input.attributes('type')).toBe('date')

    await input.setValue('2026-08-11')
    expect(wrapper.emitted('update:modelValue')).toEqual([['2026-08-11']])
  })

  it('label terhubung ke input (getByLabel tetap bekerja)', () => {
    const wrapper = mount(FilterDateInput, { props: { modelValue: '' } })
    const label = wrapper.get('label')
    expect(label.text()).toBe('Tanggal')
    expect(label.attributes('for')).toBe(wrapper.get('input').attributes('id'))
  })
})

describe('FilterSearchInput', () => {
  it('tombol hapus hanya ada saat ada kata kunci, dan memancarkan string kosong', async () => {
    const wrapper = mount(FilterSearchInput, { props: { modelValue: '', testId: 'search-filter' } })
    expect(wrapper.find('[data-testid="search-filter-clear"]').exists()).toBe(false)

    await wrapper.setProps({ modelValue: 'str' })
    await wrapper.get('[data-testid="search-filter-clear"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([['']])
  })

  it('tetap <input type="text"> dengan testId lama', async () => {
    const wrapper = mount(FilterSearchInput, { props: { modelValue: '', testId: 'search-filter-input' } })
    const input = wrapper.get('[data-testid="search-filter-input"]')
    expect(input.attributes('type')).toBe('text')

    await input.setValue('ct-10')
    expect(wrapper.emitted('update:modelValue')).toEqual([['ct-10']])
  })
})

describe('ListFilterBar', () => {
  const baseProps = { date: '', search: '', dateTestId: 'date-filter', searchTestId: 'search-filter' }

  it('ringkasan: "N data" tanpa penyaringan efektif, "X dari N data" bila menyaring', async () => {
    const wrapper = mount(ListFilterBar, { props: { ...baseProps, showCount: true, filteredCount: 3, totalCount: 3 } })
    expect(wrapper.get('[data-testid="filter-result-count"]').text()).toBe('3 data')

    await wrapper.setProps({ filteredCount: 1 })
    expect(wrapper.get('[data-testid="filter-result-count"]').text()).toBe('1 dari 3 data')
  })

  it('tidak menampilkan jumlah saat daftar belum dimuat atau tidak ada record lokal sama sekali', async () => {
    const wrapper = mount(ListFilterBar, { props: { ...baseProps, showCount: false, filteredCount: 0, totalCount: 5 } })
    expect(wrapper.find('[data-testid="filter-result-count"]').exists()).toBe(false)

    await wrapper.setProps({ showCount: true, totalCount: 0 })
    expect(wrapper.find('[data-testid="filter-result-count"]').exists()).toBe(false)
  })

  it('Reset Filter hanya dirender saat ada filter aktif — satu-satunya di layar — dan memancarkan reset', async () => {
    const wrapper = mount(ListFilterBar, { props: { ...baseProps, active: false } })
    expect(wrapper.find('[data-testid="reset-filter-button"]').exists()).toBe(false)

    await wrapper.setProps({ active: true })
    expect(wrapper.findAll('[data-testid="reset-filter-button"]')).toHaveLength(1)

    await wrapper.get('[data-testid="reset-filter-button"]').trigger('click')
    expect(wrapper.emitted('reset')).toHaveLength(1)
  })

  it('v-model:date dan v-model:search tersambung ke induk', async () => {
    const Host = defineComponent({
      setup() {
        const date = ref('2026-08-11')
        const search = ref('')
        return () =>
          h('div', [
            h(ListFilterBar, {
              date: date.value,
              search: search.value,
              dateTestId: 'date-filter',
              searchTestId: 'search-filter',
              'onUpdate:date': (v: string) => (date.value = v),
              'onUpdate:search': (v: string) => (search.value = v),
            }),
            h('output', { 'data-testid': 'state' }, `${date.value}|${search.value}`),
          ])
      },
    })
    const wrapper = mount(Host)

    await wrapper.get('[data-testid="date-quick-all"]').trigger('click')
    await wrapper.get('[data-testid="search-filter"]').setValue('abc')
    expect(wrapper.get('[data-testid="state"]').text()).toBe('|abc')

    await wrapper.get('[data-testid="search-filter-clear"]').trigger('click')
    expect(wrapper.get('[data-testid="state"]').text()).toBe('|')
  })
})

describe('FilterPanel', () => {
  it('tidak merender baris chip bila semua chip bersyarat bernilai false', () => {
    const show = ref(false)
    const Host = defineComponent({
      setup() {
        return () =>
          h(FilterPanel, null, {
            default: () => h('span', 'kendali'),
            chips: () => [show.value ? h(FilterChip, { label: 'Mill', value: 'BU A' }) : null],
          })
      },
    })
    const wrapper = mount(Host)
    expect(wrapper.find('.filter-panel-chips').exists()).toBe(false)
  })

  it('merender chip dengan teks "Label: Nilai"', () => {
    const wrapper = mount(FilterPanel, {
      slots: { chips: () => h(FilterChip, { label: 'Production Line', value: 'Line 2', 'data-testid': 'production-line-current' }) },
    })
    expect(wrapper.find('.filter-panel-chips').exists()).toBe(true)
    expect(wrapper.get('[data-testid="production-line-current"]').text()).toBe('Production Line: Line 2')
  })
})

describe('FilterSelectField', () => {
  it('membungkus <select> milik induk tanpa mengubahnya, root berkelas filter-field', () => {
    const wrapper = mount(FilterSelectField, {
      props: { label: 'Periode Pelaporan', icon: 'period' },
      slots: { default: '<select data-testid="period-select"><option>Pilih Periode</option></select>' },
    })
    const select = wrapper.get('[data-testid="period-select"]')
    expect(select.element.closest('.filter-field')).not.toBeNull()
    expect(select.element.closest('label')?.textContent).toContain('Periode Pelaporan')
  })
})
