<div class="kp-browser" wire:loading.class="kp-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="kp-browser__header">
        <div>
            <h2 class="kp-browser__title">Data Browser Kernel Plant</h2>
            <p class="kp-browser__subtitle">Riwayat data log sheet stasiun Kernel Plant</p>
        </div>

        <div class="kp-browser__export">
            <a href="{{ route('data.kernel-plant.create') }}" class="kp-button kp-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="kp-button kp-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="kp-button kp-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="kp-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="kp-filterbar">
        <div class="kp-filterbar__field">
            <label for="date_from" class="kp-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="kp-filterbar__input">
        </div>

        <div class="kp-filterbar__field">
            <label for="date_to" class="kp-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="kp-filterbar__input">
        </div>

        <div class="kp-filterbar__field">
            <label for="business_unit_id" class="kp-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="kp-filterbar__input"
            />
        </div>
    </div>

    <div class="kp-table-wrap">
        <table class="kp-table">
            <thead class="kp-table__head">
                <tr>
                    <th>Kernel Plant ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.kernel-plant.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.kernel-plant.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="kp-table__row @if(!$hasDetailRoute) kp-table__row--static @endif"
                        wire:key="kp-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['kernel_plant_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="kp-badge kp-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="kp-table__row kp-table__row--static">
                        <td colspan="4">
                            <div class="kp-empty">
                                <div class="kp-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="kp-empty__title">Tidak ada data</p>
                                <p class="kp-empty__subtitle">Tidak ada data kernel plant yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="kp-pagination">
            <span class="kp-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="kp-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="kp-button kp-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="kp-button kp-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-depricarping.blade.php. Class names use
           a `kp-` prefix (distinct from `wb-`/`gr-`/`ct-`/`th-`/`pr-`/`dp-`)
           since Livewire views are not style-scoped. */
        .kp-browser {
            --kp-brand: #249360;
            --kp-brand-hover: #1d7a4e;
            --kp-destructive: #DC2626;
            --kp-text: #1f2937;
            --kp-text-muted: #6b7280;
            --kp-border: #d1d5db;
            --kp-radius-input: 6px;
            --kp-radius-button: 8px;
            color: var(--kp-text);
        }

        .kp-browser--busy { opacity: 0.85; }

        .kp-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .kp-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kp-browser__subtitle { margin: 0; font-size: 14px; color: var(--kp-text-muted); }
        .kp-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .kp-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--kp-radius-input); background: #fef2f2; border: 1px solid var(--kp-destructive); color: var(--kp-destructive); font-size: 14px; }

        .kp-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--kp-border); border-radius: 10px; }
        .kp-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .kp-filterbar__label { font-size: 13px; font-weight: 500; color: var(--kp-text-muted); }
        .kp-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--kp-text); border: 1px solid var(--kp-border); border-radius: var(--kp-radius-input); background: #fff; }
        .kp-filterbar__input:focus { outline: none; border-color: var(--kp-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .kp-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--kp-border); border-radius: 10px; background: #fff; }
        .kp-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .kp-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--kp-text-muted); border-bottom: 1px solid var(--kp-border); white-space: nowrap; }
        .kp-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--kp-border); white-space: nowrap; }
        .kp-table__row:last-child td { border-bottom: none; }
        .kp-table__row:not(.kp-table__row--static) { cursor: pointer; }
        .kp-table__row:not(.kp-table__row--static):hover { background: #f0fdf4; }

        .kp-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--kp-text-muted); }
        .kp-badge--saved, .kp-badge--synced { background: #f0fdf4; color: #16a34a; }
        .kp-badge--draft_ongoing, .kp-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .kp-empty { padding: 48px 16px; text-align: center; }
        .kp-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .kp-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--kp-text); }
        .kp-empty__subtitle { margin: 0; font-size: 13px; color: var(--kp-text-muted); }

        .kp-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--kp-text-muted); }
        .kp-pagination__controls { display: flex; gap: 8px; }

        .kp-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--kp-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .kp-button--secondary { background: var(--kp-brand); color: #fff; }
        .kp-button--secondary:hover { background: var(--kp-brand-hover); }
        .kp-button--ghost { background: #fff; color: var(--kp-text); border: 1px solid var(--kp-border); }
        .kp-button--ghost:hover:not(:disabled) { border-color: var(--kp-brand); color: var(--kp-brand); }
        .kp-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
