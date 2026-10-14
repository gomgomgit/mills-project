import { fileURLToPath, URL } from 'node:url'
// Imported from 'vitest/config' (not 'vite') so the `test` block below is
// type-checked and merged correctly — this re-exports Vite's defineConfig
// with the Vitest `test` option added to its config type.
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    vue({
      template: {
        compilerOptions: {
          // <jeep-sqlite> (App.vue) is a native custom element registered
          // by main.ts's jeepSqliteElements(window) call, not a Vue
          // component — without this, Vue's template compiler warns
          // "Failed to resolve component: jeep-sqlite" and tries (and
          // fails) to resolve it as one.
          isCustomElement: (tag) => tag === 'jeep-sqlite',
        },
      },
    }),
  ],
  resolve: {
    alias: {
      // Matches tsconfig.json's "paths": { "@/*": ["src/*"] } — needed here
      // too since Vite's bundler does not read tsconfig paths on its own.
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // 5173 is occupied by another project on this dev machine, so Vite kept
    // silently falling back to 5174 while the configured value still said
    // 5173 — strictPort makes a future collision fail loudly instead of
    // drifting to yet another port without anyone noticing.
    port: 5174,
    strictPort: true,
  },
  test: {
    environment: 'jsdom',
    // tests/e2e/*.spec.ts are Playwright specs (real browser, real
    // backend) — Vitest's default include glob (**/*.spec.ts) picks them
    // up too and fails trying to run test.describe() outside Playwright's
    // runner. Excluded here; run them via `npm run test:e2e` instead.
    //
    // tests/qa-run/** is the same thing for a different purpose: pelaksana
    // QA blackbox (lihat docs/qa/README.md), dijalankan dengan config
    // Playwright-nya sendiri. Tanpa dikecualikan di sini, `vitest run`
    // melaporkan 5 berkas gagal yang sebenarnya tidak pernah dimaksudkan
    // untuk berjalan di sini — dan kegagalan palsu yang selalu ada adalah
    // kegagalan yang berhenti dibaca orang.
    //
    // shots/** adalah hal yang sama sekali lagi, dan ia sempat terlewat:
    // shots/mobile.spec.ts (tangkapan layar bahan sales deck, commit 77a957f)
    // adalah spec Playwright dengan config-nya sendiri di shots/, tetapi
    // shots/ TIDAK ikut dikecualikan di sini. Akibatnya sejak commit itu
    // `vitest run` selalu melaporkan 1 berkas gagal collect ("Playwright Test
    // did not expect test() to be called here") — jadi suite mobile tidak
    // pernah keluar bersih lagi, dan angka baseline yang dikutip di mana-mana
    // ("131 berkas lulus") sebenarnya 131 lulus + 1 gagal. Ditutup 2026-10-14.
    exclude: ['**/node_modules/**', 'tests/e2e/**', 'tests/qa-run/**', 'shots/**'],
    // Dialog modal (ConfirmDialog/SyncResultDialog) di-<Teleport> ke <body>;
    // setup ini men-stub Teleport agar isinya tetap ada di dalam wrapper
    // spec layar. Lihat tests/setup/teleportStub.ts.
    setupFiles: ['tests/setup/teleportStub.ts'],
  },
})
