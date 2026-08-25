<div class="dp-browser" wire:loading.class="dp-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="dp-browser__header">
        <div>
            <h2 class="dp-browser__title">Data Browser Depricarping</h2>
            <p class="dp-browser__subtitle">Riwayat data log sheet stasiun Depricarping</p>
        </div>

        <div class="dp-browser__export">
            <a href="{{ route('data.depricarping.create') }}" class="dp-button dp-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="dp-button dp-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="dp-button dp-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="dp-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="dp-filterbar">
        <div class="dp-filterbar__field">
            <label for="date_from" class="dp-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="dp-filterbar__input">
        </div>

        <div class="dp-filterbar__field">
            <label for="date_to" class="dp-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="dp-filterbar__input">
        </div>

        <div class="dp-filterbar__field">
            <label for="business_unit_id" class="dp-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="dp-filterbar__input"
            />
        </div>
    </div>

    <div class="dp-table-wrap">
        <table class="dp-table">
            <thead class="dp-table__head">
                <tr>
                    <th>Presser ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.depricarping.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.depricarping.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="dp-table__row @if(!$hasDetailRoute) dp-table__row--static @endif"
                        wire:key="dp-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['presser_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="dp-badge dp-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="dp-table__row dp-table__row--static">
                        <td colspan="4">
                            <div class="dp-empty">
                                <div class="dp-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="dp-empty__title">Tidak ada data</p>
                                <p class="dp-empty__subtitle">Tidak ada data depricarping yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="dp-pagination">
            <span class="dp-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="dp-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="dp-button dp-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="dp-button dp-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-pressing.blade.php. Class names use a
           `dp-` prefix (distinct from `wb-`/`gr-`/`ct-`/`th-`/`pr-`) since
           Livewire views are not style-scoped. */
        .dp-browser {
            --dp-brand: #249360;
            --dp-brand-hover: #1d7a4e;
            --dp-destructive: #DC2626;
            --dp-text: #1f2937;
            --dp-text-muted: #6b7280;
            --dp-border: #d1d5db;
            --dp-radius-input: 6px;
            --dp-radius-button: 8px;
            color: var(--dp-text);
        }

        .dp-browser--busy { opacity: 0.85; }

        .dp-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .dp-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .dp-browser__subtitle { margin: 0; font-size: 14px; color: var(--dp-text-muted); }
        .dp-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .dp-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--dp-radius-input); background: #fef2f2; border: 1px solid var(--dp-destructive); color: var(--dp-destructive); font-size: 14px; }

        .dp-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--dp-border); border-radius: 10px; }
        .dp-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .dp-filterbar__label { font-size: 13px; font-weight: 500; color: var(--dp-text-muted); }
        .dp-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--dp-text); border: 1px solid var(--dp-border); border-radius: var(--dp-radius-input); background: #fff; }
        .dp-filterbar__input:focus { outline: none; border-color: var(--dp-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .dp-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--dp-border); border-radius: 10px; background: #fff; }
        .dp-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .dp-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--dp-text-muted); border-bottom: 1px solid var(--dp-border); white-space: nowrap; }
        .dp-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--dp-border); white-space: nowrap; }
        .dp-table__row:last-child td { border-bottom: none; }
        .dp-table__row:not(.dp-table__row--static) { cursor: pointer; }
        .dp-table__row:not(.dp-table__row--static):hover { background: #f0fdf4; }

        .dp-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--dp-text-muted); }
        .dp-badge--saved, .dp-badge--synced { background: #f0fdf4; color: #16a34a; }
        .dp-badge--draft_ongoing, .dp-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .dp-empty { padding: 48px 16px; text-align: center; }
        .dp-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .dp-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--dp-text); }
        .dp-empty__subtitle { margin: 0; font-size: 13px; color: var(--dp-text-muted); }

        .dp-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--dp-text-muted); }
        .dp-pagination__controls { display: flex; gap: 8px; }

        .dp-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--dp-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .dp-button--secondary { background: var(--dp-brand); color: #fff; }
        .dp-button--secondary:hover { background: var(--dp-brand-hover); }
        .dp-button--ghost { background: #fff; color: var(--dp-text); border: 1px solid var(--dp-border); }
        .dp-button--ghost:hover:not(:disabled) { border-color: var(--dp-brand); color: var(--dp-brand); }
        .dp-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
