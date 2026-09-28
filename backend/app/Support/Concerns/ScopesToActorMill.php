<?php

namespace App\Support\Concerns;

use App\Enums\UserRole;
use App\Exceptions\CrossMillWriteDeniedException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * ScopesToActorMill — the mill-scope guard shared by every station write
 * path (2026-09-28).
 *
 * WHY A TRAIT, AND WHY HERE
 * ------------------------
 * Before this trait the project had SIX hand-copied resolveBusinessUnit()
 * and FOUR hand-copied guardAccess() implementations across the *Report
 * services, and the station WRITE path had none at all — which is exactly
 * why FormSterilizer scoped its Production Line dropdown to the actor's
 * mill while its 15 siblings did not, and why all 18 *RecordService
 * create() methods trusted the client's `production_line_id` outright.
 * Duplicated authorization drifts; a single trait cannot.
 *
 * It lives in App\Support\Concerns (not App\Services\Concerns) because two
 * different layers need the same decision:
 *
 *   - the 18 App\Services\*RecordService classes (the actual guard), and
 *   - the 18 App\Livewire\Data\Form* components (the dropdown that must
 *     not offer what the guard will refuse).
 *
 * THE RULE (project decision, 2026-09-28)
 * ---------------------------------------
 * A Production Line is a CHOSEN CONTEXT, not an account binding — there is
 * no `users.production_line_id` and there must not be one. The rule is
 * therefore "the chosen line must sit inside the ACTOR'S MILL", never
 * "the chosen line must equal the account's line":
 *
 *   - Admin is not mill-bound and may write to any mill's line. Admin is
 *     recognised BY ROLE, never by `users.business_unit_id` — that column
 *     is populated for most Admin rows in this project and is deliberately
 *     ignored for them, consistent with
 *     StationReportService::resolveBusinessUnit() and
 *     MillSettingService::checkAccess().
 *   - Operator / Supervisor / Mill Management must stay inside
 *     `users.business_unit_id`. An account without one FAILS CLOSED with
 *     422 + "Hubungi Admin" — never widens to every mill.
 *
 * WHICH COLUMN DECIDES
 * --------------------
 * `stations.business_unit_id` — the same column every mill-scoped READ
 * already filters on (StationReportService, the Data Browsers, the period
 * reports). Guarding the write on the very column the reads scope by is
 * what makes "written" and "visible" the same set; guarding the line's own
 * `production_lines.business_unit_id` instead would still allow a record
 * to land on a station the actor's own reports then refuse to show.
 * `stations.business_unit_id` is NOT NULL (2025_01_15_000004) and is kept
 * denormalized alongside `production_line_id` on purpose
 * (2026_08_20_000004), so there is no "wrong table" risk here — the
 * distinction matters because SQLite silently treats an unknown column in
 * a WHERE as a string literal (0 rows, no error) where PostgreSQL raises,
 * so a guard on a misplaced column would look green in the test suite and
 * be wide open in production.
 */
trait ScopesToActorMill
{
    /**
     * THE one place that decides "which mill is this actor".
     *
     * @return string|null the actor's mill id, or null when the actor is
     *                     not mill-bound at all (Admin — may write anywhere)
     *
     * @throws ValidationException 422 VALIDATION_ERROR — mill-bound actor
     *                             with no `business_unit_id` (fail closed)
     */
    protected function actorMillId(User $actor): ?string
    {
        if ($this->actorRoleValue($actor) === UserRole::Admin->value) {
            return null;
        }

        $millId = (string) ($actor->business_unit_id ?? '');

        if ($millId === '') {
            // FAIL CLOSED — never fall back to "any mill". Same wording and
            // same 422-not-403 reasoning as
            // StationReportService::resolveBusinessUnit().
            throw ValidationException::withMessages([
                'production_line_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
            ]);
        }

        return $millId;
    }

    /**
     * THE one place that decides "may this actor write to this mill's
     * data". Everything else in this trait is plumbing around this method.
     *
     * @throws CrossMillWriteDeniedException 403 FORBIDDEN
     * @throws ValidationException 422 — via actorMillId()
     */
    protected function assertMillWritable(?string $targetMillId, User $actor, string $message): void
    {
        $actorMillId = $this->actorMillId($actor);

        if ($actorMillId === null) {
            return; // Admin — not mill-bound.
        }

        if ((string) $targetMillId === '' || (string) $targetMillId !== $actorMillId) {
            throw new CrossMillWriteDeniedException($message);
        }
    }

    /**
     * create() path: resolve the active Station of $stationType on the
     * CLIENT-SUPPLIED $productionLineId, then refuse it if it is not in the
     * actor's mill.
     *
     * Returns null — rather than throwing — when no such station exists, so
     * each service keeps throwing its own NoActive<Station>Exception with
     * its own message/status, exactly as before.
     *
     * Ordering is deliberate: the mill-bound-actor check (actorMillId())
     * runs FIRST, so a broken account fails closed with 422 before any row
     * is touched; a non-existent line then still falls through to the
     * service's own 422 NoActive<Station>Exception (a line that does not
     * exist is not a cross-mill attempt), and only a line that resolves to
     * a real station in ANOTHER mill produces 403.
     *
     * `orderBy('created_at')->orderBy('id')` is NOT cosmetic: `stations`
     * has no unique (production_line_id, type) — the only unique index is
     * on `code` — so two active stations of the same type on one line are
     * legal, and the pre-existing bare ->first() picked between them
     * nondeterministically (different row on PostgreSQL vs SQLite, and
     * potentially between two runs). This makes the pick stable (oldest
     * wins) without adding a constraint that could fail on existing data.
     */
    protected function resolveActiveStationForActor(?string $productionLineId, string $stationType, User $actor): ?Station
    {
        $this->actorMillId($actor);

        if ($productionLineId === null || $productionLineId === '') {
            return null;
        }

        $station = Station::query()
            ->where('production_line_id', $productionLineId)
            ->where('type', $stationType)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($station === null) {
            return null;
        }

        $this->assertMillWritable(
            $station->business_unit_id,
            $actor,
            'Production line yang dipilih bukan milik mill Anda.'
        );

        return $station;
    }

    /**
     * update() path: the record just findOrFail()'d must belong to the
     * actor's mill, resolved through its station (records carry no
     * business_unit_id of their own). Reads `stations.business_unit_id`
     * with a single value() query — no relation-loading assumption, so it
     * behaves the same whether or not the caller eager-loaded `station`.
     *
     * @throws CrossMillWriteDeniedException 403 FORBIDDEN
     * @throws ValidationException 422 — via actorMillId()
     */
    protected function assertRecordWritableByActor(Model $record, User $actor): void
    {
        $stationMillId = Station::query()
            ->whereKey($record->station_id)
            ->value('business_unit_id');

        $this->assertMillWritable(
            $stationMillId === null ? null : (string) $stationMillId,
            $actor,
            'Record ini milik mill lain, Anda tidak dapat mengubahnya.'
        );
    }

    /**
     * UI path: the Production Line options a Form screen may offer.
     *
     * Same rule as the guard, one deliberate difference in FAILURE MODE:
     * a mill-bound actor without a `business_unit_id` gets an EMPTY list
     * here instead of an exception, because a dropdown is rendered during
     * mount() and must not blow up a page. The actionable message still
     * arrives the moment they try to save — actorMillId() raises it from
     * inside the service. Lifted verbatim (behaviour-wise) from
     * FormSterilizer::loadProductionLineOptions(), which was the only one
     * of the 18 Form components that got this right.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function productionLineOptionsForActor(?Authenticatable $user): array
    {
        $query = ProductionLine::query()->orderBy('name');

        if (! $user instanceof User || $this->actorRoleValue($user) !== UserRole::Admin->value) {
            $millId = (string) ($user->business_unit_id ?? '');

            if ($millId === '') {
                return [];
            }

            $query->where('business_unit_id', $millId);
        }

        return $query->get(['id', 'name'])->toArray();
    }

    /**
     * UI path: the Business Unit options a Form screen may offer (only the
     * two screens that let the actor pick a mill explicitly before the
     * line — Form Weighbridge and Form Grading). Admin sees every mill;
     * a mill-bound actor sees only their own, so the cascaded Production
     * Line list can never be widened by switching mill first.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function businessUnitOptionsForActor(?Authenticatable $user): array
    {
        return $this->businessUnitsForActor($user)
            ->map(fn (BusinessUnit $unit) => ['id' => $unit->id, 'name' => $unit->name])
            ->all();
    }

    /**
     * Same rule as businessUnitOptionsForActor(), as MODELS rather than
     * arrays — the 18 Data Browser blades render `$businessUnit->id` /
     * `->name`, so they need objects. Both methods resolve through here so
     * "which mills may this actor see in a dropdown" has exactly one
     * implementation.
     *
     * Failure mode is deliberately an EMPTY list, not an exception: this
     * feeds a <select> rendered during render()/mount() and must not blow
     * up a page. The actionable message still reaches the actor — the
     * Data Browser catches the 422 that actorReadMillId() raises from
     * inside the service and shows it in the error banner.
     *
     * @return EloquentCollection<int, BusinessUnit>
     */
    protected function businessUnitsForActor(?Authenticatable $user): EloquentCollection
    {
        $query = BusinessUnit::query()->orderBy('name');

        if (! $user instanceof User || $this->actorRoleValue($user) !== UserRole::Admin->value) {
            $millId = (string) ($user->business_unit_id ?? '');

            if ($millId === '') {
                return new EloquentCollection;
            }

            $query->whereKey($millId);
        }

        return $query->get(['id', 'name']);
    }

    /*
    |---------------------------------------------------------------------
    | READ SIDE (added 2026-09-28, stage 1b)
    |---------------------------------------------------------------------
    |
    | The write side above answers "may this actor WRITE here". Everything
    | below answers "what may this actor SEE", and it lives in the same
    | trait on purpose: before this block the project had the read rule
    | hand-copied into six *ReportService::resolveBusinessUnit() methods
    | while the 18 *RecordService read paths had NO rule at all — the
    | Data Browsers' `business_unit_id` filter came straight off a Livewire
    | property (empty by default) and an empty value meant NO SCOPE, so
    | every Supervisor could list, open and export every other mill's
    | station log sheets.
    |
    | One difference from the write side, and it is the whole point: the
    | client-supplied `business_unit_id` is DISCARDED for a mill-bound
    | actor — not validated, not compared, not 403'd. A Supervisor who
    | pushes another mill's id through the Livewire property or the API
    | query string gets their OWN mill's rows back, exactly as if they had
    | sent nothing. Same wording, same reasoning, as
    | StationReportService::resolveBusinessUnit(), which is the behaviour
    | this project already shipped for the period reports.
    |
    | Unlike the write side these methods take NO actor argument: they read
    | the authenticated actor themselves. listRecords()/export()/getDetail()
    | are called from 36 Livewire components and 18 API controllers with no
    | actor parameter, and adding one would have meant ~290 call sites where
    | a single missed one is a silent leak. Resolving from the guard makes
    | the scope impossible to forget, and the no-actor case throws
    | AuthenticationException (401) rather than widening — same fail-closed
    | choice StationReportService::resolveBusinessUnit() makes.
    */

    /**
     * THE one place that decides "which mill may this actor READ".
     *
     * @return string|null the actor's mill id, or null when the actor is
     *                     not mill-bound at all (Admin — may read anywhere)
     *
     * @throws AuthenticationException 401 — no authenticated actor
     * @throws ValidationException 422 VALIDATION_ERROR — mill-bound actor
     *                             with no `business_unit_id` (fail closed)
     */
    protected function actorReadMillId(): ?string
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            // FAIL CLOSED. Every route that reaches here is behind
            // `auth:web`, so this is unreachable in production — but a
            // guard whose default is "no scope" is exactly the bug this
            // block exists to remove.
            throw new AuthenticationException;
        }

        if ($this->actorRoleValue($actor) === UserRole::Admin->value) {
            return null;
        }

        $millId = (string) ($actor->business_unit_id ?? '');

        if ($millId === '') {
            throw ValidationException::withMessages([
                'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
            ]);
        }

        return $millId;
    }

    /**
     * Collapse a CLIENT-SUPPLIED mill filter down to what the actor may
     * actually see.
     *
     * @return string|null the mill id to filter on, or null for "no mill
     *                     filter at all" — which can only ever be an Admin
     *                     who did not pick one
     */
    protected function resolveReadMillId(?string $requestedMillId): ?string
    {
        $actorMillId = $this->actorReadMillId();

        if ($actorMillId !== null) {
            // Client-supplied value DISCARDED — not validated, not compared.
            return $actorMillId;
        }

        return ($requestedMillId === null || $requestedMillId === '') ? null : $requestedMillId;
    }

    /**
     * LIST/EXPORT path: rewrite a filter array's `business_unit_id` — and,
     * when present, its `production_line_id` — in place. Called as the
     * FIRST statement of every *RecordService::buildFilteredQuery(), which
     * both listRecords() and export() funnel through — one call per
     * service covers both.
     *
     * `production_line_id` is handled here rather than in each service for
     * the same reason `business_unit_id` is: the 18 services are
     * hand-written siblings, and a rule copied 18 times is a rule that will
     * drift 18 ways. It is only touched when the key EXISTS in $filters, so
     * a caller that does not offer a line filter at all is unaffected.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function scopeFiltersToActorMill(array $filters): array
    {
        $requested = $filters['business_unit_id'] ?? null;

        $millId = $this->resolveReadMillId(
            $requested === null ? null : (string) $requested
        );

        $filters['business_unit_id'] = $millId;

        if (array_key_exists('production_line_id', $filters)) {
            $requestedLine = $filters['production_line_id'];

            $filters['production_line_id'] = $this->clampProductionLineIdToMill(
                $requestedLine === null ? null : (string) $requestedLine,
                $millId,
            );
        }

        return $filters;
    }

    /**
     * READ path: collapse a CLIENT-SUPPLIED production line id down to one
     * the actor is actually allowed to narrow by.
     *
     * A production line is a CHOSEN CONTEXT, not an account binding — there
     * is no `users.production_line_id` — so the only rule that can be
     * enforced is "the chosen line must sit inside the mill currently in
     * effect for this actor". $millId is whatever resolveReadMillId()
     * already decided: the actor's own mill for a bound role (the
     * client-supplied mill having been discarded), the picked mill for an
     * Admin, or null for an Admin browsing every mill.
     *
     * FAILURE MODE IS SILENT FALLBACK, NOT AN ERROR — deliberately the same
     * treatment `business_unit_id` already gets: a line belonging to
     * another mill (pushed through a Livewire property or a bookmarked
     * query string) resolves to null, i.e. "all lines within the mill the
     * actor may see". It never throws, and it never shows that mill's rows.
     * Returning null rather than, say, an impossible id also keeps an
     * unknown/deleted line from silently emptying the list — a filter the
     * actor cannot even see in the dropdown should not be able to hide
     * their own data.
     *
     * The existence check is a real query on `production_lines` on purpose.
     * Comparing a raw uuid against the record table alone would not tell us
     * WHICH mill it belongs to, and SQLite happily evaluates a WHERE on a
     * column that does not exist as a string literal (0 rows, no error)
     * where PostgreSQL raises — so a guard that never touches the lines
     * table would look green in the suite and be wide open in production.
     */
    protected function clampProductionLineIdToMill(?string $requestedLineId, ?string $millId): ?string
    {
        if ($requestedLineId === null || $requestedLineId === '') {
            return null; // "Semua Line" — the default.
        }

        $query = ProductionLine::query()->whereKey($requestedLineId);

        if ($millId !== null && $millId !== '') {
            $query->where('business_unit_id', $millId);
        }

        return $query->exists() ? $requestedLineId : null;
    }

    /**
     * READ path, UI: the Production Line options a Data Browser's filter
     * may offer, within the mill currently in effect for the actor.
     *
     * Mirrors businessUnitsForActor() (the mill <select>'s source) one
     * level down the hierarchy, and fails the same way: an EMPTY list, not
     * an exception, because this feeds a <select> rendered during
     * render()/mount(). A mill-bound actor with no `business_unit_id` gets
     * nothing to choose from; the actionable 422 still reaches them from
     * actorReadMillId() inside the service.
     *
     * When an Admin has picked no mill, the list spans every mill, so each
     * label is prefixed with its mill name — two mills may legitimately
     * both call a line "Line 01", and an ambiguous option is worse than a
     * long one.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function productionLineOptionsForReadActor(?Authenticatable $user, string $currentMillId): array
    {
        $isAdmin = $user instanceof User && $this->actorRoleValue($user) === UserRole::Admin->value;
        $millId = $isAdmin ? $currentMillId : (string) ($user->business_unit_id ?? '');

        if (! $isAdmin && $millId === '') {
            return [];
        }

        $query = ProductionLine::query()->orderBy('name');

        if ($millId !== '') {
            return $query->where('business_unit_id', $millId)
                ->get(['id', 'name'])
                ->map(fn (ProductionLine $line) => ['id' => $line->id, 'name' => $line->name])
                ->all();
        }

        return $query->with('businessUnit:id,name')
            ->get(['id', 'name', 'business_unit_id'])
            ->map(fn (ProductionLine $line) => [
                'id' => $line->id,
                'name' => $line->businessUnit?->name
                    ? $line->businessUnit->name.' — '.$line->name
                    : $line->name,
            ])
            ->all();
    }

    /**
     * READ path, UI: what a Data Browser's production line filter property
     * must hold — the UI twin of clampProductionLineIdToMill().
     *
     * Cosmetic/defence-in-depth only, exactly like forcedMillFilterValue():
     * the binding enforcement is scopeFiltersToActorMill() inside the
     * service. This just stops the <select> and the export link claiming a
     * line that has already been discarded — including the ordinary case
     * where an Admin switches mill and the line they had picked belongs to
     * the mill they just left.
     */
    protected function forcedProductionLineFilterValue(?Authenticatable $user, string $currentMillId, string $currentLineId): string
    {
        if ($currentLineId === '') {
            return '';
        }

        $isAdmin = $user instanceof User && $this->actorRoleValue($user) === UserRole::Admin->value;
        $millId = $isAdmin ? $currentMillId : (string) ($user->business_unit_id ?? '');

        if (! $isAdmin && $millId === '') {
            return '';
        }

        return (string) ($this->clampProductionLineIdToMill($currentLineId, $millId === '' ? null : $millId) ?? '');
    }

    /**
     * DETAIL path: confine a record query to the actor's mill through its
     * station, so a known-but-foreign UUID resolves to NOTHING.
     *
     * Deliberately a QUERY SCOPE rather than a post-fetch 403: findOrFail()
     * on the scoped query raises ModelNotFoundException, which every caller
     * already handles — the API renders 404 NOT_FOUND, and all 36
     * Detail / Form Livewire components already catch it into their
     * `notFound` state. So this closes the leak with no call-site change,
     * and it does not confirm that someone else's record exists.
     */
    protected function scopeQueryToActorMill(EloquentBuilder $query, string $stationRelation = 'station'): EloquentBuilder
    {
        $millId = $this->actorReadMillId();

        if ($millId === null) {
            return $query; // Admin — not mill-bound.
        }

        return $query->whereHas(
            $stationRelation,
            fn (EloquentBuilder $stationQuery) => $stationQuery->where('business_unit_id', $millId)
        );
    }

    /**
     * READ path, UI: what a Data Browser's mill filter property must hold.
     * Admin keeps whatever they picked; a mill-bound actor is pinned to
     * their own mill, so a value injected through the Livewire property
     * (or a bookmarked query string) is overwritten before render() ever
     * reads it.
     *
     * Cosmetic/defence-in-depth only — the binding enforcement is
     * scopeFiltersToActorMill() inside the service. This just keeps the
     * <select> and the export link telling the truth.
     */
    protected function forcedMillFilterValue(?Authenticatable $user, string $current): string
    {
        if ($user instanceof User && $this->actorRoleValue($user) === UserRole::Admin->value) {
            return $current;
        }

        return (string) ($user->business_unit_id ?? '');
    }

    /**
     * READ path, UI: clamp a CLIENT-SUPPLIED mill id down to what the actor
     * may see, WITHOUT throwing.
     *
     * For the two Form screens that cascade mill -> production line ->
     * weighbridge card (Form Grading, Form Weighbridge). Their mill
     * `<select>` is already narrowed by businessUnitOptionsForActor(), but
     * `$form['business_unit_id']` is a public Livewire property: a crafted
     * request can set it to any mill and the cascade would then load that
     * mill's production lines — and, on Form Grading, that mill's
     * weighbridge cards (wb_card_number / vehicle_number / estate_supplier),
     * which is real operational data. The WRITE was already refused (stage
     * 1a resolves the station from `production_line_id` and 403s cross-mill);
     * this closes the READ that fed the picker.
     *
     * Returns null for "no mill" — a mill-less bound actor, whose cascade
     * then legitimately has nothing to offer (an empty dropdown, not an
     * exception, because this runs during render()/mount()).
     */
    protected function clampMillIdForActor(?Authenticatable $user, ?string $requestedMillId): ?string
    {
        if ($user instanceof User && $this->actorRoleValue($user) === UserRole::Admin->value) {
            return ($requestedMillId === null || $requestedMillId === '') ? null : $requestedMillId;
        }

        $millId = (string) ($user->business_unit_id ?? '');

        return $millId === '' ? null : $millId;
    }

    /** Role as a plain string, whether or not the cast is applied. */
    protected function actorRoleValue(User $actor): string
    {
        return $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role;
    }
}
