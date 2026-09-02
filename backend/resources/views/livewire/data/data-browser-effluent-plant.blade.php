<div class="ep-browser" wire:loading.class="ep-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="ep-browser__header">
        <div>
            <h2 class="ep-browser__title">Data Browser Effluent Plant</h2>
            <p class="ep-browser__subtitle">Riwayat data log sheet stasiun Effluent Plant</p>
        </div>

        <div class="ep-browser__export">
            <a href="{{ route('data.effluent-plant.create') }}" class="ep-button ep-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="ep-button ep-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="ep-button ep-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="ep-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="ep-filterbar">
        <div class="ep-filterbar__field">
            <label for="date_from" class="ep-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="ep-filterbar__input">
        </div>

        <div class="ep-filterbar__field">
            <label for="date_to" class="ep-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="ep-filterbar__input">
        </div>

        <div class="ep-filterbar__field">
            <label for="business_unit_id" class="ep-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="ep-filterbar__input"
            />
        </div>
    </div>

    <div class="ep-table-wrap">
        <table class="ep-table">
            <thead class="ep-table__head">
                <tr>
                    <th>Effluent Plant ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.effluent-plant.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.effluent-plant.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="ep-table__row @if(!$hasDetailRoute) ep-table__row--static @endif"
                        wire:key="ep-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['effluent_plant_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="ep-badge ep-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="ep-table__row ep-table__row--static">
                        <td colspan="4">
                            <div class="ep-empty">
                                <div class="ep-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="ep-empty__title">Tidak ada data</p>
                                <p class="ep-empty__subtitle">Tidak ada data effluent plant yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="ep-pagination">
            <span class="ep-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="ep-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="ep-button ep-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="ep-button ep-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-threshing.blade.php. Class names use a
           `ep-` prefix (distinct from `th-`/`gr-`/`ct-`) since Livewire
           views are not style-scoped. */
        .ep-browser {
            --ep-brand: #249360;
            --ep-brand-hover: #1d7a4e;
            --ep-destructive: #DC2626;
            --ep-text: #1f2937;
            --ep-text-muted: #6b7280;
            --ep-border: #d1d5db;
            --ep-radius-input: 6px;
            --ep-radius-button: 8px;
            color: var(--ep-text);
        }

        .ep-browser--busy { opacity: 0.85; }

        .ep-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .ep-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .ep-browser__subtitle { margin: 0; font-size: 14px; color: var(--ep-text-muted); }
        .ep-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .ep-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--ep-radius-input); background: #fef2f2; border: 1px solid var(--ep-destructive); color: var(--ep-destructive); font-size: 14px; }

        .ep-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--ep-border); border-radius: 10px; }
        .ep-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .ep-filterbar__label { font-size: 13px; font-weight: 500; color: var(--ep-text-muted); }
        .ep-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--ep-text); border: 1px solid var(--ep-border); border-radius: var(--ep-radius-input); background: #fff; }
        .ep-filterbar__input:focus { outline: none; border-color: var(--ep-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .ep-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--ep-border); border-radius: 10px; background: #fff; }
        .ep-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .ep-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--ep-text-muted); border-bottom: 1px solid var(--ep-border); white-space: nowrap; }
        .ep-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--ep-border); white-space: nowrap; }
        .ep-table__row:last-child td { border-bottom: none; }
        .ep-table__row:not(.ep-table__row--static) { cursor: pointer; }
        .ep-table__row:not(.ep-table__row--static):hover { background: #f0fdf4; }

        .ep-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--ep-text-muted); }
        .ep-badge--saved, .ep-badge--synced { background: #f0fdf4; color: #16a34a; }
        .ep-badge--draft_ongoing, .ep-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .ep-empty { padding: 48px 16px; text-align: center; }
        .ep-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .ep-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--ep-text); }
        .ep-empty__subtitle { margin: 0; font-size: 13px; color: var(--ep-text-muted); }

        .ep-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--ep-text-muted); }
        .ep-pagination__controls { display: flex; gap: 8px; }

        .ep-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--ep-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .ep-button--secondary { background: var(--ep-brand); color: #fff; }
        .ep-button--secondary:hover { background: var(--ep-brand-hover); }
        .ep-button--ghost { background: #fff; color: var(--ep-text); border: 1px solid var(--ep-border); }
        .ep-button--ghost:hover:not(:disabled) { border-color: var(--ep-brand); color: var(--ep-brand); }
        .ep-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
