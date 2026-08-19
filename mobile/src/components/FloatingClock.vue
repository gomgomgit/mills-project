<script setup lang="ts">
/**
 * Always-on-top clock overlay, toggled via each screen's hamburger
 * nav-menu ("Aktifkan/Nonaktifkan Jam Mengambang"). Mounted once, globally,
 * in App.vue — not per-screen — so it persists across navigation without
 * needing to be duplicated into every view like the nav-menu itself is.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { useFloatingClockStore } from '@/stores/floatingClock'

const floatingClockStore = useFloatingClockStore()

const now = ref(new Date())
let intervalId: ReturnType<typeof setInterval> | undefined

function formatTime(date: Date): string {
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

onMounted(() => {
  intervalId = setInterval(() => {
    now.value = new Date()
  }, 1000)
})

onUnmounted(() => {
  if (intervalId !== undefined) clearInterval(intervalId)
})
</script>

<template>
  <div v-if="floatingClockStore.enabled" class="floating-clock" data-testid="floating-clock" role="status" aria-label="Jam saat ini">
    {{ formatTime(now) }}
  </div>
</template>

<style scoped>
.floating-clock {
  position: fixed;
  bottom: 16px;
  right: 16px;
  z-index: 1000;
  padding: 8px 14px;
  border-radius: 999px;
  background-color: rgba(31, 41, 55, 0.85);
  color: #ffffff;
  font-family: 'Inter', sans-serif;
  font-size: 14px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  pointer-events: none;
  user-select: none;
}
</style>
