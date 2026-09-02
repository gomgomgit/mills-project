<div class="cl-browser" wire:loading.class="cl-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="cl-browser__header">
        <div>
            <h2 class="cl-browser__title">Data Browser Clarification</h2>
            <p class="cl-browser__subtitle">Riwayat data log sheet stasiun Clarification</p>
        </div>

        <div class="cl-browser__export">
            <a href="{{ route('data.clarification.create') }}" class="cl-button cl-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="cl-button cl-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="cl-button cl-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="cl-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="cl-filterbar">
        <div class="cl-filterbar__field">
            <label for="date_from" class="cl-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="cl-filterbar__input">
        </div>

        <div class="cl-filterbar__field">
            <label for="date_to" class="cl-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="cl-filterbar__input">
        </div>

        <div class="cl-filterbar__field">
            <label for="business_unit_id" class="cl-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="cl-filterbar__input"
            />
        </div>
    </div>

    <div class="cl-table-wrap">
        <table class="cl-table">
            <thead class="cl-table__head">
                <tr>
                    <th>Clarification ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.clarification.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.clarification.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="cl-table__row @if(!$hasDetailRoute) cl-table__row--static @endif"
                        wire:key="cl-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['clarification_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="cl-badge cl-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="cl-table__row cl-table__row--static">
                        <td colspan="4">
                            <div class="cl-empty">
                                <div class="cl-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="cl-empty__title">Tidak ada data</p>
                                <p class="cl-empty__subtitle">Tidak ada data clarification yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="cl-pagination">
            <span class="cl-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="cl-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="cl-button cl-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="cl-button cl-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-boiler-room.blade.php. Class names use
           a `cl-` prefix (distinct from `th-`/`gr-`/`ct-`/`br-`) since
           Livewire views are not style-scoped. */
        .cl-browser {
            --cl-brand: #249360;
            --cl-brand-hover: #1d7a4e;
            --cl-destructive: #DC2626;
            --cl-text: #1f2937;
            --cl-text-muted: #6b7280;
            --cl-border: #d1d5db;
            --cl-radius-input: 6px;
            --cl-radius-button: 8px;
            color: var(--cl-text);
        }

        .cl-browser--busy { opacity: 0.85; }

        .cl-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .cl-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .cl-browser__subtitle { margin: 0; font-size: 14px; color: var(--cl-text-muted); }
        .cl-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .cl-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--cl-radius-input); background: #fef2f2; border: 1px solid var(--cl-destructive); color: var(--cl-destructive); font-size: 14px; }

        .cl-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--cl-border); border-radius: 10px; }
        .cl-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .cl-filterbar__label { font-size: 13px; font-weight: 500; color: var(--cl-text-muted); }
        .cl-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--cl-text); border: 1px solid var(--cl-border); border-radius: var(--cl-radius-input); background: #fff; }
        .cl-filterbar__input:focus { outline: none; border-color: var(--cl-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .cl-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--cl-border); border-radius: 10px; background: #fff; }
        .cl-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .cl-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--cl-text-muted); border-bottom: 1px solid var(--cl-border); white-space: nowrap; }
        .cl-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--cl-border); white-space: nowrap; }
        .cl-table__row:last-child td { border-bottom: none; }
        .cl-table__row:not(.cl-table__row--static) { cursor: pointer; }
        .cl-table__row:not(.cl-table__row--static):hover { background: #f0fdf4; }

        .cl-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--cl-text-muted); }
        .cl-badge--saved, .cl-badge--synced { background: #f0fdf4; color: #16a34a; }
        .cl-badge--draft_ongoing, .cl-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .cl-empty { padding: 48px 16px; text-align: center; }
        .cl-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .cl-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--cl-text); }
        .cl-empty__subtitle { margin: 0; font-size: 13px; color: var(--cl-text-muted); }

        .cl-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--cl-text-muted); }
        .cl-pagination__controls { display: flex; gap: 8px; }

        .cl-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--cl-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .cl-button--secondary { background: var(--cl-brand); color: #fff; }
        .cl-button--secondary:hover { background: var(--cl-brand-hover); }
        .cl-button--ghost { background: #fff; color: var(--cl-text); border: 1px solid var(--cl-border); }
        .cl-button--ghost:hover:not(:disabled) { border-color: var(--cl-brand); color: var(--cl-brand); }
        .cl-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
