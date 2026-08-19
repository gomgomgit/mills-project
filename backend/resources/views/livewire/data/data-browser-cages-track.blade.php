<div class="ct-browser" wire:loading.class="ct-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="ct-browser__header">
        <div>
            <h2 class="ct-browser__title">Data Browser Cages Track</h2>
            <p class="ct-browser__subtitle">Riwayat data log sheet stasiun Cages Track</p>
        </div>

        <div class="ct-browser__export">
            <a href="{{ $exportCsvUrl }}" class="ct-button ct-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="ct-button ct-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="ct-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="ct-filterbar">
        <div class="ct-filterbar__field">
            <label for="date_from" class="ct-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="ct-filterbar__input">
        </div>

        <div class="ct-filterbar__field">
            <label for="date_to" class="ct-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="ct-filterbar__input">
        </div>

        <div class="ct-filterbar__field">
            <label for="business_unit_id" class="ct-filterbar__label">Business Unit</label>
            <select id="business_unit_id" wire:model.live="business_unit_id" class="ct-filterbar__input">
                <option value="">Semua Business Unit</option>
                @foreach ($businessUnits as $businessUnit)
                    <option value="{{ $businessUnit->id }}">{{ $businessUnit->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ct-table-wrap">
        <table class="ct-table">
            <thead class="ct-table__head">
                <tr>
                    <th>No. Cages Track</th>
                    <th>Tanggal</th>
                    <th>Jumlah Cage/Lori Tercatat</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.cages-track.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.cages-track.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="ct-table__row @if(!$hasDetailRoute) ct-table__row--static @endif"
                        wire:key="ct-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['cages_track_number'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['tipped_time_count'] }}</td>
                        <td>
                            <span class="ct-badge ct-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="ct-table__row ct-table__row--static">
                        <td colspan="4">
                            <div class="ct-empty">
                                <div class="ct-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="ct-empty__title">Tidak ada data</p>
                                <p class="ct-empty__subtitle">Tidak ada data cages track yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="ct-pagination">
            <span class="ct-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="ct-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="ct-button ct-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="ct-button ct-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here (same
           approach as resources/views/data/grading.blade.php and every
           other Livewire view in this codebase so far) since the backend
           scaffold has no frontend build pipeline yet — see
           implementation_notes. Class names use a `ct-` prefix (distinct
           from screen-016's `wb-` and screen-017's `gr-` prefixes) since
           Livewire views are not style-scoped — avoids any collision if
           multiple browsers were ever rendered on the same page. */
        .ct-browser {
            --ct-brand: #249360;
            --ct-brand-hover: #1d7a4e;
            --ct-destructive: #DC2626;
            --ct-text: #1f2937;
            --ct-text-muted: #6b7280;
            --ct-border: #d1d5db;
            --ct-radius-input: 6px;
            --ct-radius-button: 8px;
            color: var(--ct-text);
        }

        .ct-browser--busy {
            opacity: 0.85;
        }

        .ct-browser__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .ct-browser__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .ct-browser__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--ct-text-muted);
        }

        .ct-browser__export {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .ct-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--ct-radius-input);
            background: #fef2f2;
            border: 1px solid var(--ct-destructive);
            color: var(--ct-destructive);
            font-size: 14px;
        }

        .ct-filterbar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid var(--ct-border);
            border-radius: 10px;
        }

        .ct-filterbar__field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }

        .ct-filterbar__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--ct-text-muted);
        }

        .ct-filterbar__input {
            padding: 8px 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--ct-text);
            border: 1px solid var(--ct-border);
            border-radius: var(--ct-radius-input);
            background: #fff;
        }

        .ct-filterbar__input:focus {
            outline: none;
            border-color: var(--ct-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .ct-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--ct-border);
            border-radius: 10px;
            background: #fff;
        }

        .ct-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .ct-table__head th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--ct-text-muted);
            border-bottom: 1px solid var(--ct-border);
            white-space: nowrap;
        }

        .ct-table__row td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--ct-border);
            white-space: nowrap;
        }

        .ct-table__row:last-child td {
            border-bottom: none;
        }

        .ct-table__row:not(.ct-table__row--static) {
            cursor: pointer;
        }

        .ct-table__row:not(.ct-table__row--static):hover {
            background: #f0fdf4;
        }

        .ct-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            background: #f3f4f6;
            color: var(--ct-text-muted);
        }

        .ct-badge--saved,
        .ct-badge--synced {
            background: #f0fdf4;
            color: #16a34a;
        }

        .ct-badge--draft_ongoing,
        .ct-badge--draft_paused {
            background: #fffbeb;
            color: #b45309;
        }

        .ct-empty {
            padding: 48px 16px;
            text-align: center;
        }

        .ct-empty__illustration {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .ct-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--ct-text);
        }

        .ct-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--ct-text-muted);
        }

        .ct-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--ct-text-muted);
        }

        .ct-pagination__controls {
            display: flex;
            gap: 8px;
        }

        .ct-button {
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--ct-radius-button);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .ct-button--secondary {
            background: var(--ct-brand);
            color: #fff;
        }

        .ct-button--secondary:hover {
            background: var(--ct-brand-hover);
        }

        .ct-button--ghost {
            background: #fff;
            color: var(--ct-text);
            border: 1px solid var(--ct-border);
        }

        .ct-button--ghost:hover:not(:disabled) {
            border-color: var(--ct-brand);
            color: var(--ct-brand);
        }

        .ct-button--ghost:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</div>
