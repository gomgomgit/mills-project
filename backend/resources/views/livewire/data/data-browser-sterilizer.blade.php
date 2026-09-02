<div class="sf-browser" wire:loading.class="sf-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="sf-browser__header">
        <div>
            <h2 class="sf-browser__title">Data Browser Sterilizer</h2>
            <p class="sf-browser__subtitle">Riwayat data log sheet stasiun Sterilizer</p>
        </div>

        <div class="sf-browser__export">
            <a href="{{ route('data.sterilizer.create') }}" class="sf-button sf-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="sf-button sf-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="sf-button sf-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="sf-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="sf-filterbar">
        <div class="sf-filterbar__field">
            <label for="date_from" class="sf-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="sf-filterbar__input">
        </div>

        <div class="sf-filterbar__field">
            <label for="date_to" class="sf-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="sf-filterbar__input">
        </div>

        <div class="sf-filterbar__field">
            <label for="business_unit_id" class="sf-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="sf-filterbar__input"
            />
        </div>
    </div>

    <div class="sf-table-wrap">
        <table class="sf-table">
            <thead class="sf-table__head">
                <tr>
                    <th>Sterilizer ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Siklus</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.sterilizer.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.sterilizer.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="sf-table__row @if(!$hasDetailRoute) sf-table__row--static @endif"
                        wire:key="sf-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['sterilizer_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['cycle_count'] }}</td>
                        <td>
                            <span class="sf-badge sf-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="sf-table__row sf-table__row--static">
                        <td colspan="4">
                            <div class="sf-empty">
                                <div class="sf-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="sf-empty__title">Tidak ada data</p>
                                <p class="sf-empty__subtitle">Tidak ada data sterilizer yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="sf-pagination">
            <span class="sf-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="sf-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="sf-button sf-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="sf-button sf-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        .sf-browser {
            --sf-brand: #249360;
            --sf-brand-hover: #1d7a4e;
            --sf-destructive: #DC2626;
            --sf-text: #1f2937;
            --sf-text-muted: #6b7280;
            --sf-border: #d1d5db;
            --sf-radius-input: 6px;
            --sf-radius-button: 8px;
            color: var(--sf-text);
        }

        .sf-browser--busy { opacity: 0.85; }

        .sf-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .sf-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .sf-browser__subtitle { margin: 0; font-size: 14px; color: var(--sf-text-muted); }
        .sf-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .sf-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--sf-radius-input); background: #fef2f2; border: 1px solid var(--sf-destructive); color: var(--sf-destructive); font-size: 14px; }

        .sf-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--sf-border); border-radius: 10px; }
        .sf-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .sf-filterbar__label { font-size: 13px; font-weight: 500; color: var(--sf-text-muted); }
        .sf-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--sf-text); border: 1px solid var(--sf-border); border-radius: var(--sf-radius-input); background: #fff; }
        .sf-filterbar__input:focus { outline: none; border-color: var(--sf-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .sf-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--sf-border); border-radius: 10px; background: #fff; }
        .sf-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .sf-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--sf-text-muted); border-bottom: 1px solid var(--sf-border); white-space: nowrap; }
        .sf-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--sf-border); white-space: nowrap; }
        .sf-table__row:last-child td { border-bottom: none; }
        .sf-table__row:not(.sf-table__row--static) { cursor: pointer; }
        .sf-table__row:not(.sf-table__row--static):hover { background: #f0fdf4; }

        .sf-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--sf-text-muted); }
        .sf-badge--saved, .sf-badge--synced { background: #f0fdf4; color: #16a34a; }
        .sf-badge--draft_ongoing, .sf-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .sf-empty { padding: 48px 16px; text-align: center; }
        .sf-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .sf-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--sf-text); }
        .sf-empty__subtitle { margin: 0; font-size: 13px; color: var(--sf-text-muted); }

        .sf-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--sf-text-muted); }
        .sf-pagination__controls { display: flex; gap: 8px; }

        .sf-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--sf-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .sf-button--secondary { background: var(--sf-brand); color: #fff; }
        .sf-button--secondary:hover { background: var(--sf-brand-hover); }
        .sf-button--ghost { background: #fff; color: var(--sf-text); border: 1px solid var(--sf-border); }
        .sf-button--ghost:hover:not(:disabled) { border-color: var(--sf-brand); color: var(--sf-brand); }
        .sf-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
