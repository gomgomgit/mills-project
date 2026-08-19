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
    port: 5173,
  },
  test: {
    environment: 'jsdom',
    // tests/e2e/*.spec.ts are Playwright specs (real browser, real
    // backend) — Vitest's default include glob (**/*.spec.ts) picks them
    // up too and fails trying to run test.describe() outside Playwright's
    // runner. Excluded here; run them via `npm run test:e2e` instead.
    exclude: ['**/node_modules/**', 'tests/e2e/**'],
  },
})
