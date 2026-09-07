<div class="kc-page">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Master Data Tree View</h2>
            <p class="kc-page__subtitle">Jelajahi hierarki Corporate &rarr; Company &rarr; Business Unit &rarr; Production Line. Klik nama untuk membuka screen Kelola terkait.</p>
        </div>
    </div>

    <style>
        .mdt-tree, .mdt-node__children {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .mdt-node__children {
            margin-left: 24px;
            border-left: 1px solid var(--color-border, #e5e7eb);
            padding-left: 8px;
        }

        .mdt-node__row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 4px;
        }

        .mdt-node__chevron {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            width: 16px;
            padding: 0;
            transition: transform 0.15s ease;
        }

        .mdt-node__chevron--open {
            transform: rotate(90deg);
        }

        .mdt-node__chevron-spacer {
            display: inline-block;
            width: 16px;
        }

        .mdt-node__link {
            color: var(--color-text-primary, #111827);
            text-decoration: none;
            font-weight: 500;
        }

        .mdt-node__link:hover {
            text-decoration: underline;
        }

        .mdt-node__count {
            color: var(--color-text-muted, #6b7280);
            font-size: 12px;
            background: var(--color-surface, #f7f7f7);
            border-radius: 999px;
            padding: 1px 8px;
        }
    </style>

    @if (empty($tree))
        <p class="kc-page__subtitle">Belum ada data master. Mulai dengan menambah Corporate di Kelola Corporate.</p>
    @else
        <ul class="mdt-tree">
            @foreach ($tree as $node)
                <x-master-data-tree-node :node="$node" :expanded="$expanded" />
            @endforeach
        </ul>
    @endif
</div>
