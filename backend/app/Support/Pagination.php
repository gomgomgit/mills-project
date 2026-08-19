<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Pagination — shared offset-based pagination helper (pagination-helper,
 * shared-modules).
 *
 * shared_decisions.pagination: strategy=Offset-based (page + per_page),
 * defaults: page=1, per_page=20, max per_page=100. Every list endpoint
 * (weighbridge-records, grading-records, ...) should use this helper so the
 * response meta shape is consistent across the API.
 *
 * Usage in a controller:
 *   return response()->json(
 *       Pagination::paginate(WeighbridgeRecord::query()->latest(), $request)
 *   );
 *
 * Response shape:
 *   {
 *     "data": [ ... ],
 *     "meta": { "page": 1, "per_page": 20, "total": 42, "total_pages": 3 }
 *   }
 */
class Pagination
{
    public const DEFAULT_PAGE = 1;

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * Paginate an Eloquent query using page/per_page query params from the
     * request, applying the shared defaults + max cap, and return the
     * formatted {data, meta} array.
     */
    public static function paginate(
        Builder $query,
        Request $request,
        string $pageParam = 'page',
        string $perPageParam = 'per_page'
    ): array {
        $page = max((int) $request->query($pageParam, self::DEFAULT_PAGE), 1);
        $perPage = self::resolvePerPage($request, $perPageParam);

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return self::format($paginator);
    }

    /**
     * Resolve a valid per_page value from the request: falls back to the
     * default when absent/invalid, and is capped at MAX_PER_PAGE.
     */
    public static function resolvePerPage(Request $request, string $perPageParam = 'per_page'): int
    {
        $perPage = (int) $request->query($perPageParam, self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }

    /**
     * Format an already-built LengthAwarePaginator into the shared
     * {data, meta} response shape.
     */
    public static function format(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                // Standard Laravel LengthAwarePaginator::lastPage() behavior:
                // always at least 1, even when total() === 0 (max(1,
                // ceil(total/perPage))). Kept as the canonical contract
                // (confirmed by project decision) rather than special-casing
                // the empty result to 0 — every list endpoint using this
                // helper shares this convention.
                'total_pages' => $paginator->lastPage(),
            ],
        ];
    }
}
