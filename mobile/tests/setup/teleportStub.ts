/**
 * ConfirmDialog.vue / SyncResultDialog.vue me-<Teleport> overlay-nya ke
 * <body> (audit 2026-10-04 — backdrop harus menutup seluruh viewport
 * termasuk header). Spec layar mencari isi dialog lewat `wrapper.find()`,
 * yang tidak melihat node yang sudah dipindah ke <body>; stub global ini
 * merender isi Teleport di tempat (`<teleport-stub>`). Spec yang memang
 * menguji teleport-nya (ConfirmDialog.spec.ts, SyncResultDialog.spec.ts)
 * mematikan stub ini per-mount dengan `global: { stubs: { teleport: false } }`.
 */
import { config } from '@vue/test-utils'

config.global.stubs = { ...config.global.stubs, teleport: true }
