import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * router — Vue Router instance + auth guard (router, shared-modules).
 *
 * Route table starts with placeholder stubs for /login and /home; per-screen
 * routes/components are added (and these placeholders replaced) by
 * impl-2-screen as each screen is implemented.
 *
 * Auth guard: any route without `meta.public === true` requires an
 * authenticated session. Session is restored from local storage on first
 * navigation — works fully offline per shared_decisions.auth (token does not
 * auto-expire while offline). Unauthenticated users are redirected to
 * /login with the originally requested path preserved in `redirect`.
 *
 * Note: uses createWebHistory (works for the Vite dev/web build). If the
 * packaged native (Capacitor) build hits deep-link/history issues under the
 * file:// origin, switch to createWebHashHistory — see setup_notes.
 */
const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    redirect: '/home',
  },
  {
    path: '/home',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { public: false },
  },
  {
    // screen-004--ganti-password-mobile / usecase-004--ganti-password-mobile.
    // meta.public is deliberately false — the auth guard below requires an
    // authenticated session, matching screen_tech_spec.auth_requirement
    // (authenticated; actors: operator, supervisor).
    path: '/settings/password',
    name: 'change-password',
    component: () => import('@/views/ChangePasswordView.vue'),
    meta: { public: false },
  },
  {
    // screen-006--station-list / usecase-006--station-list "Pilih
    // Stasiun". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `station-list` route name HomeView.vue
    // already references (see HomeView.vue's header comment) — no other
    // change needed there. `monitor-cages-track` (screen-009) is
    // registered further below.
    path: '/stations',
    name: 'station-list',
    component: () => import('@/views/StationListView.vue'),
    meta: { public: false },
  },
  {
    // screen-007--monitor-weighbridge / usecase-007--monitor-weighbridge
    // "Monitor Weighbridge". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-weighbridge` route name
    // StationListView.vue already references (see StationListView.vue's
    // header comment / known_issues) — no other change needed there.
    // `data-preview-weighbridge` (screen-013) is now registered below,
    // resolving this screen's own dangling reference (see
    // DataPreviewWeighbridgeView.vue's header comment). `weighbridge-form`
    // (screen-010) is now registered below too.
    path: '/stations/weighbridge/monitor',
    name: 'monitor-weighbridge',
    component: () => import('@/views/MonitorWeighbridgeView.vue'),
    meta: { public: false },
  },
  {
    // screen-010--form-weighbridge / usecase-010--form-weighbridge "Form
    // Weighbridge". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `weighbridge-form` route name
    // MonitorWeighbridgeView.vue already references (see that view's
    // header comment / known_issues) — no other change needed there. `:id`
    // route param carries the draft record's id, matching the
    // `router.push({ name: 'weighbridge-form', params: { id } })` calls
    // already made by MonitorWeighbridgeView.vue.
    path: '/stations/weighbridge/form/:id',
    name: 'weighbridge-form',
    component: () => import('@/views/FormWeighbridgeView.vue'),
    meta: { public: false },
  },
  {
    // screen-013--data-preview-weighbridge /
    // usecase-013--data-preview-weighbridge "Data Preview Weighbridge".
    // meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `data-preview-weighbridge` route name
    // MonitorWeighbridgeView.vue already references (see that view's
    // header comment / known_issues) — no other change needed there.
    //
    // Path per screen_tech_spec is exactly `/stations/weighbridge/preview`
    // (no id segment) — MonitorWeighbridgeView.vue's current 'Buka Data
    // Preview' call (`router.push({ name: 'data-preview-weighbridge' })`)
    // passes no id, matching that literal path. `:id?` is added here as an
    // OPTIONAL trailing param (matches `/stations/weighbridge/preview` when
    // absent, per vue-router 4 optional-param semantics) so this route
    // still satisfies this screen's own business_logic step 1 ("load
    // weighbridge_record by id") for any future/other caller that does
    // pass one (same `:id` convention as `weighbridge-form` above) —
    // without breaking the zero-param path the current caller uses. See
    // DataPreviewWeighbridgeView.vue's header comment / known_issues for
    // the resulting behavior when no id is supplied (always renders the
    // "record not found" state until screen-007 is revised to pass one).
    path: '/stations/weighbridge/preview/:id?',
    name: 'data-preview-weighbridge',
    component: () => import('@/views/DataPreviewWeighbridgeView.vue'),
    meta: { public: false },
  },
  {
    // screen-008--monitor-grading / usecase-008--monitor-grading "Monitor
    // Grading". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-grading` route name
    // StationListView.vue already references (see StationListView.vue's
    // header comment / known_issues) — no other change needed there.
    // `monitor-cages-track` (screen-009) is registered further below.
    // `grading-form` (screen-011) is now registered further below too
    // (screen-011--form-grading). `data-preview-grading` (screen-014) is
    // now registered below too, resolving this screen's own dangling
    // reference (see DataPreviewGradingView.vue's header comment).
    path: '/stations/grading/monitor',
    name: 'monitor-grading',
    component: () => import('@/views/MonitorGradingView.vue'),
    meta: { public: false },
  },
  {
    // screen-014--data-preview-grading / usecase-014--data-preview-grading
    // "Data Preview Grading". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `data-preview-grading` route name
    // MonitorGradingView.vue already references (see that view's header
    // comment / known_issues) — no other change needed there.
    //
    // Path per screen_tech_spec is exactly `/stations/grading/preview`
    // (no id segment) — MonitorGradingView.vue's current 'Buka Data
    // Preview' call (`router.push({ name: 'data-preview-grading' })`)
    // passes no id, matching that literal path. `:id?` is added here as
    // an OPTIONAL trailing param (matches `/stations/grading/preview`
    // when absent, per vue-router 4 optional-param semantics), same
    // approach as `data-preview-weighbridge` (screen-013) above, so this
    // route still satisfies this screen's own business_logic step 1
    // ("load grading_record + grading_detail rows by id") for any
    // future/other caller that does pass one — without breaking the
    // zero-param path the current caller uses. See
    // DataPreviewGradingView.vue's header comment / known_issues for the
    // resulting behavior when no id is supplied (always renders the
    // "record not found" state until screen-008 is revised to pass one).
    path: '/stations/grading/preview/:id?',
    name: 'data-preview-grading',
    component: () => import('@/views/DataPreviewGradingView.vue'),
    meta: { public: false },
  },
  {
    // screen-009--monitor-cages-track / usecase-009--monitor-cages-track
    // "Monitor Cages Track". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-cages-track` route name
    // StationListView.vue already references (see StationListView.vue's
    // header comment / known_issues) — no other change needed there. This
    // resolves screen-006's dangling reference to `monitor-cages-track`.
    // `cages-track-form` (screen-012) is now registered further below.
    // `data-preview-cages-track` (screen-015) is now registered further
    // below too, resolving this screen's own dangling reference (see
    // DataPreviewCagesTrackView.vue's header comment) — though the
    // 'Data Preview' button call itself still passes no id, so it always
    // hits that screen's "record not found" state (see this screen's own
    // known_issues, mirrored from DataPreviewCagesTrackView.vue's).
    path: '/stations/cages-track/monitor',
    name: 'monitor-cages-track',
    component: () => import('@/views/MonitorCagesTrackView.vue'),
    meta: { public: false },
  },
  {
    // screen-011--form-grading / usecase-011--form-grading "Form Grading".
    // meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `grading-form` route name
    // MonitorGradingView.vue already references (see that view's header
    // comment / known_issues) — no other change needed there. `:id` route
    // param carries the draft record's id, matching the
    // `router.push({ name: 'grading-form', params: { id } })` calls
    // already made by MonitorGradingView.vue — same `/:id` param
    // convention as `weighbridge-form` (screen-010) above. This resolves
    // screen-008's dangling reference to `grading-form`. Note: this
    // screen's tech spec lists the route path as `/stations/grading/form`
    // (without the id segment); the `:id` param is added here because
    // business_logic step 1 requires loading the draft by route param and
    // MonitorGradingView.vue already navigates with `params: { id }` —
    // documented in this screen's implementation_notes.
    path: '/stations/grading/form/:id',
    name: 'grading-form',
    component: () => import('@/views/FormGradingView.vue'),
    meta: { public: false },
  },
  {
    // screen-012--form-cages-track / usecase-012--form-cages-track "Form
    // Cages Track". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `cages-track-form` route name
    // MonitorCagesTrackView.vue already references (see that view's
    // header comment / known_issues) — no other change needed there. `:id`
    // route param carries the draft record's id, matching the
    // `router.push({ name: 'cages-track-form', params: { id } })` calls
    // already made by MonitorCagesTrackView.vue — same `/:id` param
    // convention as `weighbridge-form` (screen-010) / `grading-form`
    // (screen-011) above. This resolves screen-009's dangling reference to
    // `cages-track-form`. Note: this screen's tech spec lists the route
    // path as `/stations/cages-track/form` (without the id segment); the
    // `:id` param is added here for the same reason as `grading-form`
    // above — documented in this screen's implementation_notes.
    path: '/stations/cages-track/form/:id',
    name: 'cages-track-form',
    component: () => import('@/views/FormCagesTrackView.vue'),
    meta: { public: false },
  },
  {
    // screen-015--data-preview-cages-track /
    // usecase-015--data-preview-cages-track "Data Preview Cages Track".
    // meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Fills in the `data-preview-cages-track` route name
    // MonitorCagesTrackView.vue already references (see that view's
    // header comment / known_issues) — no other change needed there. This
    // resolves screen-009's dangling reference to
    // `data-preview-cages-track`.
    //
    // Path per screen_tech_spec is exactly `/stations/cages-track/preview`
    // (no id segment) — MonitorCagesTrackView.vue's current 'Data
    // Preview' call (`router.push({ name: 'data-preview-cages-track' })`)
    // passes no id, matching that literal path. `:id?` is added here as
    // an OPTIONAL trailing param (matches `/stations/cages-track/preview`
    // when absent, per vue-router 4 optional-param semantics), same
    // approach as `data-preview-weighbridge` (screen-013) /
    // `data-preview-grading` (screen-014) above, so this route still
    // satisfies this screen's own business_logic step 1 ("load
    // cages_track_record + cages_tipped_time rows by id") for any
    // future/other caller that does pass one — without breaking the
    // zero-param path the current caller uses. See
    // DataPreviewCagesTrackView.vue's header comment / known_issues for
    // the resulting behavior when no id is supplied (always renders the
    // "record not found" state until screen-009 is revised to pass one).
    path: '/stations/cages-track/preview/:id?',
    name: 'data-preview-cages-track',
    component: () => import('@/views/DataPreviewCagesTrackView.vue'),
    meta: { public: false },
  },
  {
    // screen-037--monitor-threshing / usecase-037--monitor-threshing
    // "Monitor Threshing". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). NOTE: StationListView.vue (screen-006, out of this
    // task's scope) has NOT yet been updated to link to this route name —
    // reachable directly by URL / by a future revision of screen-006.
    path: '/stations/threshing/monitor',
    name: 'monitor-threshing',
    component: () => import('@/views/MonitorThreshingView.vue'),
    meta: { public: false },
  },
  {
    // screen-041--form-threshing / usecase-041--form-threshing "Form
    // Threshing". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). `:id` route param carries the draft record's id,
    // matching MonitorThreshingView.vue's `router.push({ name:
    // 'threshing-form', params: { id } })` calls, same `/:id` param
    // convention as `cages-track-form` above.
    path: '/stations/threshing/form/:id',
    name: 'threshing-form',
    component: () => import('@/views/FormThreshingView.vue'),
    meta: { public: false },
  },
  {
    // screen-045--data-preview-threshing /
    // usecase-045--data-preview-threshing "Data Preview Threshing".
    // meta.public is deliberately false. `:id?` optional trailing param,
    // same convention as `data-preview-cages-track` above.
    path: '/stations/threshing/preview/:id?',
    name: 'data-preview-threshing',
    component: () => import('@/views/DataPreviewThreshingView.vue'),
    meta: { public: false },
  },
  {
    // screen-038--monitor-pressing / usecase-038--monitor-pressing
    // "Monitor Pressing". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Mirrors 'monitor-threshing' exactly — Pressing's
    // structural sibling. NOTE: StationListView.vue (screen-006, out of
    // this task's scope) has NOT yet been updated to link to this route
    // name — reachable directly by URL / by a future revision of
    // screen-006 (same known issue already documented for Threshing).
    path: '/stations/pressing/monitor',
    name: 'monitor-pressing',
    component: () => import('@/views/MonitorPressingView.vue'),
    meta: { public: false },
  },
  {
    // screen-042--form-pressing / usecase-042--form-pressing "Form
    // Pressing". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). `:id` route param carries the draft record's id,
    // matching MonitorPressingView.vue's `router.push({ name:
    // 'pressing-form', params: { id } })` calls, same `/:id` param
    // convention as `threshing-form` above.
    path: '/stations/pressing/form/:id',
    name: 'pressing-form',
    component: () => import('@/views/FormPressingView.vue'),
    meta: { public: false },
  },
  {
    // screen-046--data-preview-pressing /
    // usecase-046--data-preview-pressing "Data Preview Pressing".
    // meta.public is deliberately false. `:id?` optional trailing param,
    // same convention as `data-preview-threshing` above.
    path: '/stations/pressing/preview/:id?',
    name: 'data-preview-pressing',
    component: () => import('@/views/DataPreviewPressingView.vue'),
    meta: { public: false },
  },
  {
    // screen-039--monitor-depricarping / usecase-039--monitor-depricarping
    // "Monitor Depricarping". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Mirrors 'monitor-pressing' exactly — Depricarping's
    // structural sibling. NOTE: StationListView.vue (screen-006, out of
    // this task's scope) has NOT yet been updated to link to this route
    // name — reachable directly by URL / by a future revision of
    // screen-006 (same known issue already documented for Threshing/
    // Pressing).
    path: '/stations/depricarping/monitor',
    name: 'monitor-depricarping',
    component: () => import('@/views/MonitorDepricarpingView.vue'),
    meta: { public: false },
  },
  {
    // screen-043--form-depricarping / usecase-043--form-depricarping "Form
    // Depricarping". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). `:id` route param carries the draft record's id,
    // matching MonitorDepricarpingView.vue's `router.push({ name:
    // 'depricarping-form', params: { id } })` calls, same `/:id` param
    // convention as `pressing-form` above.
    path: '/stations/depricarping/form/:id',
    name: 'depricarping-form',
    component: () => import('@/views/FormDepricarpingView.vue'),
    meta: { public: false },
  },
  {
    // screen-047--data-preview-depricarping /
    // usecase-047--data-preview-depricarping "Data Preview Depricarping".
    // meta.public is deliberately false. `:id?` optional trailing param,
    // same convention as `data-preview-pressing` above.
    path: '/stations/depricarping/preview/:id?',
    name: 'data-preview-depricarping',
    component: () => import('@/views/DataPreviewDepricarpingView.vue'),
    meta: { public: false },
  },
  {
    // screen-040--monitor-kernel-plant / usecase-040--monitor-kernel-plant
    // "Monitor Kernel Plant". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). Mirrors 'monitor-depricarping' exactly — Kernel Plant's
    // structural sibling. NOTE: StationListView.vue (screen-006, out of
    // this task's scope) has NOT yet been updated to link to this route
    // name — reachable directly by URL / by a future revision of
    // screen-006 (same known issue already documented for Threshing/
    // Pressing/Depricarping).
    path: '/stations/kernel-plant/monitor',
    name: 'monitor-kernel-plant',
    component: () => import('@/views/MonitorKernelPlantView.vue'),
    meta: { public: false },
  },
  {
    // screen-044--form-kernel-plant / usecase-044--form-kernel-plant "Form
    // Kernel Plant". meta.public is deliberately false, matching
    // screen_tech_spec.auth_requirement (authenticated; actors: operator,
    // supervisor). `:id` route param carries the draft record's id,
    // matching MonitorKernelPlantView.vue's `router.push({ name:
    // 'kernel-plant-form', params: { id } })` calls, same `/:id` param
    // convention as `depricarping-form` above.
    path: '/stations/kernel-plant/form/:id',
    name: 'kernel-plant-form',
    component: () => import('@/views/FormKernelPlantView.vue'),
    meta: { public: false },
  },
  {
    // screen-048--data-preview-kernel-plant /
    // usecase-048--data-preview-kernel-plant "Data Preview Kernel Plant".
    // meta.public is deliberately false. `:id?` optional trailing param,
    // same convention as `data-preview-depricarping` above.
    path: '/stations/kernel-plant/preview/:id?',
    name: 'data-preview-kernel-plant',
    component: () => import('@/views/DataPreviewKernelPlantView.vue'),
    meta: { public: false },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const authStore = useAuthStore()

  if (!authStore.initialized) {
    authStore.restoreSession()
  }

  if (to.meta.public) {
    return true
  }

  if (!authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  return true
})

export default router
