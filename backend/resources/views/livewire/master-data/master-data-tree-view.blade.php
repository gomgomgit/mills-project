<div class="kc-page">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Master Data Tree View</h2>
            <p class="kc-page__subtitle">Jelajahi hierarki Corporate &rarr; Company &rarr; Business Unit &rarr; Production Line. Klik nama untuk membuka screen Kelola terkait.</p>
        </div>
    </div>

    <style>
        /* kc-page base — this screen borrowed the kc- class prefix
           (screen-127 was built alongside the Kelola master-data screens)
           but never carried its own copy of the shared token/header rules,
           so it rendered with unstyled headings until this pass. Kept
           identical to kelola-company.blade.php's block for visual
           consistency across the module. */
        .kc-page {
            --kc-brand: #249360;
            --kc-brand-hover: #1d7a4e;
            --kc-text: #1f2937;
            --kc-text-muted: #6b7280;
            --kc-border: #d1d5db;
            --kc-radius-input: 6px;
            color: var(--kc-text);
        }

        .kc-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .kc-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .kc-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--kc-text-muted);
        }

        /* Card shell — same border/radius/background language as
           .kc-table-wrap on the other master-data screens, so the tree
           reads as one more "content panel" in the same module rather
           than a bare list floating on the page background. */
        .mdt-card {
            border: 1px solid var(--kc-border);
            border-radius: 10px;
            background: #fff;
            padding: 20px 24px;
        }

        .mdt-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
            padding: 40px 16px;
            color: var(--kc-text-muted);
        }

        .mdt-empty svg {
            width: 32px;
            height: 32px;
            stroke: var(--kc-border);
        }

        .mdt-empty p {
            margin: 0;
            font-size: 14px;
        }

        .mdt-tree, .mdt-node__children {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .mdt-node__children {
            position: relative;
            margin-left: 12px;
            margin-top: 2px;
            padding-left: 20px;
            border-left: 1.5px dashed var(--kc-border);
        }

        /* Short horizontal tick connecting the vertical guide line to each
           child row's icon — the classic file-tree "elbow" connector. */
        .mdt-node__children > .mdt-node {
            position: relative;
        }

        .mdt-node__children > .mdt-node::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 19px;
            width: 20px;
            height: 1.5px;
            background: var(--kc-border);
        }

        .mdt-node__row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 8px;
            border-radius: var(--kc-radius-input);
            transition: background 0.12s ease;
        }

        .mdt-node__row:hover {
            background: #f9fafb;
        }

        .mdt-node__chevron {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            cursor: pointer;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            padding: 0;
            color: var(--kc-text-muted);
            border-radius: 4px;
        }

        .mdt-node__chevron:hover {
            background: #eef2f1;
            color: var(--kc-text);
        }

        .mdt-node__chevron svg {
            width: 12px;
            height: 12px;
            transition: transform 0.15s ease;
        }

        .mdt-node__chevron--open svg {
            transform: rotate(90deg);
        }

        .mdt-node__chevron-spacer {
            display: inline-block;
            width: 20px;
            flex-shrink: 0;
        }

        /* Per-level icon chip — a tinted square so the eye can scan the
           hierarchy depth at a glance without reading every label
           (Corporate=brand green, Company=blue, Business Unit=amber,
           Production Line=purple). Colors are decorative only, chosen for
           mutual contrast — they carry no semantic meaning beyond level. */
        .mdt-node__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 7px;
        }

        .mdt-node__icon svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.75;
        }

        .mdt-node__icon--corporate { background: rgba(36, 147, 96, 0.12); color: var(--kc-brand); }
        .mdt-node__icon--company { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
        .mdt-node__icon--business_unit { background: rgba(217, 119, 6, 0.12); color: #d97706; }
        .mdt-node__icon--production_line { background: rgba(124, 58, 237, 0.12); color: #7c3aed; }

        .mdt-node__link {
            color: var(--kc-text);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .mdt-node__link:hover {
            color: var(--kc-brand);
            text-decoration: underline;
        }

        .mdt-node__count {
            color: var(--kc-brand);
            font-size: 12px;
            font-weight: 600;
            background: rgba(36, 147, 96, 0.1);
            border-radius: 999px;
            padding: 1px 9px;
        }

        /* Phone (<768px): tighter card padding + shallower per-level indent
           so a 4-level-deep tree (corporate>company>business_unit>
           production_line) doesn't push row content off-screen; the wrapper
           still scrolls horizontally as a safety net for long names. */
        @media (max-width: 767px) {
            .kc-page__title {
                font-size: 18px;
            }

            .mdt-card {
                padding: 14px 12px;
                overflow-x: auto;
            }

            .mdt-node__children {
                margin-left: 6px;
                padding-left: 12px;
            }

            .mdt-node__children > .mdt-node::before {
                left: -12px;
                width: 12px;
            }

            .mdt-node__row {
                gap: 6px;
            }

            .mdt-node__link {
                white-space: nowrap;
            }
        }
    </style>

    @if (empty($tree))
        <div class="mdt-card">
            <div class="mdt-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="18" rx="1"></rect><line x1="9" y1="8" x2="15" y2="8"></line><line x1="9" y1="13" x2="15" y2="13"></line></svg>
                <p>Belum ada data master. Mulai dengan menambah Corporate di Kelola Corporate.</p>
            </div>
        </div>
    @else
        <div class="mdt-card">
            <ul class="mdt-tree">
                @foreach ($tree as $node)
                    <x-master-data-tree-node :node="$node" :expanded="$expanded" />
                @endforeach
            </ul>
        </div>
    @endif
</div>
