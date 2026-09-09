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

    Per-level icon (mdt-node__icon--{level}, styled in the parent view) is
    purely visual — lets a user scanning a deep tree tell the level of a
    row apart without reading indentation alone.
--}}
@props(['node', 'expanded' => []])

@php
    $targets = [
        'corporate' => ['route' => 'master-data.corporates', 'param' => null],
        'company' => ['route' => 'master-data.companies', 'param' => 'filterCorporateId'],
        'business_unit' => ['route' => 'master-data.business-units', 'param' => 'filterCompanyId'],
        'production_line' => ['route' => 'master-data.production-lines', 'param' => 'filterBusinessUnitId'],
    ];

    $icons = [
        'corporate' => '<rect x="4" y="2" width="16" height="20" rx="1"></rect><line x1="9" y1="7" x2="9" y2="7.01"></line><line x1="15" y1="7" x2="15" y2="7.01"></line><line x1="9" y1="12" x2="9" y2="12.01"></line><line x1="15" y1="12" x2="15" y2="12.01"></line><line x1="9" y1="17" x2="15" y2="17"></line>',
        'company' => '<rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>',
        'business_unit' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle>',
        'production_line' => '<circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle><line x1="7" y1="12" x2="10" y2="12"></line><line x1="14" y1="12" x2="17" y2="12"></line>',
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
            </button>
        @else
            <span class="mdt-node__chevron-spacer" aria-hidden="true"></span>
        @endif

        <span class="mdt-node__icon mdt-node__icon--{{ $node['level'] }}" aria-hidden="true">
            <svg viewBox="0 0 24 24">{!! $icons[$node['level']] !!}</svg>
        </span>

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
