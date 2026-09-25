<?php

namespace App\Services;

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\StationType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * StationReportService — screen-140--laporan-stasiun-web /
 * usecase-142--laporan-stasiun-web (Pilih Stasiun untuk Laporan).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * StationReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanStasiun), the same split used by
 * SterilizerReportService / SterilizerReportController /
 * LaporanSterilizer — so the page and the API can never disagree on which
 * stations exist or which of them already have a report.
 *
 * READ-ONLY BY CONSTRUCTION: both public methods are SELECTs, and the
 * /api/station-reports prefix carries no POST/PUT/PATCH/DELETE. This
 * screen only picks a destination; it never writes anything.
 *
 * THREE BEHAVIOURS THAT ARE EASY TO GET WRONG, all deliberate:
 *
 *   1. resolveBusinessUnit() DISCARDS the client's business_unit_id for
 *      Supervisor / Mill Management — not validates it, not compares it,
 *      discards it. Probing another mill's id (or an id that does not
 *      exist at all) returns 200 with the caller's OWN mill. It is
 *      deliberately NOT a 403: there is no access attempt to refuse,
 *      because the parameter is never used for those roles, and a 403
 *      would confirm the other mill exists.
 *   2. A Supervisor / Mill Management account whose users.business_unit_id
 *      is NULL FAILS CLOSED with 422 — the whole-mill list is never even
 *      queried. Falling back to "all mills" for a broken master-data row
 *      would hand one mill's user every other mill's data.
 *   3. An Admin who has not picked a mill gets 422 VALIDATION_ERROR, NOT
 *      403. Nothing was refused; the input is simply incomplete.
 *
 * Behaviourally identical to SterilizerReportService::resolveBusinessUnit()
 * by design. The duplication is deliberate for this run: screen-129 is out
 * of scope and must not be touched. Folding both into one shared trait is
 * recorded as separate work.
 */
class StationReportService
{
    /**
     * station_types.code => the route name of that station's period report.
     *
     * THE SINGLE SOURCE OF TRUTH for report_available / report_path.
     * Shipping a new station report means adding ONE line here — never
     * scattering an `@if ($code === 'x')` into the blade. A code absent
     * from this map is still returned to the caller, just with
     * report_available = false, because the screen shows the full station
     * catalogue and greys out what is not built yet rather than hiding it.
     *
     * @var array<string, string>
     */
    public const REPORT_ROUTES = [
        // screen-130--laporan-cages-track-web. Without this one line the
        // report exists, its own tests pass, and the tile stays greyed out —
        // the screen is simply unreachable from the UI.
        StationTypeEnum::CagesTrack->value => 'reports.cages-track',
        StationTypeEnum::Sterilizer->value => 'reports.sterilizer',
        // screen-132--laporan-clarification-web. BETWEEN sterilizer and
        // boiler-room, never appended: station_types.sort_order puts
        // clarification (70) behind cages-track (30) and sterilizer (40) but
        // ahead of boiler-room (90), and the ordering of this map is
        // load-bearing — see the note below.
        StationTypeEnum::Clarification->value => 'reports.clarification',
        // screen-131--laporan-boiler-room-web. LAST, after sterilizer:
        // station_types.sort_order puts boiler-room (90) behind cages-track
        // (30) and sterilizer (40), and the ordering of this map is
        // load-bearing — see the note below.
        StationTypeEnum::BoilerRoom->value => 'reports.boiler-room',
    ];

    /**
     * ORDERING NOTE (2026-09-24, screen-130): entries are listed in
     * `station_types.sort_order` order — cages-track (3) before sterilizer
     * (4), and boiler-room appended after both (2026-09-24, screen-131).
     * Nothing in this service depends on the order; stationList() reads the
     * master table and only ever does a key lookup here.
     *
     * The order is kept in step anyway because screen-140's API test
     * ("hanya stasiun yang laporannya sudah ada yang aktif") compares the
     * available codes — which come back in sort_order — against
     * array_keys(self::REPORT_ROUTES) with a strict, ordered toBe(). That
     * assertion was written when the map held a single entry, so it only
     * looked order-insensitive. Adding a station report out of sort_order
     * will fail it for a reason that has nothing to do with the new report.
     */

    /**
     * The historical catch-all station type. It is not a real station —
     * only a bucket holding pre-migration rows — so it never gets a tile.
     */
    public const EXCLUDED_STATION_TYPE = StationTypeEnum::Other->value;

    /**
     * business_logic step: the mill picker options — ADMIN ONLY.
     *
     * Supervisor and Mill Management are bound to a single mill and have
     * no picker at all, so asking for this list is a 403 rather than a
     * filtered list of one.
     *
     * An empty master is a valid answer: [] with HTTP 200, never a 404.
     * The screen turns that into "master Business Unit masih kosong".
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * business_logic steps 1-11 — the station grid for the mill in effect.
     *
     * The list comes from the `station_types` MASTER TABLE ordered by
     * sort_order, not from a hardcoded array and not from the
     * App\Enums\StationType cases: adding a type to the master must add a
     * tile here with no code change at all. That is exactly what the
     * "daftar stasiun mengikuti master" test scenario proves.
     *
     * The list is deliberately NOT filtered per mill — the station type
     * master is global. A mill that happens to have no Sterilizer still
     * sees the Sterilizer tile. That follows the screen's rule "show
     * everything, disable what is not ready", and is a decision rather
     * than an oversight.
     *
     * @return array{business_unit: array{id: string, name: string}, stations: list<array{code: string, name: string, sort_order: int, report_available: bool, report_path: string|null}>}
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN (Operator / any role without web access)
     * @throws ValidationException 422 VALIDATION_ERROR (admin without a mill, or a bound account with no mill)
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function stations(?string $requestedBusinessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);

        // Proves the mill exists AND gives the screen its name. Runs
        // BEFORE the station query so a bad mill id never costs a second
        // round trip.
        /** @var BusinessUnit $businessUnit */
        $businessUnit = BusinessUnit::query()->findOrFail($businessUnitId, ['id', 'name']);

        return [
            'business_unit' => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ],
            'stations' => $this->stationList($businessUnitId),
        ];
    }

    /**
     * business_logic steps 1-7 — which mill the caller is allowed to look
     * at.
     *
     * Supervisor / Mill Management: ALWAYS their own business_unit_id. The
     * `business_unit_id` argument is ignored outright — see the class
     * docblock for why that is a 200 and not a 403. An account with no
     * mill at all fails closed with 422 instead of widening to every mill.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR — never a silent null, and never a 403.
     *
     * Operator (and any other role) has no web access and is refused here
     * as well as at the route middleware.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $role = $this->roleOf($user);

        if ($role === UserRole::Supervisor->value || $role === UserRole::MillManagement->value) {
            // Client-supplied business_unit_id is deliberately discarded —
            // not validated, not compared, discarded.
            $businessUnitId = (string) ($user->business_unit_id ?? '');

            if ($businessUnitId === '') {
                // FAIL CLOSED. No fallback to "every mill" — that would
                // turn a broken master-data row into a cross-mill leak.
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        if ($role === UserRole::Admin->value) {
            if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
                // Incomplete input, not a refused access — 422, not 403.
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan stasiun.'],
                ]);
            }

            return $requestedBusinessUnitId;
        }

        throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
    }

    /**
     * business_logic steps 9-10 — the station types, in production-process
     * order, each tagged with whether its period report exists yet.
     *
     * Nothing is dropped for being unbuilt: an unmapped code comes back
     * with report_available = false and report_path = null so the screen
     * can render it as a disabled tile.
     *
     * @return list<array{code: string, name: string, sort_order: int, report_available: bool, report_path: string|null}>
     */
    protected function stationList(string $businessUnitId): array
    {
        return StationType::query()
            ->where('code', '<>', self::EXCLUDED_STATION_TYPE)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'sort_order'])
            ->map(function (StationType $stationType) use ($businessUnitId) {
                $code = (string) $stationType->code;
                $routeName = self::REPORT_ROUTES[$code] ?? null;

                return [
                    'code' => $code,
                    'name' => (string) $stationType->name,
                    'sort_order' => (int) $stationType->sort_order,
                    'report_available' => $routeName !== null,
                    // The mill travels WITH the link, so the report screen
                    // never has to ask for a mill a second time in one flow.
                    'report_path' => $routeName === null
                        ? null
                        : route($routeName, ['business_unit_id' => $businessUnitId]),
                ];
            })
            ->all();
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }
}
