<script setup lang="ts">
// Root shell — screen views are registered as routes; see src/router.
import { computed, watchEffect } from 'vue'
import FloatingClock from '@/components/FloatingClock.vue'
import AiAssistantPanel from '@/components/AiAssistantPanel.vue'
import AiAssistantBubble from '@/components/AiAssistantBubble.vue'
import LoadingOverlay from '@/components/loading/LoadingOverlay.vue'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { floatingSafeBottomPx } from '@/utils/floatingSafeArea'

const aiAssistantStore = useAiAssistantStore()
const floatingClockStore = useFloatingClockStore()
const authStore = useAuthStore()

// Ruang aman bawah untuk elemen mengambang (audit 2026-10-04): bubble AI
// dan jam mengambang menutupi tombol footer (Load Data, Clear/Simpan),
// pesan error, dan kartu cakupan laporan di 390px. Setiap layar adalah
// satu <main> dengan min-height 100vh dan footer `margin-top: auto`,
// jadi menambah padding-bottom pada root layar menggeser footer ke atas
// elemen mengambang dan menyisakan ruang gulir di akhir konten. Nilainya
// mengikuti elemen yang sedang aktif (keduanya bisa dimatikan dari menu).
//
// Audit 2026-10-05: padding saja hanya menjamin UJUNG gulir — di posisi
// awal layar bubble tetap menimpa kontrol yang kebetulan ada di pojok kanan
// bawah (Clear/Simpan Form Sterilizer di 390px). Karena itu elemen
// mengambang kini duduk di DOK bawah yang buram setinggi ruang aman yang
// sama: konten menggulir di belakangnya dan tidak pernah tampak/terjangkau
// di bawah bubble/jam pada posisi diam mana pun.
const safeBottom = computed(() =>
  floatingSafeBottomPx({
    bubbleEnabled: aiAssistantStore.bubbleEnabled,
    clockEnabled: floatingClockStore.enabled,
  }),
)

watchEffect(() => {
  const root = document.documentElement
  if (safeBottom.value > 0) {
    root.style.setProperty('--floating-safe-bottom', `${safeBottom.value}px`)
    root.dataset.floatingSafeArea = ''
  } else {
    root.style.removeProperty('--floating-safe-bottom')
    delete root.dataset.floatingSafeArea
  }
})
</script>

<template>
  <router-view v-slot="{ Component }">
    <component :is="Component" class="app-route-view" />
  </router-view>
  <!-- Dok bawah: pita buram tempat bubble/jam duduk (lihat skrip). Menelan
       ketukan supaya kontrol yang tergulir di belakangnya tidak terpicu. -->
  <div v-if="safeBottom > 0" class="floating-dock" data-testid="floating-dock" aria-hidden="true" />
  <FloatingClock />
  <AiAssistantBubble />
  <AiAssistantPanel />
  <!-- Logout menunggu POST /api/logout dari menu yang sudah tertutup —
       satu lapisan global alih-alih penanda di 49 layar (audit loading
       state 2026-10-05). -->
  <LoadingOverlay v-if="authStore.loggingOut" label="Keluar…" />
</template>

<style>
/*
 * Tidak di-scope: berlaku untuk root <main> setiap layar (lihat
 * watchEffect di atas). `#app` menaikkan spesifisitas di atas aturan
 * `padding` ber-scope milik tiap layar; hanya aktif saat ada elemen
 * mengambang, jadi tampilan tanpa bubble/jam tidak berubah.
 */
:root[data-floating-safe-area] #app .app-route-view {
  padding-bottom: var(--floating-safe-bottom);
}

/* Di bawah bubble (999) & jam (1000), di atas konten layar (menu ≤ 20);
   dialog/panel/overlay memakai 1100+ sehingga tetap menutupi dok. */
.floating-dock {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  height: var(--floating-safe-bottom);
  z-index: 998;
  background-color: #ffffff;
  border-top: 1px solid #f3f4f6;
}
</style>

<!--
  The <jeep-sqlite> element itself is created directly in main.ts (appended
  to document.body before this app mounts), not rendered here — it must
  exist in the DOM and be fully initialized (web store + local schema)
  BEFORE any screen component's onMounted() can query/write local data,
  which requires synchronous ordering that Vue's own render cycle can't
  guarantee (a template-rendered element only exists after app.mount()
  resolves, which is too late). See main.ts's bootstrap() for the full
  sequence.
-->
