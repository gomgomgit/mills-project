<script setup lang="ts">
/**
 * StationGrid — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" business_logic steps 2-4.
 *
 * Presentation-only / router-agnostic (same pattern as
 * PausedDraftsList.vue): accepts the loaded `stations` list as a prop and
 * emits `navigate` with the tapped station's type — StationListView.vue
 * owns the actual `router.push`. This keeps the component trivially
 * component-testable without a router instance.
 *
 * Active tiles (`is_active = true`) are tappable and emit `navigate`.
 * Disabled tiles (`is_active = false`) are visually distinct (muted,
 * reduced emphasis) but intentionally NOT a native `disabled` button —
 * business_logic step 4 requires a tap on a disabled tile to still show a
 * "belum tersedia" info message, which a real `disabled` attribute would
 * suppress (no click/focus at all). `aria-disabled="true"` communicates
 * the same non-interactive intent to assistive tech without blocking the
 * tap handler.
 *
 * Layout (matches the Phase 2 reference mock exactly —
 * .asdlc/generated/2-business-spec/screens/html/screen-006--station-list.html,
 * per explicit user request "buat seperti pada mock saja"): two titled
 * sections — "Stasiun MVP Aktif" (3-column grid, 3 active tiles) and
 * "Skema Mendatang (Belum Tersedia)" (3-column grid, 12 placeholder
 * tiles) — rather than one flat 15-tile grid. `stations` (already ordered
 * active-first by stationRepo.ts) is split into the two groups here by
 * `isActive`, so tile order and grouping match without stationRepo.ts
 * needing to know about the section split. Every tile keeps the same
 * `role="listitem"` / `data-testid="station-tile-{id}"` / `aria-disabled`
 * contract regardless of which section it renders in, so existing
 * tap/navigate behavior and tests are unaffected by the grouping.
 *
 * Per-station icon: exact SVG path data transcribed from the reference
 * mock (Lucide-style, 24px viewBox, stroke 1.5, `currentColor`) — one
 * icon per active station type (weighbridge/grading/cages-track) and one
 * per each of the 12 canonical future-station names the mock defines
 * (Sterilizer, Thresher, Press, Clarification, Kernel Plant, Boiler,
 * Effluent Treatment, Loading Ramp, Digester, Engine Room, Water
 * Treatment, Bulking Storage). Placeholder icon is looked up by
 * `station.name` (case-insensitive) since `station.type` for every
 * placeholder is just the generic `'other'` value — a name not in the
 * known set (e.g. differently-seeded local data) falls back to a plain
 * neutral square outline rather than throwing. Icon markup is static/
 * hardcoded (never includes `station.name`/user data in the HTML string
 * itself, which is rendered separately as text), so `v-html` here is
 * injecting only fixed, developer-authored SVG content — not an XSS risk.
 *
 * entity-catalog v7 (Mills Setting feature): active tiles additionally
 * consult `station.icon` (an Admin/Mill-Management-set override, synced
 * from the server) BEFORE the type-based default above — see
 * ICON_OVERRIDES / `iconInnerHtml()`. Disabled/placeholder tiles never
 * receive an override (business_logic step 3), and an unset/unrecognized
 * `station.icon` value falls through to the same type-based default as
 * before this feature — tile color/shadow/radius/layout are untouched.
 */
import { computed, ref } from 'vue'
import type { StationSlot, StationType } from '@/services/stationRepo'

const props = withDefaults(
  defineProps<{
    stations: StationSlot[]
    /**
     * business_logic step 2/3 — hasDraft per active station type (current
     * user's own draft_ongoing/draft_paused record on that station), keyed
     * by StationType. Drives the active tile's red-vs-black background —
     * see `tileStyle()` below. Not required for disabled/placeholder
     * tiles, whose color logic is unaffected.
     */
    draftStatusByType?: Partial<Record<StationType, boolean>>
  }>(),
  {
    draftStatusByType: () => ({}),
  },
)

const emit = defineEmits<{
  navigate: [type: StationType]
}>()

const activeStations = computed(() => props.stations.filter((station) => station.isActive))
const placeholderStations = computed(() => props.stations.filter((station) => !station.isActive))

const ACTIVE_ICONS: Record<'weighbridge' | 'grading' | 'cages-track', string> = {
  weighbridge:
    '<rect x="2" y="7" width="20" height="10" rx="1"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><line x1="6" y1="17" x2="6" y2="20"/><line x1="18" y1="17" x2="18" y2="20"/>',
  grading: '<circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="12"/><line x1="12" y1="12" x2="16" y2="14"/>',
  'cages-track':
    '<rect x="3" y="4" width="7" height="16" rx="1"/><rect x="14" y="4" width="7" height="16" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/>',
}

const PLACEHOLDER_ICONS: Record<string, string> = {
  sterilizer: '<rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/>',
  thresher: '<circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="16" y2="12"/>',
  press: '<path d="M4 20V10l8-6 8 6v10"/><line x1="12" y1="14" x2="12" y2="20"/>',
  clarification: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
  'kernel plant': '<rect x="5" y="3" width="14" height="18" rx="1"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/>',
  boiler: '<path d="M6 21V9a6 6 0 0 1 12 0v12"/><line x1="6" y1="15" x2="18" y2="15"/>',
  'effluent treatment': '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
  'loading ramp': '<rect x="3" y="10" width="18" height="8" rx="1"/><line x1="3" y1="10" x2="8" y2="4" stroke-linejoin="round"/>',
  digester: '<rect x="4" y="4" width="16" height="16" rx="8"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
  'engine room': '<rect x="6" y="2" width="12" height="20" rx="1"/><line x1="6" y1="8" x2="18" y2="8"/><line x1="6" y1="14" x2="18" y2="14"/>',
  'water treatment': '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
  'bulking storage': '<rect x="4" y="6" width="16" height="14" rx="1"/><path d="M8 6V4h8v2"/>',
}

const FALLBACK_ICON = '<rect x="4" y="4" width="16" height="16" rx="2"/>'

/**
 * entity-catalog v7 (Mills Setting feature) — optional per-station icon
 * override. Keys match `MillSettingService::SUPPORTED_ICONS` on the
 * backend (the picker's controlled vocabulary in screen-034's Mills
 * Setting form) exactly, lowercase. Only consulted for ACTIVE stations
 * (business_logic step 3 — disabled/placeholder tiles never receive an
 * override, regardless of `station.icon`). Same hand-authored 24-viewBox
 * Lucide-style path convention as ACTIVE_ICONS/PLACEHOLDER_ICONS above.
 */
const ICON_OVERRIDES: Record<string, string> = {
  gauge: '<path d="M12 12l4-4"/><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 8.5 6"/>',
  layers: '<path d="M12 3 3 8l9 5 9-5-9-5z"/><path d="M3 13l9 5 9-5"/>',
  package: '<path d="M21 8 12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><line x1="12" y1="13" x2="12" y2="21"/>',
  truck: '<rect x="1" y="7" width="13" height="10" rx="1"/><path d="M14 10h4l3 3v4h-7"/><circle cx="6" cy="19" r="2"/><circle cx="17" cy="19" r="2"/>',
  scale: '<line x1="12" y1="3" x2="12" y2="21"/><path d="M5 7h14"/><path d="M5 7 2 13a3 3 0 0 0 6 0z"/><path d="M19 7l-3 6a3 3 0 0 0 6 0z"/>',
  warehouse: '<path d="M3 10 12 4l9 6v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M8 20v-6h8v6"/>',
  factory: '<path d="M3 21V10l6 4v-4l6 4v-4l6 4v7z"/><line x1="3" y1="21" x2="21" y2="21"/>',
  container: '<rect x="2" y="6" width="20" height="12" rx="1"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="2" y1="14" x2="22" y2="14"/>',
  box: '<rect x="4" y="4" width="16" height="16" rx="1"/>',
  boxes: '<rect x="2" y="10" width="8" height="10" rx="1"/><rect x="14" y="10" width="8" height="10" rx="1"/><path d="M6 10V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4"/>',
}

/**
 * business_logic step 3 / edge_case_handling — active tile background:
 * red (`--color-station-red` / #D20000, same token the tile already used
 * for every active tile before this change) when the station's type has
 * an in-progress draft (ongoing OR paused — both map to the same red,
 * not further distinguished per edge_case_handling), else a dark neutral
 * (#1F2937, matching the "Text Primary" color already used elsewhere in
 * this app — e.g. HomeView.vue's `.brand-name`/`.welcome-text`). Icon
 * color is unaffected (`currentColor` stays white via `.station-tile--active`'s
 * `color: #ffffff`, applied by both style variants below).
 */
function activeTileStyle(station: StationSlot): Record<string, string> {
  const hasDraft = props.draftStatusByType[station.type] === true

  return {
    backgroundColor: hasDraft ? '#d20000' : '#1f2937',
  }
}

function iconInnerHtml(station: StationSlot): string {
  if (station.isActive) {
    const override = station.icon ? ICON_OVERRIDES[station.icon.trim().toLowerCase()] : undefined
    if (override) {
      return override
    }

    if (station.type in ACTIVE_ICONS) {
      return ACTIVE_ICONS[station.type as keyof typeof ACTIVE_ICONS]
    }
  }

  return PLACEHOLDER_ICONS[station.name.trim().toLowerCase()] ?? FALLBACK_ICON
}

const infoMessage = ref<string | null>(null)
let infoMessageTimeout: ReturnType<typeof setTimeout> | null = null

function onTileTap(station: StationSlot) {
  if (station.isActive) {
    emit('navigate', station.type)

    return
  }

  showInfoMessage(`${station.name} belum tersedia.`)
}

/**
 * business_logic step 4 — inline "belum tersedia" info message, shown for
 * a few seconds then auto-dismissed (rather than requiring a manual
 * dismiss action, since it is purely informational and does not block any
 * flow).
 */
function showInfoMessage(message: string) {
  infoMessage.value = message

  if (infoMessageTimeout) {
    clearTimeout(infoMessageTimeout)
  }

  infoMessageTimeout = setTimeout(() => {
    infoMessage.value = null
  }, 3000)
}
</script>

<template>
  <div class="station-grid-wrapper">
    <p v-if="infoMessage" class="info-message" role="status" data-testid="station-info-message">
      {{ infoMessage }}
    </p>

    <template v-if="activeStations.length > 0">
      <p class="section-title">Stasiun MVP Aktif</p>
      <div class="station-grid" role="list" aria-label="Daftar stasiun aktif">
        <button
          v-for="station in activeStations"
          :key="station.id"
          type="button"
          role="listitem"
          class="station-tile station-tile--active"
          :style="activeTileStyle(station)"
          :aria-disabled="false"
          :data-testid="`station-tile-${station.id}`"
          @click="onTileTap(station)"
        >
          <svg
            class="station-tile-icon"
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            v-html="iconInnerHtml(station)"
          />
          <span class="station-tile-name">{{ station.name }}</span>
        </button>
      </div>
    </template>

    <template v-if="placeholderStations.length > 0">
      <p class="section-title section-title--spaced">Skema Mendatang (Belum Tersedia)</p>
      <div class="station-grid" role="list" aria-label="Daftar stasiun mendatang">
        <button
          v-for="station in placeholderStations"
          :key="station.id"
          type="button"
          role="listitem"
          class="station-tile station-tile--disabled"
          :aria-disabled="true"
          :data-testid="`station-tile-${station.id}`"
          @click="onTileTap(station)"
        >
          <svg
            class="station-tile-icon"
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            v-html="iconInnerHtml(station)"
          />
          <span class="station-tile-name">{{ station.name }}</span>
          <span class="station-tile-hint">Belum tersedia</span>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.station-grid-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-message {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background-color: #f3f4f6;
  color: #1f2937;
  font-family: 'Inter', sans-serif;
  font-size: 13px;
}

.section-title {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 700;
  color: #6b7280;
  font-family: 'Inter', sans-serif;
}

.section-title--spaced {
  margin-top: 20px;
}

.station-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.station-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 44px;
  /* Bumped from 10px 6px — user feedback: "card menu buat padding
     vertical lebih besar agar terlihat lebih baik" (bigger vertical
     padding on the tile so it looks better). Horizontal padding kept
     modest since the grid is 3-column and tight on width. */
  padding: 22px 6px;
  box-sizing: border-box;
  border: none;
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  font-family: inherit;
  /* uiux-spec component_patterns['station-tile'] shadow token 'card' */
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.station-tile-icon {
  flex-shrink: 0;
}

.station-tile--active {
  /* background-color is set inline via activeTileStyle() — red when
     draftStatusByType[station.type] is true, dark neutral otherwise. */
  color: #ffffff;
}

.station-tile--active:focus {
  outline: 2px solid #1f2937;
  outline-offset: 2px;
}

.station-tile--disabled {
  background-color: #f3f4f6;
  color: #9ca3af;
  cursor: default;
}

.station-tile-name {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.station-tile-hint {
  font-size: 11px;
  font-weight: 400;
}
</style>
