<div class="pw-browser" wire:loading.class="pw-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="pw-browser__header">
        <div>
            <h2 class="pw-browser__title">Data Browser Process Water</h2>
            <p class="pw-browser__subtitle">Riwayat data log sheet stasiun Process Water</p>
        </div>

        <div class="pw-browser__export">
            <a href="{{ route('data.process-water.create') }}" class="pw-button pw-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="pw-button pw-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="pw-button pw-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="pw-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="pw-filterbar">
        <div class="pw-filterbar__field">
            <label for="date_from" class="pw-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="pw-filterbar__input">
        </div>

        <div class="pw-filterbar__field">
            <label for="date_to" class="pw-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="pw-filterbar__input">
        </div>

        <div class="pw-filterbar__field">
            <label for="business_unit_id" class="pw-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="pw-filterbar__input"
            />
        </div>
    </div>

    <div class="pw-table-wrap">
        <table class="pw-table">
            <thead class="pw-table__head">
                <tr>
                    <th>Process Water ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.process-water.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.process-water.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="pw-table__row @if(!$hasDetailRoute) pw-table__row--static @endif"
                        wire:key="pw-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['process_water_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="pw-badge pw-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="pw-table__row pw-table__row--static">
                        <td colspan="4">
                            <div class="pw-empty">
                                <div class="pw-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="pw-empty__title">Tidak ada data</p>
                                <p class="pw-empty__subtitle">Tidak ada data process water yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="pw-pagination">
            <span class="pw-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="pw-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="pw-button pw-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="pw-button pw-button--ghost"
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
           `pw-` prefix (distinct from `th-`/`gr-`/`ct-`) since Livewire
           views are not style-scoped. */
        .pw-browser {
            --pw-brand: #249360;
            --pw-brand-hover: #1d7a4e;
            --pw-destructive: #DC2626;
            --pw-text: #1f2937;
            --pw-text-muted: #6b7280;
            --pw-border: #d1d5db;
            --pw-radius-input: 6px;
            --pw-radius-button: 8px;
            color: var(--pw-text);
        }

        .pw-browser--busy { opacity: 0.85; }

        .pw-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .pw-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pw-browser__subtitle { margin: 0; font-size: 14px; color: var(--pw-text-muted); }
        .pw-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .pw-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--pw-radius-input); background: #fef2f2; border: 1px solid var(--pw-destructive); color: var(--pw-destructive); font-size: 14px; }

        .pw-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--pw-border); border-radius: 10px; }
        .pw-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .pw-filterbar__label { font-size: 13px; font-weight: 500; color: var(--pw-text-muted); }
        .pw-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--pw-text); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-input); background: #fff; }
        .pw-filterbar__input:focus { outline: none; border-color: var(--pw-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .pw-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--pw-border); border-radius: 10px; background: #fff; }
        .pw-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .pw-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--pw-text-muted); border-bottom: 1px solid var(--pw-border); white-space: nowrap; }
        .pw-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--pw-border); white-space: nowrap; }
        .pw-table__row:last-child td { border-bottom: none; }
        .pw-table__row:not(.pw-table__row--static) { cursor: pointer; }
        .pw-table__row:not(.pw-table__row--static):hover { background: #f0fdf4; }

        .pw-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--pw-text-muted); }
        .pw-badge--saved, .pw-badge--synced { background: #f0fdf4; color: #16a34a; }
        .pw-badge--draft_ongoing, .pw-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .pw-empty { padding: 48px 16px; text-align: center; }
        .pw-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .pw-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--pw-text); }
        .pw-empty__subtitle { margin: 0; font-size: 13px; color: var(--pw-text-muted); }

        .pw-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--pw-text-muted); }
        .pw-pagination__controls { display: flex; gap: 8px; }

        .pw-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--pw-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .pw-button--secondary { background: var(--pw-brand); color: #fff; }
        .pw-button--secondary:hover { background: var(--pw-brand-hover); }
        .pw-button--ghost { background: #fff; color: var(--pw-text); border: 1px solid var(--pw-border); }
        .pw-button--ghost:hover:not(:disabled) { border-color: var(--pw-brand); color: var(--pw-brand); }
        .pw-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
