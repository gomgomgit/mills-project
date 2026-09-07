{{--
    Recursive tree-node renderer for screen-127--master-data-tree-view.
    ONE component handles all 4 levels (corporate/company/business_unit/
    production_line) because MasterDataTreeService::getTree() normalizes
    every level into the identical shape (level, id, parent_id, name, code,
    children_count, children) — recursion terminates naturally when
    `children` is empty at production_line, no depth counter needed.

    Two separate click targets per row, deliberately: the chevron toggles
    expand/collapse in place (wire:click, stays on this page); the name is
    a plain <a href> that navigates away. Binding both to one target would
    make expanding a node also navigate away before its children render.
--}}
@props(['node', 'expanded' => []])

@php
    $targets = [
        'corporate' => ['route' => 'master-data.corporates', 'param' => null],
        'company' => ['route' => 'master-data.companies', 'param' => 'filterCorporateId'],
        'business_unit' => ['route' => 'master-data.business-units', 'param' => 'filterCompanyId'],
        'production_line' => ['route' => 'master-data.production-lines', 'param' => 'filterBusinessUnitId'],
    ];

    $target = $targets[$node['level']];
    $href = $target['param']
        ? route($target['route'], [$target['param'] => $node['parent_id']])
        : route($target['route']);

    $hasChildren = count($node['children']) > 0;
    $nodeKey = "{$node['level']}-{$node['id']}";
    $isExpanded = isset($expanded[$nodeKey]);
@endphp

<li class="mdt-node" wire:key="{{ $nodeKey }}">
    <div class="mdt-node__row">
        @if ($hasChildren)
            <button
                type="button"
                class="mdt-node__chevron {{ $isExpanded ? 'mdt-node__chevron--open' : '' }}"
                wire:click="toggleNode('{{ $node['level'] }}', '{{ $node['id'] }}')"
                aria-label="{{ $isExpanded ? 'Tutup' : 'Buka' }} {{ $node['name'] }}"
            >
                &#9656;
            </button>
        @else
            <span class="mdt-node__chevron-spacer" aria-hidden="true"></span>
        @endif

        <a href="{{ $href }}" class="mdt-node__link">{{ $node['name'] }}</a>

        @if ($node['level'] !== 'production_line')
            <span class="mdt-node__count">{{ $node['children_count'] }}</span>
        @endif
    </div>

    @if ($hasChildren && $isExpanded)
        <ul class="mdt-node__children">
            @foreach ($node['children'] as $child)
                <x-master-data-tree-node :node="$child" :expanded="$expanded" />
            @endforeach
        </ul>
    @endif
</li>
