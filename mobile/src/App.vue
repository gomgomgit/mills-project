<script setup lang="ts">
// Root shell — screen views are registered as routes; see src/router.
import FloatingClock from '@/components/FloatingClock.vue'
</script>

<template>
  <router-view />
  <FloatingClock />
</template>

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

