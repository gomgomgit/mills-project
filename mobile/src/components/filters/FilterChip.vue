<script setup lang="ts">
/**
 * FilterChip — chip cakupan aktif yang tidak dapat diubah dari layar ini
 * (mis. "Mill: Business Unit A" bagi pengguna terikat satu mill, atau
 * "Production Line: Line 2" yang menyertai angka laporan; audit desain
 * filter 2026-10-05). Teksnya tetap "Label: Nilai" sehingga pembaca layar
 * dan spec yang memeriksa isi teks membaca hal yang sama seperti
 * sebelumnya. Atribut lain (data-testid) jatuh ke elemen root.
 */
defineProps<{
  label: string
  value: string
}>()
</script>

<template>
  <p class="filter-chip">
    <span class="filter-chip-label">{{ label }}:&#32;</span>
    <strong class="filter-chip-value">{{ value }}</strong>
  </p>
</template>

<style scoped>
.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 0;
  max-width: 100%;
  min-height: 28px;
  margin: 0;
  padding: 0 10px;
  border: 1px solid #d1e7da;
  border-radius: 999px;
  background-color: #ffffff;
  font-size: 12px;
  box-sizing: border-box;
}

.filter-chip-label {
  flex-shrink: 0;
  white-space: pre;
  color: #6b7280;
}

.filter-chip-value {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
  color: #1a6f48;
}
</style>
