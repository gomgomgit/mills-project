<div class="kd-browser" wire:loading.class="kd-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="kd-browser__header">
        <div>
            <h2 class="kd-browser__title">Data Browser Kernel Dispatch</h2>
            <p class="kd-browser__subtitle">Riwayat data log sheet stasiun Kernel Dispatch</p>
        </div>

        <div class="kd-browser__export">
            <a href="{{ route('data.kernel-dispatch.create') }}" class="kd-button kd-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="kd-button kd-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="kd-button kd-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="kd-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="kd-filterbar">
        <div class="kd-filterbar__field">
            <label for="date_from" class="kd-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="kd-filterbar__input">
        </div>

        <div class="kd-filterbar__field">
            <label for="date_to" class="kd-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="kd-filterbar__input">
        </div>

        <div class="kd-filterbar__field">
            <label for="business_unit_id" class="kd-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="kd-filterbar__input"
            />
        </div>
    </div>

    <div class="kd-table-wrap">
        <table class="kd-table">
            <thead class="kd-table__head">
                <tr>
                    <th>Kernel Dispatch ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Kejadian</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.kernel-dispatch.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.kernel-dispatch.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="kd-table__row @if(!$hasDetailRoute) kd-table__row--static @endif"
                        wire:key="kd-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['kernel_dispatch_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['event_count'] }}</td>
                        <td>
                            <span class="kd-badge kd-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="kd-table__row kd-table__row--static">
                        <td colspan="4">
                            <div class="kd-empty">
                                <div class="kd-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="kd-empty__title">Tidak ada data</p>
                                <p class="kd-empty__subtitle">Tidak ada data kernel dispatch yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="kd-pagination">
            <span class="kd-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="kd-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="kd-button kd-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="kd-button kd-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        .kd-browser {
            --kd-brand: #249360;
            --kd-brand-hover: #1d7a4e;
            --kd-destructive: #DC2626;
            --kd-text: #1f2937;
            --kd-text-muted: #6b7280;
            --kd-border: #d1d5db;
            --kd-radius-input: 6px;
            --kd-radius-button: 8px;
            color: var(--kd-text);
        }

        .kd-browser--busy { opacity: 0.85; }

        .kd-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .kd-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kd-browser__subtitle { margin: 0; font-size: 14px; color: var(--kd-text-muted); }
        .kd-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .kd-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--kd-radius-input); background: #fef2f2; border: 1px solid var(--kd-destructive); color: var(--kd-destructive); font-size: 14px; }

        .kd-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--kd-border); border-radius: 10px; }
        .kd-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .kd-filterbar__label { font-size: 13px; font-weight: 500; color: var(--kd-text-muted); }
        .kd-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--kd-text); border: 1px solid var(--kd-border); border-radius: var(--kd-radius-input); background: #fff; }
        .kd-filterbar__input:focus { outline: none; border-color: var(--kd-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .kd-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--kd-border); border-radius: 10px; background: #fff; }
        .kd-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .kd-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--kd-text-muted); border-bottom: 1px solid var(--kd-border); white-space: nowrap; }
        .kd-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--kd-border); white-space: nowrap; }
        .kd-table__row:last-child td { border-bottom: none; }
        .kd-table__row:not(.kd-table__row--static) { cursor: pointer; }
        .kd-table__row:not(.kd-table__row--static):hover { background: #f0fdf4; }

        .kd-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--kd-text-muted); }
        .kd-badge--saved, .kd-badge--synced { background: #f0fdf4; color: #16a34a; }
        .kd-badge--draft_ongoing, .kd-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .kd-empty { padding: 48px 16px; text-align: center; }
        .kd-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .kd-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--kd-text); }
        .kd-empty__subtitle { margin: 0; font-size: 13px; color: var(--kd-text-muted); }

        .kd-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--kd-text-muted); }
        .kd-pagination__controls { display: flex; gap: 8px; }

        .kd-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--kd-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .kd-button--secondary { background: var(--kd-brand); color: #fff; }
        .kd-button--secondary:hover { background: var(--kd-brand-hover); }
        .kd-button--ghost { background: #fff; color: var(--kd-text); border: 1px solid var(--kd-border); }
        .kd-button--ghost:hover:not(:disabled) { border-color: var(--kd-brand); color: var(--kd-brand); }
        .kd-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
