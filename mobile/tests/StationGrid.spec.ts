/**
 * StationGrid.spec.ts — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" business_logic steps 2-4.
 *
 * Covers unit_test_cases 1, 2 and 5, and both test_scenarios'
 * component_test entries:
 *   1. "Pilih Stasiun — success" — grid renders 3 active + 12 disabled
 *      stations; tapping an active tile emits `navigate` targeting the
 *      Monitor screen's station type.
 *   2. "Tap Stasiun Disabled" — tapping a disabled tile shows the
 *      "belum tersedia" info message and does NOT emit `navigate`.
 *
 * StationGrid.vue is presentation-only / router-agnostic (per its own
 * header comment) — it only emits `navigate`, it never calls
 * `router.push` itself, so this file mounts it directly with no router
 * mock needed. The actual `router.push` call is covered separately in
 * StationListView.spec.ts (unit_test_case 3).
 */
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import StationGrid from '@/components/StationGrid.vue'
import type { StationSlot, StationType } from '@/services/stationRepo'

function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
  return {
    businessUnitId: 'bu-1',
    name: `Stasiun ${overrides.id}`,
    type: 'other',
    isActive: false,
    icon: null,
    ...overrides,
  }
}

/** 3 active real station types + 12 disabled placeholder stations — 15 total, mirroring the synced local table shape per stationRepo.ts's header comment. */
function makeFullStationSet(): StationSlot[] {
  const active: StationSlot[] = [
    makeStation({ id: 'station-weighbridge', name: 'Timbangan', type: 'weighbridge', isActive: true }),
    makeStation({ id: 'station-grading', name: 'Grading', type: 'grading', isActive: true }),
    makeStation({ id: 'station-cages-track', name: 'Cages Track', type: 'cages-track', isActive: true }),
  ]

  const disabled: StationSlot[] = Array.from({ length: 12 }, (_, index) =>
    makeStation({
      id: `station-placeholder-${index}`,
      name: `Stasiun ${index + 1}`,
      type: 'other',
      isActive: false,
    }),
  )

  return [...active, ...disabled]
}

describe('StationGrid', () => {
  // unit_test_case 1
  it('renders station as tappable when is_active=true', () => {
    const station = makeStation({ id: 'station-1', name: 'Timbangan', type: 'weighbridge', isActive: true })
    const wrapper = mount(StationGrid, { props: { stations: [station] } })

    const tile = wrapper.get('[data-testid="station-tile-station-1"]')
    expect(tile.attributes('aria-disabled')).toBe('false')
    expect(tile.classes()).toContain('station-tile--active')
  })

  // unit_test_case 2
  it('renders station as disabled/info-only when is_active=false', () => {
    const station = makeStation({ id: 'station-2', name: 'Stasiun X', type: 'other', isActive: false })
    const wrapper = mount(StationGrid, { props: { stations: [station] } })

    const tile = wrapper.get('[data-testid="station-tile-station-2"]')
    expect(tile.attributes('aria-disabled')).toBe('true')
    expect(tile.classes()).toContain('station-tile--disabled')
    expect(tile.text()).toContain('Belum tersedia')
  })

  // Scenario: "Tap Stasiun Disabled"
  it("scenario: tap stasiun disabled — shows 'belum tersedia' info and does not navigate", async () => {
    const station = makeStation({ id: 'station-3', name: 'Stasiun Y', type: 'other', isActive: false })
    const wrapper = mount(StationGrid, { props: { stations: [station] } })

    expect(wrapper.find('[data-testid="station-info-message"]').exists()).toBe(false)

    await wrapper.get('[data-testid="station-tile-station-3"]').trigger('click')

    const infoMessage = wrapper.get('[data-testid="station-info-message"]')
    expect(infoMessage.attributes('role')).toBe('status')
    expect(infoMessage.text().toLowerCase()).toContain('belum tersedia')
    expect(wrapper.emitted('navigate')).toBeUndefined()
  })

  // Scenario: "Pilih Stasiun — success"
  it('scenario: pilih stasiun — success — tapping an active tile emits navigate with the station type', async () => {
    const stations = makeFullStationSet()
    const wrapper = mount(StationGrid, { props: { stations } })

    await wrapper.get('[data-testid="station-tile-station-weighbridge"]').trigger('click')

    expect(wrapper.emitted('navigate')).toEqual([['weighbridge']])
    expect(wrapper.find('[data-testid="station-info-message"]').exists()).toBe(false)
  })

  // unit_test_case 5 — combined/integration-style: full 15-station grid
  // renders every tile with the correct active/disabled state, and
  // tapping each active tile navigates correctly while disabled tiles
  // never do.
  it('returns success result when all conditions pass — grid renders all stations with correct state, tapping active navigates correctly', async () => {
    const stations = makeFullStationSet()
    const wrapper = mount(StationGrid, { props: { stations } })

    // All 15 tiles rendered.
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(15)

    const activeTypes: StationType[] = ['weighbridge', 'grading', 'cages-track']
    for (const station of stations.filter((entry) => entry.isActive)) {
      const tile = wrapper.get(`[data-testid="station-tile-${station.id}"]`)
      expect(tile.attributes('aria-disabled')).toBe('false')
    }

    for (const station of stations.filter((entry) => !entry.isActive)) {
      const tile = wrapper.get(`[data-testid="station-tile-${station.id}"]`)
      expect(tile.attributes('aria-disabled')).toBe('true')
    }

    // Tap each active tile in turn -> navigate emitted with matching type,
    // in order.
    for (const type of activeTypes) {
      const stationId = `station-${type}`
      await wrapper.get(`[data-testid="station-tile-${stationId}"]`).trigger('click')
    }
    expect(wrapper.emitted('navigate')).toEqual(activeTypes.map((type) => [type]))

    // Tap a disabled tile -> info message shown, no additional navigate
    // emitted.
    await wrapper.get('[data-testid="station-tile-station-placeholder-0"]').trigger('click')
    expect(wrapper.emitted('navigate')).toHaveLength(3)
    expect(wrapper.get('[data-testid="station-info-message"]').text().toLowerCase()).toContain('belum tersedia')
  })

  // business_logic step 3 / edge_case_handling — draft-status-driven
  // active tile background color (2026-08-18 update: red vs dark-neutral
  // tile background per draftStatusByType). jsdom normalizes the inline
  // hex color set by activeTileStyle() to an `rgb(...)` string on both
  // the `style` attribute and the element's computed `style` object —
  // asserted against the normalized form rather than the hex literal.
  describe('draft-status-by-type background color', () => {
    it('renders the active tile with the red background when draftStatusByType[type] is true', () => {
      const station = makeStation({ id: 'station-wb', name: 'Timbangan', type: 'weighbridge', isActive: true })
      const wrapper = mount(StationGrid, {
        props: { stations: [station], draftStatusByType: { weighbridge: true } },
      })

      const tile = wrapper.get('[data-testid="station-tile-station-wb"]')
      expect((tile.element as HTMLElement).style.backgroundColor).toBe('rgb(210, 0, 0)')
    })

    it('renders the active tile with the dark-neutral background when draftStatusByType[type] is false', () => {
      const station = makeStation({ id: 'station-wb', name: 'Timbangan', type: 'weighbridge', isActive: true })
      const wrapper = mount(StationGrid, {
        props: { stations: [station], draftStatusByType: { weighbridge: false } },
      })

      const tile = wrapper.get('[data-testid="station-tile-station-wb"]')
      expect((tile.element as HTMLElement).style.backgroundColor).toBe('rgb(31, 41, 55)')
    })

    it('renders the active tile with the dark-neutral background when the type key is absent from draftStatusByType', () => {
      const station = makeStation({ id: 'station-wb', name: 'Timbangan', type: 'weighbridge', isActive: true })
      // draftStatusByType intentionally omitted — component's own default
      // prop value ({}) applies, same as every pre-existing test in this
      // file that predates the prop.
      const wrapper = mount(StationGrid, { props: { stations: [station] } })

      const tile = wrapper.get('[data-testid="station-tile-station-wb"]')
      expect((tile.element as HTMLElement).style.backgroundColor).toBe('rgb(31, 41, 55)')
    })

    it('still renders tap/navigate and disabled-tile behavior unchanged when draftStatusByType is omitted (default {})', async () => {
      // Guards against a regression where adding the new prop broke the
      // existing tile-rendering/tap/navigation contract for callers that
      // don't pass it at all.
      const stations = makeFullStationSet()
      const wrapper = mount(StationGrid, { props: { stations } })

      expect(wrapper.findAll('[role="listitem"]')).toHaveLength(15)

      await wrapper.get('[data-testid="station-tile-station-weighbridge"]').trigger('click')
      expect(wrapper.emitted('navigate')).toEqual([['weighbridge']])

      const activeTile = wrapper.get('[data-testid="station-tile-station-weighbridge"]')
      expect(activeTile.attributes('aria-disabled')).toBe('false')
      // No draft-status entry for 'weighbridge' -> dark-neutral default.
      expect((activeTile.element as HTMLElement).style.backgroundColor).toBe('rgb(31, 41, 55)')

      const disabledTile = wrapper.get('[data-testid="station-tile-station-placeholder-0"]')
      expect(disabledTile.attributes('aria-disabled')).toBe('true')
    })
  })

  // entity-catalog v7 / tech-spec v4 — station.icon override (Mills Setting
  // feature). Covers the 3 new unit_test_cases: valid override, null
  // fallback, unrecognized-name fallback. tile color/shadow/layout are
  // asserted unchanged (this is an icon-only change, not a photo/background).
  describe('station.icon override', () => {
    it("uses station.icon as the tile's icon when it is set to a recognized override name", () => {
      const station = makeStation({
        id: 'station-wb',
        name: 'Timbangan',
        type: 'weighbridge',
        isActive: true,
        icon: 'truck',
      })
      const wrapper = mount(StationGrid, { props: { stations: [station] } })

      const iconHtml = wrapper.get('[data-testid="station-tile-station-wb"] svg').element.innerHTML
      // 'truck' override path (circle wheels) — distinct from the default
      // 'weighbridge' path (a single <rect> deck, no <circle>).
      expect(iconHtml).toContain('cy="19"')
      // Tile styling (background/shadow/radius/layout) is untouched by the
      // icon override — same class/inline-style contract as before.
      const tile = wrapper.get('[data-testid="station-tile-station-wb"]')
      expect(tile.classes()).toContain('station-tile--active')
    })

    it('falls back to the default type-based icon when station.icon is null', () => {
      const withIcon = makeStation({ id: 'a', type: 'weighbridge', isActive: true, icon: 'truck' })
      const withoutIcon = makeStation({ id: 'b', type: 'weighbridge', isActive: true, icon: null })
      const wrapper = mount(StationGrid, { props: { stations: [withIcon, withoutIcon] } })

      const iconWithHtml = wrapper.get('[data-testid="station-tile-a"] svg').element.innerHTML
      const iconWithoutHtml = wrapper.get('[data-testid="station-tile-b"] svg').element.innerHTML

      expect(iconWithHtml).not.toEqual(iconWithoutHtml)
      expect(iconWithoutHtml).toContain('width="20" height="10"') // default weighbridge icon
    })

    it('falls back to the default type-based icon when station.icon is an unrecognized name', () => {
      const station = makeStation({
        id: 'station-ct',
        type: 'cages-track',
        isActive: true,
        icon: 'not-a-real-icon',
      })
      const wrapper = mount(StationGrid, { props: { stations: [station] } })

      const iconHtml = wrapper.get('[data-testid="station-tile-station-ct"] svg').element.innerHTML
      // Default cages-track icon (two side-by-side <rect> cages), not the
      // FALLBACK_ICON square either.
      expect(iconHtml).toContain('x="3" y="4" width="7" height="16"')
    })

    it('never applies station.icon on a disabled/placeholder tile, even when set', () => {
      const station = makeStation({ id: 'station-ph', isActive: false, icon: 'truck' })
      const wrapper = mount(StationGrid, { props: { stations: [station] } })

      const iconHtml = wrapper.get('[data-testid="station-tile-station-ph"] svg').element.innerHTML
      expect(iconHtml).not.toContain('cy="19"')
    })
  })
})
