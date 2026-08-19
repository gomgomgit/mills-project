import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { defineCustomElements as jeepSqliteElements } from 'jeep-sqlite/loader'
import App from './App.vue'
import router from './router'
import { initLocalSchema, seedGradingParametersIfNeeded } from '@/services/localSchema'
import { query, run } from '@/services/localDb'

// Registers the <jeep-sqlite> custom element — @capacitor-community/sqlite's
// web-platform companion. Without this, SQLiteConnection.open() has no
// backing store and fails when the app runs in a plain browser (Vite dev
// server / any non-native shell), which is the only way this app is
// exercised in this environment (no Android/iOS build). Native builds
// ignore this element (CapacitorSQLite talks to the native plugin
// directly there) — registering it unconditionally is harmless either way.
jeepSqliteElements(window)

/**
 * bootstrap() — ordering matters here and Vue's own render cycle can't
 * express it:
 *   1. Create the <jeep-sqlite> element directly (not via App.vue's
 *      template) and append it to document.body BEFORE the Vue app
 *      mounts, so it exists in the DOM (and jeep-sqlite's internal plugin
 *      bridge can find it via document.querySelector) the moment the
 *      first SQLite call happens — a template-rendered element only
 *      exists AFTER app.mount() resolves, which is too late.
 *   2. `autoSave="true"` so writes actually persist to IndexedDB across
 *      full page reloads (default is in-memory-only for the session,
 *      which silently discarded local drafts on reload — caught via a
 *      real browser test: a draft created on Monitor Weighbridge vanished
 *      after navigating back to the same screen).
 *   3. Await schema creation (initLocalSchema(), idempotent CREATE TABLE
 *      IF NOT EXISTS) to fully complete before app.mount() — otherwise a
 *      screen's onMounted() query can race ahead of table creation on a
 *      fresh page load (also caught via a real browser test: "no such
 *      table: weighbridge_record" on a fast reload). This delays first
 *      paint by however long schema init takes (fast — a handful of
 *      CREATE TABLE IF NOT EXISTS statements), which is an acceptable
 *      trade for correctness.
 */
async function bootstrap(): Promise<void> {
  const jeepSqliteEl = document.createElement('jeep-sqlite')
  jeepSqliteEl.setAttribute('autoSave', 'true')
  jeepSqliteEl.setAttribute('wasmPath', '/assets')
  document.body.appendChild(jeepSqliteEl)
  await customElements.whenDefined('jeep-sqlite')

  await initLocalSchema()

  // Grading Parameter is global master data (no business_unit_id scoping,
  // unlike `seedDefaultStationsIfNeeded()` which is per-business-unit and
  // therefore wired into stores/auth.ts's login() instead) — seeded once
  // here, right after schema creation, rather than at login. Idempotent
  // (INSERT OR IGNORE on a deterministic id), so calling it on every app
  // boot is safe.
  await seedGradingParametersIfNeeded()

  // Dev-only bridge so Playwright (tests/e2e/*.spec.ts) can seed/inspect
  // local SQLite rows directly (e.g. the `station` reference table, which
  // has no in-app sync flow yet — see stationRepo.ts/localSchema.ts's
  // header comments). `import.meta.env.DEV` is false for `vite build`
  // (production/native builds), so this never ships outside `vite dev`.
  if (import.meta.env.DEV) {
    ;(window as unknown as { __mslTestDb: { query: typeof query; run: typeof run } }).__mslTestDb = {
      query,
      run,
    }
  }

  const app = createApp(App)
  app.use(createPinia())
  app.use(router)
  app.mount('#app')
}

void bootstrap()
