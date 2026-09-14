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
        18 canonical station tiles, ALL 18 active, 0 placeholder, hardcoded
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

        2026-08-31 — 6 more former placeholders promoted to active:
        Clarification, Boiler ("Boiler Room"), Effluent Treatment
        ("Effluent Plant"), Engine Room, Water Treatment ("Process
        Water"), Bulking Storage ("Storage Tank") — reusing
        StationGrid.vue's PLACEHOLDER_ICONS SVG paths for the matching
        station name (mobile has not yet promoted these to its own
        ACTIVE_ICONS map at time of writing) for visual consistency.
        Plus 4 brand-new active tiles with no mobile equivalent yet
        (Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process
        Quality Control) — simple/distinct hand-picked icons, same
        inline-SVG convention. At the time this batch was added, none of
        these 10 stations had a Data Browser screen implemented yet, so
        all 10 were styled as active but linked to `javascript:void(0)`
        rather than a `route()` call. Solid Waste Disposal (screen-091),
        Process Water (screen-092), Kernel Dispatch (screen-093), CPO
        Dispatch (screen-094), and Effluent Plant (screen-095) have since
        been implemented and are now routed like the original 7. Storage
        Tank's Data Browser screen (screen-096) has since also been
        implemented and routed. Engine Room's Data Browser screen
        (screen-097) has since also been implemented and routed. Boiler
        Room's Data Browser screen (screen-098) has since also been
        implemented and routed. Clarification's Data Browser screen
        (screen-099) has since also been implemented and routed. Process
        Quality Control's Data Browser screen (screen-100) has since also
        been implemented and routed — all 10 of the 2026-08-31 batch are now
        routed, and this was the final of all 10 new MVP stations end-to-end.

        2026-09-01 — 'Loading Ramp' placeholder removed entirely: it turned
        out to be a duplicate name for the already-active Cages Track
        station, not a distinct station. Only Sterilizer remained placeholder
        at that point.

        2026-09-01 (final promotion) — Sterilizer promoted from placeholder
        to a fully active tile, routed to `route('data.sterilizer')`. This
        was the LAST remaining placeholder — all 18 canonical tiles are now
        active and routed, 0 placeholders remain.

        2026-09-01 (reorder) — tile order changed to a custom layout per
        user request: Weighbridge, Pressing, Storage Tank, Grading,
        Clarification, Effluent Plant, Cages Track, Engine Room, CPO
        Dispatch, Sterilizer, Boiler Room, Kernel Dispatch, Kernel Plant,
        Process Water, Threshing, Depricarping, Solid Waste Disposal,
        Process Quality Control — mirrors the same order now used by
        mobile's stationRepo.ts CASE-based ORDER BY. No longer the
        insertion/promotion-chronological order this comment's history
        above describes; that history is kept for context, not as the
        current tile sequence.

        2026-09-01 (hide) — 8 of the 18 tiles temporarily hidden from this
        grid per product decision (Engine Room, Storage Tank, Effluent
        Plant, CPO Dispatch, Kernel Dispatch, Process Water, Solid Waste
        Disposal, Process Quality Control) — commented out below, not
        deleted, mirroring mobile's stationRepo.ts HIDDEN_STATION_TYPES
        list. The underlying stations remain fully active/functional
        (their Data Browser routes still resolve if visited directly);
        only this grid's tile is hidden. Remove the surrounding comment
        block below to re-enable a given tile.

        2026-09-04 (re-enable) — Engine Room and Storage Tank re-enabled
        per user request; un-commented below and removed from mobile's
        stationRepo.ts HIDDEN_STATION_TYPES. 12 of 18 active tiles now
        render: Weighbridge, Pressing, Storage Tank, Grading, Clarification,
        Cages Track, Engine Room, Sterilizer, Boiler Room, Kernel Plant,
        Threshing, Depricarping. The remaining 6 (Effluent Plant, CPO
        Dispatch, Kernel Dispatch, Process Water, Solid Waste Disposal,
        Process Quality Control) stay hidden.

        2026-09-11 (re-enable) — Effluent Plant and CPO Dispatch re-enabled
        per user request; un-commented below and removed from mobile's
        stationRepo.ts HIDDEN_STATION_TYPES. 14 of 18 tiles now render; the
        remaining 4 (Kernel Dispatch, Process Water, Solid Waste Disposal,
        Process Quality Control) stay hidden.

        2026-09-14 (re-enable) — Kernel Dispatch and Process Water re-enabled
        per user request; un-commented below and removed from mobile's
        stationRepo.ts HIDDEN_STATION_TYPES. 16 of 18 tiles now render; only
        Solid Waste Disposal and Process Quality Control stay hidden.
    --}}
    <div class="station-grid">
        <a href="{{ route('data.weighbridge') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="13" r="8"></circle><path d="M12 9v4l3 2"></path><path d="M9 3h6"></path></svg>
            Weighbridge
        </a>
        <a href="{{ route('data.pressing') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20V10l8-6 8 6v10"></path><line x1="12" y1="14" x2="12" y2="20"></line></svg>
            Pressing
        </a>
        <a href="{{ route('data.storage-tank') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="6" width="16" height="14" rx="1"></rect><path d="M8 6V4h8v2"></path></svg>
            Storage Tank
        </a>
        <a href="{{ route('data.grading') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="4"></rect><rect x="3" y="10" width="18" height="4"></rect><rect x="3" y="16" width="18" height="4"></rect></svg>
            Grading
        </a>
        <a href="{{ route('data.clarification') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="3"></circle></svg>
            Clarification
        </a>
        <a href="{{ route('data.effluent-plant') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M8 12h8M12 8v8"></path></svg>
            Effluent Plant
        </a>
        <a href="{{ route('data.cages-track') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="7" width="18" height="13" rx="1"></rect><path d="M3 11h18"></path><path d="M8 7V4h8v3"></path></svg>
            Cages Track
        </a>
        <a href="{{ route('data.engine-room') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="2" width="12" height="20" rx="1"></rect><line x1="6" y1="8" x2="18" y2="8"></line><line x1="6" y1="14" x2="18" y2="14"></line></svg>
            Engine Room
        </a>
        <a href="{{ route('data.cpo-dispatch') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="10" height="14" rx="2"></rect><line x1="3" y1="10" x2="13" y2="10"></line><line x1="3" y1="14" x2="13" y2="14"></line><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path></svg>
            CPO Dispatch
        </a>
        <a href="{{ route('data.sterilizer') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="2"></rect><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="16" y2="13"></line></svg>
            Sterilizer
        </a>
        <a href="{{ route('data.boiler-room') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 21V9a6 6 0 0 1 12 0v12"></path><line x1="6" y1="15" x2="18" y2="15"></line></svg>
            Boiler Room
        </a>
        <a href="{{ route('data.kernel-dispatch') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="9" width="12" height="10" rx="1"></rect><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path></svg>
            Kernel Dispatch
        </a>
        <a href="{{ route('data.kernel-plant') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="3" width="14" height="18" rx="1"></rect><line x1="9" y1="8" x2="15" y2="8"></line><line x1="9" y1="12" x2="15" y2="12"></line></svg>
            Kernel Plant
        </a>
        <a href="{{ route('data.process-water') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"></path></svg>
            Process Water
        </a>
        <a href="{{ route('data.threshing') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            Threshing
        </a>
        <a href="{{ route('data.depricarping') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="8"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            Depricarping
        </a>
        {{-- Solid Waste Disposal temporarily hidden (2026-09-01, product decision) — station stays active, just not shown as a tile here.
        <a href="{{ route('data.solid-waste-disposal') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 7h16"></path><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            Solid Waste Disposal
        </a>
        --}}
        {{-- Process Quality Control temporarily hidden (2026-09-01, product decision) — station stays active, just not shown as a tile here.
        <a href="{{ route('data.process-quality-control') }}" class="station-tile active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"></path><path d="M9 12l2 2 4-4"></path></svg>
            Process Quality Control
        </a>
        --}}
    </div>
</x-layouts.app>
