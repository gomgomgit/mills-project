<div class="th-browser" wire:loading.class="th-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="th-browser__header">
        <div>
            <h2 class="th-browser__title">Data Browser Threshing</h2>
            <p class="th-browser__subtitle">Riwayat data log sheet stasiun Threshing</p>
        </div>

        <div class="th-browser__export">
            <a href="{{ route('data.threshing.create') }}" class="th-button th-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="th-button th-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="th-button th-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="th-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="th-filterbar">
        <div class="th-filterbar__field">
            <label for="date_from" class="th-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="th-filterbar__input">
        </div>

        <div class="th-filterbar__field">
            <label for="date_to" class="th-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="th-filterbar__input">
        </div>

        <div class="th-filterbar__field">
            <label for="business_unit_id" class="th-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="th-filterbar__input"
            />
        </div>
    </div>

    <div class="th-table-wrap">
        <table class="th-table">
            <thead class="th-table__head">
                <tr>
                    <th>Thresher ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.threshing.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.threshing.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="th-table__row @if(!$hasDetailRoute) th-table__row--static @endif"
                        wire:key="th-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['thresher_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="th-badge th-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="th-table__row th-table__row--static">
                        <td colspan="4">
                            <div class="th-empty">
                                <div class="th-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="th-empty__title">Tidak ada data</p>
                                <p class="th-empty__subtitle">Tidak ada data threshing yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="th-pagination">
            <span class="th-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="th-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="th-button th-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="th-button th-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-cages-track.blade.php. Class names use a
           `th-` prefix (distinct from `wb-`/`gr-`/`ct-`) since Livewire
           views are not style-scoped. */
        .th-browser {
            --th-brand: #249360;
            --th-brand-hover: #1d7a4e;
            --th-destructive: #DC2626;
            --th-text: #1f2937;
            --th-text-muted: #6b7280;
            --th-border: #d1d5db;
            --th-radius-input: 6px;
            --th-radius-button: 8px;
            color: var(--th-text);
        }

        .th-browser--busy { opacity: 0.85; }

        .th-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .th-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .th-browser__subtitle { margin: 0; font-size: 14px; color: var(--th-text-muted); }
        .th-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .th-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--th-radius-input); background: #fef2f2; border: 1px solid var(--th-destructive); color: var(--th-destructive); font-size: 14px; }

        .th-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--th-border); border-radius: 10px; }
        .th-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .th-filterbar__label { font-size: 13px; font-weight: 500; color: var(--th-text-muted); }
        .th-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--th-text); border: 1px solid var(--th-border); border-radius: var(--th-radius-input); background: #fff; }
        .th-filterbar__input:focus { outline: none; border-color: var(--th-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .th-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--th-border); border-radius: 10px; background: #fff; }
        .th-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .th-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--th-text-muted); border-bottom: 1px solid var(--th-border); white-space: nowrap; }
        .th-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--th-border); white-space: nowrap; }
        .th-table__row:last-child td { border-bottom: none; }
        .th-table__row:not(.th-table__row--static) { cursor: pointer; }
        .th-table__row:not(.th-table__row--static):hover { background: #f0fdf4; }

        .th-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--th-text-muted); }
        .th-badge--saved, .th-badge--synced { background: #f0fdf4; color: #16a34a; }
        .th-badge--draft_ongoing, .th-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .th-empty { padding: 48px 16px; text-align: center; }
        .th-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .th-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--th-text); }
        .th-empty__subtitle { margin: 0; font-size: 13px; color: var(--th-text-muted); }

        .th-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--th-text-muted); }
        .th-pagination__controls { display: flex; gap: 8px; }

        .th-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--th-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .th-button--secondary { background: var(--th-brand); color: #fff; }
        .th-button--secondary:hover { background: var(--th-brand-hover); }
        .th-button--ghost { background: #fff; color: var(--th-text); border: 1px solid var(--th-border); }
        .th-button--ghost:hover:not(:disabled) { border-color: var(--th-brand); color: var(--th-brand); }
        .th-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
