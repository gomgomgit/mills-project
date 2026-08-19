<div class="gr-browser" wire:loading.class="gr-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="gr-browser__header">
        <div>
            <h2 class="gr-browser__title">Data Browser Grading</h2>
            <p class="gr-browser__subtitle">Riwayat data log sheet stasiun Grading</p>
        </div>

        <div class="gr-browser__export">
            <a href="{{ $exportCsvUrl }}" class="gr-button gr-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="gr-button gr-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="gr-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="gr-filterbar">
        <div class="gr-filterbar__field">
            <label for="date_from" class="gr-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="gr-filterbar__input">
        </div>

        <div class="gr-filterbar__field">
            <label for="date_to" class="gr-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="gr-filterbar__input">
        </div>

        <div class="gr-filterbar__field">
            <label for="business_unit_id" class="gr-filterbar__label">Business Unit</label>
            <select id="business_unit_id" wire:model.live="business_unit_id" class="gr-filterbar__input">
                <option value="">Semua Business Unit</option>
                @foreach ($businessUnits as $businessUnit)
                    <option value="{{ $businessUnit->id }}">{{ $businessUnit->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="gr-table-wrap">
        <table class="gr-table">
            <thead class="gr-table__head">
                <tr>
                    <th>No. Grading</th>
                    <th>Tanggal</th>
                    <th>No. Kendaraan</th>
                    <th>Nama Pengemudi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.grading.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.grading.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="gr-table__row @if(!$hasDetailRoute) gr-table__row--static @endif"
                        wire:key="gr-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['grading_number'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['vehicle_number'] }}</td>
                        <td>{{ $record['driver_name'] }}</td>
                        <td>
                            <span class="gr-badge gr-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="gr-table__row gr-table__row--static">
                        <td colspan="5">
                            <div class="gr-empty">
                                <div class="gr-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="gr-empty__title">Tidak ada data</p>
                                <p class="gr-empty__subtitle">Tidak ada data grading yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="gr-pagination">
            <span class="gr-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="gr-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="gr-button gr-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="gr-button gr-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here (same
           approach as resources/views/data/weighbridge.blade.php and every
           other Livewire view in this codebase so far) since the backend
           scaffold has no frontend build pipeline yet — see
           implementation_notes. Class names use a `gr-` prefix (distinct
           from screen-016's `wb-` prefix) since Livewire views are not
           style-scoped — avoids any collision if both browsers were ever
           rendered on the same page. */
        .gr-browser {
            --gr-brand: #249360;
            --gr-brand-hover: #1d7a4e;
            --gr-destructive: #DC2626;
            --gr-text: #1f2937;
            --gr-text-muted: #6b7280;
            --gr-border: #d1d5db;
            --gr-radius-input: 6px;
            --gr-radius-button: 8px;
            color: var(--gr-text);
        }

        .gr-browser--busy {
            opacity: 0.85;
        }

        .gr-browser__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .gr-browser__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .gr-browser__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--gr-text-muted);
        }

        .gr-browser__export {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .gr-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--gr-radius-input);
            background: #fef2f2;
            border: 1px solid var(--gr-destructive);
            color: var(--gr-destructive);
            font-size: 14px;
        }

        .gr-filterbar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid var(--gr-border);
            border-radius: 10px;
        }

        .gr-filterbar__field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }

        .gr-filterbar__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--gr-text-muted);
        }

        .gr-filterbar__input {
            padding: 8px 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--gr-text);
            border: 1px solid var(--gr-border);
            border-radius: var(--gr-radius-input);
            background: #fff;
        }

        .gr-filterbar__input:focus {
            outline: none;
            border-color: var(--gr-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .gr-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--gr-border);
            border-radius: 10px;
            background: #fff;
        }

        .gr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .gr-table__head th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--gr-text-muted);
            border-bottom: 1px solid var(--gr-border);
            white-space: nowrap;
        }

        .gr-table__row td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gr-border);
            white-space: nowrap;
        }

        .gr-table__row:last-child td {
            border-bottom: none;
        }

        .gr-table__row:not(.gr-table__row--static) {
            cursor: pointer;
        }

        .gr-table__row:not(.gr-table__row--static):hover {
            background: #f0fdf4;
        }

        .gr-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            background: #f3f4f6;
            color: var(--gr-text-muted);
        }

        .gr-badge--saved,
        .gr-badge--synced {
            background: #f0fdf4;
            color: #16a34a;
        }

        .gr-badge--draft_ongoing,
        .gr-badge--draft_paused {
            background: #fffbeb;
            color: #b45309;
        }

        .gr-empty {
            padding: 48px 16px;
            text-align: center;
        }

        .gr-empty__illustration {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .gr-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--gr-text);
        }

        .gr-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--gr-text-muted);
        }

        .gr-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--gr-text-muted);
        }

        .gr-pagination__controls {
            display: flex;
            gap: 8px;
        }

        .gr-button {
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--gr-radius-button);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .gr-button--secondary {
            background: var(--gr-brand);
            color: #fff;
        }

        .gr-button--secondary:hover {
            background: var(--gr-brand-hover);
        }

        .gr-button--ghost {
            background: #fff;
            color: var(--gr-text);
            border: 1px solid var(--gr-border);
        }

        .gr-button--ghost:hover:not(:disabled) {
            border-color: var(--gr-brand);
            color: var(--gr-brand);
        }

        .gr-button--ghost:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</div>
