<x-layouts.app title="Production Process Activity">
    <x-slot:styles>
        <style>
            /* station-tile styling mirrors uiux-spec component_patterns
               'station-tile' (mobile StationGrid.vue's active/disabled states):
               active — --color-station-red (#D20000) background, radius 'card'
               (12px), shadow 'card'; disabled — --color-surface (#F7F7F7)
               background, muted text, no shadow. */
            :root {
                --color-station-red: #D20000;
                --color-surface: #F7F7F7;
                --radius-card: 12px;
            }

            .station-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 16px;
                max-width: 1200px;
            }

            @media (max-width: 640px) {
                .station-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    max-width: none;
                }
            }

            .station-tile {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 44px;
                padding: 20px 12px;
                border-radius: var(--radius-card);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
                color: #fff;
                font-size: 13px;
                font-weight: 600;
                text-align: center;
                text-decoration: none;
            }

            .station-tile.active {
                background: var(--color-station-red);
            }

            .station-tile.active:hover {
                opacity: 0.92;
            }

            .station-tile.disabled {
                background: var(--color-surface);
                color: var(--color-text-muted);
                box-shadow: none;
                font-weight: 500;
                cursor: default;
            }

            .station-tile .placeholder-label {
                font-size: 10px;
                color: var(--color-text-muted);
                font-weight: 400;
            }

            .station-tile svg {
                width: 22px;
                height: 22px;
            }
        </style>
    </x-slot:styles>

    {{--
        15 canonical station tiles (7 active + 8 placeholder), hardcoded
        per tech-spec v1 implementation_notes — mirrors mobile's
        DEFAULT_STATIONS in localSchema.ts, not a database query.

        2026-08-23 — 4 of the former 12 placeholders (Thresher/Press/
        Digester/Kernel Plant) promoted to active MVP stations
        (Threshing/Pressing/Depricarping/Kernel Plant), mirroring
        StationGrid.vue's ACTIVE_ICONS SVG paths exactly for visual
        consistency between mobile and web. Threshing/Pressing were
        temporarily hidden then re-enabled 2026-08-25; Depricarping/Kernel
        Plant were temporarily hidden then re-enabled 2026-08-28 — all 7
        now render unconditionally.
    --}}
    <div class="station-grid">
        <a href="{{ route('data.weighbridge') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="13" r="8"></circle><path d="M12 9v4l3 2"></path><path d="M9 3h6"></path></svg>
            Weighbridge
        </a>
        <a href="{{ route('data.grading') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="4"></rect><rect x="3" y="10" width="18" height="4"></rect><rect x="3" y="16" width="18" height="4"></rect></svg>
            Grading
        </a>
        <a href="{{ route('data.cages-track') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="7" width="18" height="13" rx="1"></rect><path d="M3 11h18"></path><path d="M8 7V4h8v3"></path></svg>
            Cages Track
        </a>
        <a href="{{ route('data.threshing') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            Threshing
        </a>
        <a href="{{ route('data.pressing') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20V10l8-6 8 6v10"></path><line x1="12" y1="14" x2="12" y2="20"></line></svg>
            Pressing
        </a>
        <a href="{{ route('data.depricarping') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="8"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            Depricarping
        </a>
        <a href="{{ route('data.kernel-plant') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="3" width="14" height="18" rx="1"></rect><line x1="9" y1="8" x2="15" y2="8"></line><line x1="9" y1="12" x2="15" y2="12"></line></svg>
            Kernel Plant
        </a>
        @foreach ([
            'Sterilizer', 'Clarification',
            'Boiler', 'Effluent Treatment', 'Loading Ramp',
            'Engine Room', 'Water Treatment', 'Bulking Storage',
        ] as $placeholder)
            <div class="station-tile disabled" aria-disabled="true">
                {{ $placeholder }}
                <span class="placeholder-label">Belum tersedia</span>
            </div>
        @endforeach
    </div>
</x-layouts.app>
