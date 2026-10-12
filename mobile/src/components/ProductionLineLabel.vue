<script setup lang="ts">
/**
 * Production Line yang sedang dipilih, dicetak di bawah judul layar.
 *
 * TEMUAN QA MB-HOME-03. Pilihan Production Line diingat perangkat dengan
 * benar, tetapi layar Monitor tidak menyebutnya sama sekali: operator yang
 * bekerja di dua line tidak punya satu penanda pun tentang line mana yang
 * sedang ia isi, dan satu record yang masuk ke line yang salah tidak dapat
 * dilihat dari layar mana pun sesudahnya.
 *
 * Dibaca dari localStorage, bukan dari API: Monitor adalah layar yang paling
 * sering dipakai tanpa koneksi, dan daftar Production Line tidak di-cache
 * lokal (lihat productionLineRepo). Station List menuliskan namanya setiap
 * kali memilih line dari daftar yang baru diambil server.
 *
 * Tidak merender apa pun bila namanya belum diketahui — satu label kosong
 * atau "—" lebih buruk daripada tidak ada label: ia terbaca seperti line
 * yang tidak bernama.
 */
import { computed } from 'vue'

import { readRememberedProductionLineName } from '@/services/activeStation'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const lineName = computed(() => readRememberedProductionLineName(authStore.currentUser?.id))
</script>

<template>
  <p v-if="lineName" class="production-line-label" data-testid="active-production-line">
    <span class="production-line-label__key">Production Line</span>
    <span class="production-line-label__value">{{ lineName }}</span>
  </p>
</template>

<style scoped>
.production-line-label {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin: 2px 0 0;
  font-size: 13px;
}

.production-line-label__key {
  color: #6b7280;
}

.production-line-label__value {
  font-weight: 600;
  color: #1f2937;
  /* Nama line ditulis bebas di web, jadi panjangnya tidak dapat diandalkan. */
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
