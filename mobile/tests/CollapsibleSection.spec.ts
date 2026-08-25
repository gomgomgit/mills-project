/**
 * CollapsibleSection.spec.ts — mobile/src/components/CollapsibleSection.vue.
 *
 * Generic collapse/expand wrapper, first used by the 4 station Form
 * views' "Target Operasional" section (2026-08-25). Pure/presentational,
 * no store/service — mounted directly with slot content.
 *
 * Visibility assertions check the inline `style` attribute v-show toggles
 * directly, NOT `.isVisible()` — CSS is not processed under Vitest in
 * this project (no `test.css` in vitest.config.ts), so
 * `.isVisible()`'s getComputedStyle-based check is unreliable here
 * (confirmed: it can read a stale/default computed `display` unrelated
 * to the actual inline style Vue just set).
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CollapsibleSection from '@/components/CollapsibleSection.vue'

describe('CollapsibleSection', () => {
  it('renders the title and starts collapsed (body hidden, aria-expanded false)', () => {
    const wrapper = mount(CollapsibleSection, {
      props: { title: 'Target Operasional' },
      slots: { default: '<p>Isi referensi</p>' },
    })

    expect(wrapper.text()).toContain('Target Operasional')
    expect(wrapper.get('[data-testid="collapsible-section-toggle"]').attributes('aria-expanded')).toBe('false')
    expect(wrapper.get('[data-testid="collapsible-section-body"]').attributes('style')).toContain('display: none')
  })

  it('expands the body and flips aria-expanded when the header is tapped', async () => {
    const wrapper = mount(CollapsibleSection, {
      props: { title: 'Target Operasional' },
      slots: { default: '<p>Isi referensi</p>' },
    })

    await wrapper.get('[data-testid="collapsible-section-toggle"]').trigger('click')

    expect(wrapper.get('[data-testid="collapsible-section-toggle"]').attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('[data-testid="collapsible-section-body"]').attributes('style')).not.toContain('display: none')
    expect(wrapper.text()).toContain('Isi referensi')
  })

  it('collapses again on a second tap', async () => {
    const wrapper = mount(CollapsibleSection, {
      props: { title: 'Target Operasional' },
      slots: { default: '<p>Isi referensi</p>' },
    })

    const toggle = wrapper.get('[data-testid="collapsible-section-toggle"]')
    await toggle.trigger('click')
    await toggle.trigger('click')

    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(wrapper.get('[data-testid="collapsible-section-body"]').attributes('style')).toContain('display: none')
  })

  it('renders slot content in the DOM even while collapsed (v-show, not v-if)', () => {
    const wrapper = mount(CollapsibleSection, {
      props: { title: 'Target Operasional' },
      slots: { default: '<table><tbody><tr><td>Baris referensi</td></tr></tbody></table>' },
    })

    expect(wrapper.text()).toContain('Baris referensi')
  })
})
