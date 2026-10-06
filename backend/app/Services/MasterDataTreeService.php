<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * MasterDataTreeService — screen-127--master-data-tree-view /
 * usecase-127--master-data-tree-view ("Struktur Mills").
 *
 * A dedicated cross-cutting read service, NOT bolted onto CorporateService
 * — every other service in this codebase owns exactly one entity's
 * CRUD/listing concern and only ever references its own model plus its
 * immediate parent (Company -> Corporate, never Corporate -> Company ->
 * BusinessUnit -> ProductionLine in one query). This service's single
 * responsibility spans all 4 levels, so it lives on its own.
 *
 * SCOPE — UNCHANGED BY THE 2026-10-06 REVAMP: this service stops at
 * Production Line. Station, Machinery Group and Machinery are
 * intentionally NOT part of anything returned here (per confirmed scope,
 * screen-127's business-spec); they stay owned by their own "Kelola"
 * screens. Only the SHAPE of what is returned changed, never the boundary.
 *
 * SHAPE (2026-10-06 revamp): the read-only nested tree (getTree(), built
 * from the Corporate side) is gone — it had exactly one caller, this
 * screen's own component. The screen is now a mill-centric card board plus
 * two compact summary lists, so this service exposes four entry points:
 *
 *  - board()          — one card per Business Unit (mill), paginated IN
 *                       THE DATABASE, with company.corporate +
 *                       productionLines eager-loaded and
 *                       withCount('productionLines'). Eager-loading is
 *                       what keeps this N+1-free: the parents and the
 *                       lines of every mill on the page load in a fixed
 *                       number of queries, not one query per card.
 *  - counts()         — four separate COUNTs over the four hierarchy
 *                       tables. DELIBERATELY not derived from board()'s
 *                       result nor from the filtered rows: the summary bar
 *                       exists to prove the page hides nothing, so it must
 *                       state the total of ALL data even while a filter is
 *                       active.
 *  - corporateRows()  — the Corporate summary list, withCount('companies'),
 *                       paginated on its own. Includes Corporates with zero
 *                       Companies — they appear on no mill card at all, so
 *                       this list is the only place they exist.
 *  - companyRows()    — the Company summary list, withCount('businessUnits')
 *                       + corporate eager-loaded, paginated on its own.
 *                       Same reasoning for Companies with zero mills.
 *
 * FILTERING HAPPENS IN THE DATABASE, never in PHP. Filtering after
 * pagination would make a match sitting on page 3 read as "not found", and
 * loading every row just to filter it in PHP would defeat the pagination
 * entirely. Case-insensitivity uses lower() + LIKE and NEVER ILIKE — the
 * test suite runs on SQLite while production runs PostgreSQL, and ILIKE
 * exists only in the latter.
 *
 * A row matches the keyword when the keyword matches the row ITSELF, any
 * of its ANCESTORS, or any of its DESCENDANTS — on name or code, at all
 * four levels. That single rule is what makes one search box narrow the
 * card board and both summary lists coherently: a mill stays visible when
 * what matched was one of its lines (otherwise the filter would hide
 * exactly what was being looked for), and a Corporate narrows away when
 * the keyword belongs to a different branch.
 */
class MasterDataTreeService
{
    /**
     * counts() — four independent COUNTs, one per hierarchy table.
     *
     * Never takes the search keyword: the summary bar always states the
     * total of every row that exists. An ever-shrinking total would make
     * an Admin believe filtering (or paginating) deleted data.
     *
     * @return array{corporate: int, company: int, business_unit: int, production_line: int}
     */
    public function counts(): array
    {
        return [
            'corporate' => Corporate::query()->count(),
            'company' => Company::query()->count(),
            'business_unit' => BusinessUnit::query()->count(),
            'production_line' => ProductionLine::query()->count(),
        ];
    }

    /**
     * board() — one card per mill, built FROM THE BUSINESS UNIT SIDE.
     *
     * Each card carries its parent Company and Corporate (as the small
     * "corporate > company" breadcrumb), every one of its Production Lines
     * as rows inside the card, and production_lines_count — the REAL
     * number of lines, from withCount(), not the number rendered. Lines
     * are never paginated and never filtered out of a card: a card that
     * showed only some of its lines would be lying about the mill's
     * contents. Lines that match the keyword are flagged instead
     * (`matches` => true) so the one being looked for is visible.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function board(?string $search, int $page, int $perPage): array
    {
        $term = $this->normalizeTerm($search);

        $query = BusinessUnit::query()
            ->with([
                'company.corporate',
                'productionLines' => fn ($q) => $q->orderBy('name'),
            ])
            ->withCount('productionLines')
            ->orderBy('name');

        if ($term !== null) {
            $query->where(function (Builder $q) use ($term) {
                $this->matchColumns($q, 'business_units', ['name', 'code'], $term);

                // A mill stays on the board when the match is one of its
                // own lines, or when it is its Company/Corporate parent —
                // "filter the whole hierarchy", not "filter mill names".
                $q->orWhereHas('productionLines', fn (Builder $lines) => $lines->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'production_lines', ['name', 'code'], $term)
                ));

                $q->orWhereHas('company', fn (Builder $companies) => $companies->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'companies', ['name', 'company_code'], $term)
                ));

                $q->orWhereHas('company.corporate', fn (Builder $corporates) => $corporates->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'corporates', ['name', 'corporate_code'], $term)
                ));
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: max($page, 1));

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (BusinessUnit $mill) => $this->toCard($mill, $term))
            ->all();

        return $formatted;
    }

    /**
     * corporateRows() — the Corporate summary list.
     *
     * Includes Corporates with zero Companies, with the zero PRINTED: a
     * brand-new Corporate (the first step of "set up a new mill") appears
     * on no mill card, so this is the only place it is visible at all. An
     * empty cell instead of a printed 0 would read as a rendering failure.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function corporateRows(?string $search, int $page, int $perPage): array
    {
        $term = $this->normalizeTerm($search);

        $query = Corporate::query()
            ->withCount('companies')
            ->orderBy('name');

        if ($term !== null) {
            $query->where(function (Builder $q) use ($term) {
                $this->matchColumns($q, 'corporates', ['name', 'corporate_code'], $term);

                $q->orWhereHas('companies', fn (Builder $companies) => $companies->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'companies', ['name', 'company_code'], $term)
                ));

                $q->orWhereHas('companies.businessUnits', fn (Builder $mills) => $mills->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'business_units', ['name', 'code'], $term)
                ));

                $q->orWhereHas('companies.businessUnits.productionLines', fn (Builder $lines) => $lines->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'production_lines', ['name', 'code'], $term)
                ));
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: max($page, 1));

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Corporate $corporate) => [
                'id' => $corporate->id,
                'name' => $corporate->name,
                'code' => $corporate->corporate_code,
                'companies_count' => (int) ($corporate->companies_count ?? 0),
            ])
            ->all();

        return $formatted;
    }

    /**
     * companyRows() — the Company summary list, with its parent Corporate
     * name and its mill count. Same reasoning as corporateRows() for
     * Companies that have no mill yet.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function companyRows(?string $search, int $page, int $perPage): array
    {
        $term = $this->normalizeTerm($search);

        $query = Company::query()
            ->with('corporate')
            ->withCount('businessUnits')
            ->orderBy('name');

        if ($term !== null) {
            $query->where(function (Builder $q) use ($term) {
                $this->matchColumns($q, 'companies', ['name', 'company_code'], $term);

                $q->orWhereHas('corporate', fn (Builder $corporates) => $corporates->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'corporates', ['name', 'corporate_code'], $term)
                ));

                $q->orWhereHas('businessUnits', fn (Builder $mills) => $mills->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'business_units', ['name', 'code'], $term)
                ));

                $q->orWhereHas('businessUnits.productionLines', fn (Builder $lines) => $lines->where(
                    fn (Builder $inner) => $this->matchColumns($inner, 'production_lines', ['name', 'code'], $term)
                ));
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: max($page, 1));

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->company_code,
                'corporate_id' => $company->corporate_id,
                'corporate_name' => optional($company->corporate)->name,
                'business_units_count' => (int) ($company->business_units_count ?? 0),
            ])
            ->all();

        return $formatted;
    }

    /**
     * Shapes one mill into the card the board renders.
     *
     * `initials` is computed here (max two letters) so a mill with no logo
     * can render a consistent placeholder badge instead of an <img> with
     * an empty src, which browsers draw as a broken-image icon. Long
     * names/codes are NOT shortened here — truncation is CSS's job, so the
     * full value stays in the DOM where it can be read and copied.
     *
     * @return array<string, mixed>
     */
    private function toCard(BusinessUnit $mill, ?string $term): array
    {
        $company = $mill->company;
        $corporate = optional($company)->corporate;

        return [
            'id' => $mill->id,
            'name' => $mill->name,
            'code' => $mill->code,
            'initials' => $this->initials($mill->name),
            'logo_url' => $mill->logo
                ? Storage::disk(BusinessUnitService::LOGO_DISK)->url($mill->logo)
                : null,
            'company_id' => $mill->company_id,
            'company_name' => optional($company)->name,
            'corporate_name' => optional($corporate)->name,
            // The REAL line count (withCount), never the rendered count —
            // and the only thing that decides the "belum ada Production
            // Line" state. A collection left empty by filtering is a
            // different (and misleading) condition.
            'production_lines_count' => (int) ($mill->production_lines_count ?? 0),
            'production_lines' => $mill->productionLines
                ->map(fn (ProductionLine $line) => [
                    'id' => $line->id,
                    'name' => $line->name,
                    'code' => $line->code,
                    'description' => $line->description,
                    'matches' => $this->lineMatches($line, $term),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Whether this line is the thing the keyword found — drives the "line
     * yang cocok ditandai" marker inside the card. Computed in PHP over
     * the already-loaded line (not a query): the lines of the mills on
     * this page are all in memory already, and re-querying them would add
     * one query per card for a purely presentational flag.
     */
    private function lineMatches(ProductionLine $line, ?string $term): bool
    {
        if ($term === null) {
            return false;
        }

        foreach ([$line->name, $line->code] as $value) {
            if ($value !== null && str_contains(mb_strtolower((string) $value), $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Up to two initials from a name ("Alpha Beta" -> "AB", "Alpha" ->
     * "AL"), uppercased. Empty string for an empty name — the view falls
     * back to a neutral badge rather than printing nothing.
     */
    private function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '';
        }

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
    }

    /**
     * Lower-cased, trimmed keyword — or null when there is nothing to
     * filter by. Lower-casing once here is what lets every comparison be
     * `lower(column) LIKE ?` with an already-lowered bound parameter.
     */
    private function normalizeTerm(?string $search): ?string
    {
        $term = mb_strtolower(trim((string) $search));

        return $term === '' ? null : $term;
    }

    /**
     * OR-matches `$term` against `lower(<table>.<column>)` for each given
     * column, inside whatever (already grouped) where-clause it is handed.
     *
     * lower() + LIKE, NEVER ILIKE — ILIKE is PostgreSQL-only while this
     * suite runs on SQLite, so an ILIKE here would be green in tests and
     * broken in production (see App\Rules\UniqueCaseInsensitive, which
     * made the same choice for the same reason). The term is bound as a
     * parameter, never interpolated. The explicit ESCAPE clause is there
     * for the same portability reason: PostgreSQL defaults LIKE's escape
     * character to backslash while SQLite has none at all, so stating it
     * is the only way both engines treat a keyword containing % or _
     * identically.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    private function matchColumns(Builder $query, string $table, array $columns, string $term): Builder
    {
        $pattern = '%'.$this->escapeLike($term).'%';

        foreach ($columns as $index => $column) {
            $expression = 'lower('.$table.'.'.$column.') like ? escape \'\\\'';

            if ($index === 0) {
                $query->whereRaw($expression, [$pattern]);

                continue;
            }

            $query->orWhereRaw($expression, [$pattern]);
        }

        return $query;
    }

    /**
     * Escapes LIKE wildcards so a keyword containing % or _ searches for
     * those characters literally instead of matching everything.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
