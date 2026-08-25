<div class="pr-browser" wire:loading.class="pr-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="pr-browser__header">
        <div>
            <h2 class="pr-browser__title">Data Browser Pressing</h2>
            <p class="pr-browser__subtitle">Riwayat data log sheet stasiun Pressing</p>
        </div>

        <div class="pr-browser__export">
            <a href="{{ route('data.pressing.create') }}" class="pr-button pr-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="pr-button pr-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="pr-button pr-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="pr-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="pr-filterbar">
        <div class="pr-filterbar__field">
            <label for="date_from" class="pr-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="pr-filterbar__input">
        </div>

        <div class="pr-filterbar__field">
            <label for="date_to" class="pr-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="pr-filterbar__input">
        </div>

        <div class="pr-filterbar__field">
            <label for="business_unit_id" class="pr-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="pr-filterbar__input"
            />
        </div>
    </div>

    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead class="pr-table__head">
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
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.pressing.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.pressing.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="pr-table__row @if(!$hasDetailRoute) pr-table__row--static @endif"
                        wire:key="pr-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['presser_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="pr-badge pr-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="pr-table__row pr-table__row--static">
                        <td colspan="4">
                            <div class="pr-empty">
                                <div class="pr-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="pr-empty__title">Tidak ada data</p>
                                <p class="pr-empty__subtitle">Tidak ada data pressing yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="pr-pagination">
            <span class="pr-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="pr-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="pr-button pr-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="pr-button pr-button--ghost"
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
           `pr-` prefix (distinct from `wb-`/`gr-`/`ct-`/`th-`) since
           Livewire views are not style-scoped. */
        .pr-browser {
            --pr-brand: #249360;
            --pr-brand-hover: #1d7a4e;
            --pr-destructive: #DC2626;
            --pr-text: #1f2937;
            --pr-text-muted: #6b7280;
            --pr-border: #d1d5db;
            --pr-radius-input: 6px;
            --pr-radius-button: 8px;
            color: var(--pr-text);
        }

        .pr-browser--busy { opacity: 0.85; }

        .pr-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .pr-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pr-browser__subtitle { margin: 0; font-size: 14px; color: var(--pr-text-muted); }
        .pr-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .pr-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--pr-radius-input); background: #fef2f2; border: 1px solid var(--pr-destructive); color: var(--pr-destructive); font-size: 14px; }

        .pr-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--pr-border); border-radius: 10px; }
        .pr-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .pr-filterbar__label { font-size: 13px; font-weight: 500; color: var(--pr-text-muted); }
        .pr-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--pr-text); border: 1px solid var(--pr-border); border-radius: var(--pr-radius-input); background: #fff; }
        .pr-filterbar__input:focus { outline: none; border-color: var(--pr-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .pr-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--pr-border); border-radius: 10px; background: #fff; }
        .pr-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .pr-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--pr-text-muted); border-bottom: 1px solid var(--pr-border); white-space: nowrap; }
        .pr-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--pr-border); white-space: nowrap; }
        .pr-table__row:last-child td { border-bottom: none; }
        .pr-table__row:not(.pr-table__row--static) { cursor: pointer; }
        .pr-table__row:not(.pr-table__row--static):hover { background: #f0fdf4; }

        .pr-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--pr-text-muted); }
        .pr-badge--saved, .pr-badge--synced { background: #f0fdf4; color: #16a34a; }
        .pr-badge--draft_ongoing, .pr-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .pr-empty { padding: 48px 16px; text-align: center; }
        .pr-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .pr-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--pr-text); }
        .pr-empty__subtitle { margin: 0; font-size: 13px; color: var(--pr-text-muted); }

        .pr-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--pr-text-muted); }
        .pr-pagination__controls { display: flex; gap: 8px; }

        .pr-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--pr-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .pr-button--secondary { background: var(--pr-brand); color: #fff; }
        .pr-button--secondary:hover { background: var(--pr-brand-hover); }
        .pr-button--ghost { background: #fff; color: var(--pr-text); border: 1px solid var(--pr-border); }
        .pr-button--ghost:hover:not(:disabled) { border-color: var(--pr-brand); color: var(--pr-brand); }
        .pr-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
