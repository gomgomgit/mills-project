<div class="st-browser" wire:loading.class="st-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="st-browser__header">
        <div>
            <h2 class="st-browser__title">Data Browser Storage Tank</h2>
            <p class="st-browser__subtitle">Riwayat data log sheet stasiun Storage Tank</p>
        </div>

        <div class="st-browser__export">
            <a href="{{ route('data.storage-tank.create') }}" class="st-button st-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="st-button st-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="st-button st-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="st-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="st-filterbar">
        <div class="st-filterbar__field">
            <label for="date_from" class="st-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="st-filterbar__input">
        </div>

        <div class="st-filterbar__field">
            <label for="date_to" class="st-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="st-filterbar__input">
        </div>

        <div class="st-filterbar__field">
            <label for="business_unit_id" class="st-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="st-filterbar__input"
            />
        </div>
    </div>

    <div class="st-table-wrap">
        <table class="st-table">
            <thead class="st-table__head">
                <tr>
                    <th>Storage Tank ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.storage-tank.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.storage-tank.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="st-table__row @if(!$hasDetailRoute) st-table__row--static @endif"
                        wire:key="st-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['storage_tank_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="st-badge st-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="st-table__row st-table__row--static">
                        <td colspan="4">
                            <div class="st-empty">
                                <div class="st-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="st-empty__title">Tidak ada data</p>
                                <p class="st-empty__subtitle">Tidak ada data storage tank yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="st-pagination">
            <span class="st-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="st-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="st-button st-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="st-button st-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-effluent-plant.blade.php. Class names use
           an `st-` prefix (distinct from `th-`/`gr-`/`ct-`/`ep-`) since
           Livewire views are not style-scoped. */
        .st-browser {
            --st-brand: #249360;
            --st-brand-hover: #1d7a4e;
            --st-destructive: #DC2626;
            --st-text: #1f2937;
            --st-text-muted: #6b7280;
            --st-border: #d1d5db;
            --st-radius-input: 6px;
            --st-radius-button: 8px;
            color: var(--st-text);
        }

        .st-browser--busy { opacity: 0.85; }

        .st-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .st-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .st-browser__subtitle { margin: 0; font-size: 14px; color: var(--st-text-muted); }
        .st-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .st-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--st-radius-input); background: #fef2f2; border: 1px solid var(--st-destructive); color: var(--st-destructive); font-size: 14px; }

        .st-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--st-border); border-radius: 10px; }
        .st-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .st-filterbar__label { font-size: 13px; font-weight: 500; color: var(--st-text-muted); }
        .st-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--st-text); border: 1px solid var(--st-border); border-radius: var(--st-radius-input); background: #fff; }
        .st-filterbar__input:focus { outline: none; border-color: var(--st-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .st-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--st-border); border-radius: 10px; background: #fff; }
        .st-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .st-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--st-text-muted); border-bottom: 1px solid var(--st-border); white-space: nowrap; }
        .st-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--st-border); white-space: nowrap; }
        .st-table__row:last-child td { border-bottom: none; }
        .st-table__row:not(.st-table__row--static) { cursor: pointer; }
        .st-table__row:not(.st-table__row--static):hover { background: #f0fdf4; }

        .st-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--st-text-muted); }
        .st-badge--saved, .st-badge--synced { background: #f0fdf4; color: #16a34a; }
        .st-badge--draft_ongoing, .st-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .st-empty { padding: 48px 16px; text-align: center; }
        .st-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .st-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--st-text); }
        .st-empty__subtitle { margin: 0; font-size: 13px; color: var(--st-text-muted); }

        .st-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--st-text-muted); }
        .st-pagination__controls { display: flex; gap: 8px; }

        .st-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--st-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .st-button--secondary { background: var(--st-brand); color: #fff; }
        .st-button--secondary:hover { background: var(--st-brand-hover); }
        .st-button--ghost { background: #fff; color: var(--st-text); border: 1px solid var(--st-border); }
        .st-button--ghost:hover:not(:disabled) { border-color: var(--st-brand); color: var(--st-brand); }
        .st-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
