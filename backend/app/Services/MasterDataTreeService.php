<?php

namespace App\Services;

use App\Models\Corporate;
use Illuminate\Database\Eloquent\Model;

/**
 * MasterDataTreeService — screen-127--master-data-tree-view /
 * usecase-127--master-data-tree-view (Master Data Tree View).
 *
 * A dedicated cross-cutting read service, NOT bolted onto CorporateService
 * — every other service in this codebase owns exactly one entity's
 * CRUD/listing concern and only ever references its own model plus its
 * immediate parent (Company -> Corporate, never Corporate -> Company ->
 * BusinessUnit -> ProductionLine in one query). This service's single
 * responsibility spans all 4 levels, so it lives on its own.
 *
 * getTree() runs exactly ONE query (via nested eager-loads) regardless of
 * how large the hierarchy is — no N+1 as the tree grows.
 */
class MasterDataTreeService
{
    /**
     * Returns the full Corporate -> Company -> Business Unit -> Production
     * Line hierarchy as a nested array. Stops at Production Line — Station/
     * Machinery Group/Machinery are intentionally not part of this tree
     * (per confirmed scope, screen-127's business-spec).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(): array
    {
        $corporates = Corporate::query()
            ->with([
                'companies' => fn ($q) => $q->orderBy('name')->withCount('businessUnits'),
                'companies.businessUnits' => fn ($q) => $q->orderBy('name')->withCount('productionLines'),
                'companies.businessUnits.productionLines' => fn ($q) => $q->orderBy('name'),
            ])
            ->withCount('companies')
            ->orderBy('name')
            ->get();

        return $corporates->map(fn (Corporate $corporate) => $this->toNode('corporate', $corporate, null))->all();
    }

    /**
     * Normalizes one model into the uniform node shape every level shares —
     * this uniformity is what lets the Blade side use a single recursive
     * component instead of one partial per level.
     *
     * @return array<string, mixed>
     */
    private function toNode(string $level, Model $model, ?string $parentId): array
    {
        return match ($level) {
            'corporate' => [
                'level' => 'corporate',
                'id' => $model->id,
                'parent_id' => null,
                'name' => $model->name,
                'code' => $model->corporate_code,
                'children_count' => $model->companies_count,
                'children' => $model->companies
                    ->map(fn ($company) => $this->toNode('company', $company, $model->id))
                    ->all(),
            ],
            'company' => [
                'level' => 'company',
                'id' => $model->id,
                'parent_id' => $parentId,
                'name' => $model->name,
                'code' => $model->company_code,
                'children_count' => $model->business_units_count,
                'children' => $model->businessUnits
                    ->map(fn ($businessUnit) => $this->toNode('business_unit', $businessUnit, $model->id))
                    ->all(),
            ],
            'business_unit' => [
                'level' => 'business_unit',
                'id' => $model->id,
                'parent_id' => $parentId,
                'name' => $model->name,
                'code' => $model->code,
                'children_count' => $model->production_lines_count,
                'children' => $model->productionLines
                    ->map(fn ($productionLine) => $this->toNode('production_line', $productionLine, $model->id))
                    ->all(),
            ],
            default => [
                'level' => 'production_line',
                'id' => $model->id,
                'parent_id' => $parentId,
                'name' => $model->name,
                'code' => $model->code,
                'children_count' => 0,
                'children' => [],
            ],
        };
    }
}
