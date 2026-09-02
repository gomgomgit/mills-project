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
  {
    // screen-061--monitor-solid-waste-disposal /
    // usecase-061--monitor-solid-waste-disposal "Monitor Solid Waste
    // Disposal". meta.public deliberately false (authenticated; actors:
    // operator, supervisor). Fills in the `monitor-solid-waste-disposal`
    // route name StationListView.vue already references (MONITOR_ROUTE_NAMES).
    path: '/stations/solid-waste-disposal/monitor',
    name: 'monitor-solid-waste-disposal',
    component: () => import('@/views/MonitorSolidWasteDisposalView.vue'),
    meta: { public: false },
  },
  {
    // screen-071--form-solid-waste-disposal /
    // usecase-062--form-solid-waste-disposal "Form Solid Waste Disposal".
    // `:id` route param carries the draft record's id, same convention as
    // `cages-track-form`/`pressing-form` above.
    path: '/stations/solid-waste-disposal/form/:id',
    name: 'solid-waste-disposal-form',
    component: () => import('@/views/FormSolidWasteDisposalView.vue'),
    meta: { public: false },
  },
  {
    // screen-081--data-preview-solid-waste-disposal /
    // usecase-063--data-preview-solid-waste-disposal "Data Preview Solid
    // Waste Disposal". `:id?` optional trailing param, same convention as
    // `data-preview-cages-track`/`data-preview-kernel-plant` above.
    path: '/stations/solid-waste-disposal/preview/:id?',
    name: 'data-preview-solid-waste-disposal',
    component: () => import('@/views/DataPreviewSolidWasteDisposalView.vue'),
    meta: { public: false },
  },
  {
    // screen-062--monitor-process-water / usecase-067--monitor-process-water
    // "Monitor Process Water". meta.public deliberately false
    // (authenticated; actors: operator, supervisor). Fills in the
    // `monitor-process-water` route name StationListView.vue already
    // references (MONITOR_ROUTE_NAMES). Process Water follows the same
    // hourly-grid pattern as Threshing/Pressing/Kernel Plant — mirrors
    // `monitor-threshing` exactly.
    path: '/stations/process-water/monitor',
    name: 'monitor-process-water',
    component: () => import('@/views/MonitorProcessWaterView.vue'),
    meta: { public: false },
  },
  {
    // screen-072--form-process-water / usecase-068--form-process-water
    // "Form Process Water". `:id` route param carries the draft record's
    // id, same convention as `threshing-form`/`pressing-form` above.
    path: '/stations/process-water/form/:id',
    name: 'process-water-form',
    component: () => import('@/views/FormProcessWaterView.vue'),
    meta: { public: false },
  },
  {
    // screen-082--data-preview-process-water /
    // usecase-069--data-preview-process-water "Data Preview Process
    // Water". `:id?` optional trailing param, same convention as
    // `data-preview-threshing`/`data-preview-solid-waste-disposal` above.
    path: '/stations/process-water/preview/:id?',
    name: 'data-preview-process-water',
    component: () => import('@/views/DataPreviewProcessWaterView.vue'),
    meta: { public: false },
  },
  {
    // screen-063--monitor-kernel-dispatch /
    // usecase-073--monitor-kernel-dispatch "Monitor Kernel Dispatch".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-kernel-dispatch` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    path: '/stations/kernel-dispatch/monitor',
    name: 'monitor-kernel-dispatch',
    component: () => import('@/views/MonitorKernelDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-073--form-kernel-dispatch /
    // usecase-074--form-kernel-dispatch "Form Kernel Dispatch". `:id`
    // route param carries the draft record's id, same convention as
    // `solid-waste-disposal-form`/`process-water-form` above.
    path: '/stations/kernel-dispatch/form/:id',
    name: 'kernel-dispatch-form',
    component: () => import('@/views/FormKernelDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-083--data-preview-kernel-dispatch /
    // usecase-075--data-preview-kernel-dispatch "Data Preview Kernel
    // Dispatch". `:id?` optional trailing param, same convention as
    // `data-preview-solid-waste-disposal`/`data-preview-process-water`
    // above.
    path: '/stations/kernel-dispatch/preview/:id?',
    name: 'data-preview-kernel-dispatch',
    component: () => import('@/views/DataPreviewKernelDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-064--monitor-cpo-dispatch /
    // usecase-079--monitor-cpo-dispatch "Monitor CPO Dispatch".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-cpo-dispatch` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    path: '/stations/cpo-dispatch/monitor',
    name: 'monitor-cpo-dispatch',
    component: () => import('@/views/MonitorCpoDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-074--form-cpo-dispatch /
    // usecase-080--form-cpo-dispatch "Form CPO Dispatch". `:id`
    // route param carries the draft record's id, same convention as
    // `kernel-dispatch-form`/`solid-waste-disposal-form` above.
    path: '/stations/cpo-dispatch/form/:id',
    name: 'cpo-dispatch-form',
    component: () => import('@/views/FormCpoDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-084--data-preview-cpo-dispatch /
    // usecase-081--data-preview-cpo-dispatch "Data Preview CPO
    // Dispatch". `:id?` optional trailing param, same convention as
    // `data-preview-kernel-dispatch`/`data-preview-solid-waste-disposal`
    // above.
    path: '/stations/cpo-dispatch/preview/:id?',
    name: 'data-preview-cpo-dispatch',
    component: () => import('@/views/DataPreviewCpoDispatchView.vue'),
    meta: { public: false },
  },
  {
    // screen-065--monitor-effluent-plant /
    // usecase-085--monitor-effluent-plant "Monitor Effluent Plant".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-effluent-plant` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    // Effluent Plant follows the same hourly-grid pattern as Process
    // Water/Threshing/Pressing/Kernel Plant — mirrors
    // `monitor-process-water` exactly.
    path: '/stations/effluent-plant/monitor',
    name: 'monitor-effluent-plant',
    component: () => import('@/views/MonitorEffluentPlantView.vue'),
    meta: { public: false },
  },
  {
    // screen-075--form-effluent-plant /
    // usecase-086--form-effluent-plant "Form Effluent Plant". `:id` route
    // param carries the draft record's id, same convention as
    // `process-water-form`/`kernel-dispatch-form` above.
    path: '/stations/effluent-plant/form/:id',
    name: 'effluent-plant-form',
    component: () => import('@/views/FormEffluentPlantView.vue'),
    meta: { public: false },
  },
  {
    // screen-085--data-preview-effluent-plant /
    // usecase-087--data-preview-effluent-plant "Data Preview Effluent
    // Plant". `:id?` optional trailing param, same convention as
    // `data-preview-process-water`/`data-preview-cpo-dispatch` above.
    path: '/stations/effluent-plant/preview/:id?',
    name: 'data-preview-effluent-plant',
    component: () => import('@/views/DataPreviewEffluentPlantView.vue'),
    meta: { public: false },
  },
  {
    // screen-066--monitor-storage-tank /
    // usecase-091--monitor-storage-tank "Monitor Storage Tank".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-storage-tank` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    // Storage Tank follows the same hourly-grid pattern as Effluent
    // Plant/Process Water/Threshing/Pressing/Kernel Plant — mirrors
    // `monitor-effluent-plant` exactly.
    path: '/stations/storage-tank/monitor',
    name: 'monitor-storage-tank',
    component: () => import('@/views/MonitorStorageTankView.vue'),
    meta: { public: false },
  },
  {
    // screen-076--form-storage-tank /
    // usecase-092--form-storage-tank "Form Storage Tank". `:id` route
    // param carries the draft record's id, same convention as
    // `effluent-plant-form`/`process-water-form` above.
    path: '/stations/storage-tank/form/:id',
    name: 'storage-tank-form',
    component: () => import('@/views/FormStorageTankView.vue'),
    meta: { public: false },
  },
  {
    // screen-086--data-preview-storage-tank /
    // usecase-093--data-preview-storage-tank "Data Preview Storage Tank".
    // `:id?` optional trailing param, same convention as
    // `data-preview-effluent-plant`/`data-preview-process-water` above.
    path: '/stations/storage-tank/preview/:id?',
    name: 'data-preview-storage-tank',
    component: () => import('@/views/DataPreviewStorageTankView.vue'),
    meta: { public: false },
  },
  {
    // screen-067--monitor-engine-room /
    // usecase-097--monitor-engine-room "Monitor Engine Room".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-engine-room` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    // Engine Room follows the same hourly-grid pattern as Storage Tank/
    // Effluent Plant/Process Water/Threshing/Pressing/Kernel Plant —
    // mirrors `monitor-storage-tank` exactly.
    path: '/stations/engine-room/monitor',
    name: 'monitor-engine-room',
    component: () => import('@/views/MonitorEngineRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-077--form-engine-room /
    // usecase-098--form-engine-room "Form Engine Room". `:id` route
    // param carries the draft record's id, same convention as
    // `storage-tank-form`/`effluent-plant-form` above.
    path: '/stations/engine-room/form/:id',
    name: 'engine-room-form',
    component: () => import('@/views/FormEngineRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-087--data-preview-engine-room /
    // usecase-099--data-preview-engine-room "Data Preview Engine Room".
    // `:id?` optional trailing param, same convention as
    // `data-preview-storage-tank`/`data-preview-effluent-plant` above.
    path: '/stations/engine-room/preview/:id?',
    name: 'data-preview-engine-room',
    component: () => import('@/views/DataPreviewEngineRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-068--monitor-boiler-room /
    // usecase-103--monitor-boiler-room "Monitor Boiler Room".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-boiler-room` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    // Boiler Room follows the same hourly-grid pattern as Engine Room/
    // Storage Tank/Effluent Plant/Process Water/Threshing/Pressing/Kernel
    // Plant — mirrors `monitor-engine-room` exactly.
    path: '/stations/boiler-room/monitor',
    name: 'monitor-boiler-room',
    component: () => import('@/views/MonitorBoilerRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-078--form-boiler-room /
    // usecase-104--form-boiler-room "Form Boiler Room". `:id` route
    // param carries the draft record's id, same convention as
    // `engine-room-form`/`storage-tank-form` above.
    path: '/stations/boiler-room/form/:id',
    name: 'boiler-room-form',
    component: () => import('@/views/FormBoilerRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-088--data-preview-boiler-room /
    // usecase-105--data-preview-boiler-room "Data Preview Boiler Room".
    // `:id?` optional trailing param, same convention as
    // `data-preview-engine-room`/`data-preview-storage-tank` above.
    path: '/stations/boiler-room/preview/:id?',
    name: 'data-preview-boiler-room',
    component: () => import('@/views/DataPreviewBoilerRoomView.vue'),
    meta: { public: false },
  },
  {
    // screen-069--monitor-clarification /
    // usecase-109--monitor-clarification "Monitor Clarification".
    // meta.public deliberately false (authenticated; actors: operator,
    // supervisor). Fills in the `monitor-clarification` route name
    // StationListView.vue already references (MONITOR_ROUTE_NAMES).
    // Clarification follows the same hourly-grid pattern as Boiler
    // Room/Engine Room/Storage Tank — mirrors `monitor-boiler-room` exactly.
    path: '/stations/clarification/monitor',
    name: 'monitor-clarification',
    component: () => import('@/views/MonitorClarificationView.vue'),
    meta: { public: false },
  },
  {
    // screen-079--form-clarification /
    // usecase-110--form-clarification "Form Clarification". `:id` route
    // param carries the draft record's id, same convention as
    // `boiler-room-form`/`engine-room-form` above.
    path: '/stations/clarification/form/:id',
    name: 'clarification-form',
    component: () => import('@/views/FormClarificationView.vue'),
    meta: { public: false },
  },
  {
    // screen-089--data-preview-clarification /
    // usecase-111--data-preview-clarification "Data Preview Clarification".
    // `:id?` optional trailing param, same convention as
    // `data-preview-boiler-room`/`data-preview-engine-room` above.
    path: '/stations/clarification/preview/:id?',
    name: 'data-preview-clarification',
    component: () => import('@/views/DataPreviewClarificationView.vue'),
    meta: { public: false },
  },
  {
    // screen-070--monitor-process-quality-control /
    // usecase-115--monitor-process-quality-control "Monitor Process
    // Quality Control". meta.public deliberately false (authenticated;
    // actors: operator, supervisor). Fills in the
    // `monitor-process-quality-control` route name StationListView.vue
    // already references (MONITOR_ROUTE_NAMES). Process Quality Control
    // follows the same hourly-grid pattern as Clarification/Boiler
    // Room/Engine Room/Storage Tank — mirrors `monitor-clarification`
    // exactly. This is the FINAL of the 10 MVP stations promoted
    // 2026-08-31.
    path: '/stations/process-quality-control/monitor',
    name: 'monitor-process-quality-control',
    component: () => import('@/views/MonitorProcessQualityControlView.vue'),
    meta: { public: false },
  },
  {
    // screen-080--form-process-quality-control /
    // usecase-116--form-process-quality-control "Form Process Quality
    // Control". `:id` route param carries the draft record's id, same
    // convention as `clarification-form`/`boiler-room-form` above.
    path: '/stations/process-quality-control/form/:id',
    name: 'process-quality-control-form',
    component: () => import('@/views/FormProcessQualityControlView.vue'),
    meta: { public: false },
  },
  {
    // screen-090--data-preview-process-quality-control /
    // usecase-117--data-preview-process-quality-control "Data Preview
    // Process Quality Control". `:id?` optional trailing param, same
    // convention as `data-preview-clarification`/`data-preview-boiler-room`
    // above.
    path: '/stations/process-quality-control/preview/:id?',
    name: 'data-preview-process-quality-control',
    component: () => import('@/views/DataPreviewProcessQualityControlView.vue'),
    meta: { public: false },
  },
  {
    // screen-121--monitor-sterilizer /
    // usecase-121--monitor-sterilizer "Monitor Sterilizer". meta.public
    // deliberately false (authenticated; actors: operator, supervisor).
    // Fills in the `monitor-sterilizer` route name StationListView.vue
    // already references (MONITOR_ROUTE_NAMES). Sterilizer follows the
    // same event-log pattern as CPO Dispatch/Solid Waste Disposal/Kernel
    // Dispatch — mirrors `monitor-cpo-dispatch` exactly. This is the
    // FINAL station of this project (the 18th and last of the 18
    // canonical stations, promoted 2026-09-01).
    path: '/stations/sterilizer/monitor',
    name: 'monitor-sterilizer',
    component: () => import('@/views/MonitorSterilizerView.vue'),
    meta: { public: false },
  },
  {
    // screen-122--form-sterilizer /
    // usecase-122--form-sterilizer "Form Sterilizer". `:id` route param
    // carries the draft record's id, same convention as
    // `cpo-dispatch-form`/`kernel-dispatch-form` above.
    path: '/stations/sterilizer/form/:id',
    name: 'sterilizer-form',
    component: () => import('@/views/FormSterilizerView.vue'),
    meta: { public: false },
  },
  {
    // screen-123--data-preview-sterilizer /
    // usecase-123--data-preview-sterilizer "Data Preview Sterilizer".
    // `:id?` optional trailing param, same convention as
    // `data-preview-cpo-dispatch`/`data-preview-process-quality-control`
    // above.
    path: '/stations/sterilizer/preview/:id?',
    name: 'data-preview-sterilizer',
    component: () => import('@/views/DataPreviewSterilizerView.vue'),
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
