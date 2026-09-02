<div class="er-browser" wire:loading.class="er-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="er-browser__header">
        <div>
            <h2 class="er-browser__title">Data Browser Engine Room</h2>
            <p class="er-browser__subtitle">Riwayat data log sheet stasiun Engine Room</p>
        </div>

        <div class="er-browser__export">
            <a href="{{ route('data.engine-room.create') }}" class="er-button er-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="er-button er-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="er-button er-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="er-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="er-filterbar">
        <div class="er-filterbar__field">
            <label for="date_from" class="er-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="er-filterbar__input">
        </div>

        <div class="er-filterbar__field">
            <label for="date_to" class="er-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="er-filterbar__input">
        </div>

        <div class="er-filterbar__field">
            <label for="business_unit_id" class="er-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="er-filterbar__input"
            />
        </div>
    </div>

    <div class="er-table-wrap">
        <table class="er-table">
            <thead class="er-table__head">
                <tr>
                    <th>Engine Room ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.engine-room.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.engine-room.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="er-table__row @if(!$hasDetailRoute) er-table__row--static @endif"
                        wire:key="er-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['engine_room_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="er-badge er-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="er-table__row er-table__row--static">
                        <td colspan="4">
                            <div class="er-empty">
                                <div class="er-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="er-empty__title">Tidak ada data</p>
                                <p class="er-empty__subtitle">Tidak ada data engine room yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="er-pagination">
            <span class="er-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="er-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="er-button er-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="er-button er-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-storage-tank.blade.php. Class names use
           an `er-` prefix (distinct from `th-`/`gr-`/`ct-`/`st-`) since
           Livewire views are not style-scoped. */
        .er-browser {
            --er-brand: #249360;
            --er-brand-hover: #1d7a4e;
            --er-destructive: #DC2626;
            --er-text: #1f2937;
            --er-text-muted: #6b7280;
            --er-border: #d1d5db;
            --er-radius-input: 6px;
            --er-radius-button: 8px;
            color: var(--er-text);
        }

        .er-browser--busy { opacity: 0.85; }

        .er-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .er-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .er-browser__subtitle { margin: 0; font-size: 14px; color: var(--er-text-muted); }
        .er-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .er-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--er-radius-input); background: #fef2f2; border: 1px solid var(--er-destructive); color: var(--er-destructive); font-size: 14px; }

        .er-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--er-border); border-radius: 10px; }
        .er-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .er-filterbar__label { font-size: 13px; font-weight: 500; color: var(--er-text-muted); }
        .er-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--er-text); border: 1px solid var(--er-border); border-radius: var(--er-radius-input); background: #fff; }
        .er-filterbar__input:focus { outline: none; border-color: var(--er-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .er-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--er-border); border-radius: 10px; background: #fff; }
        .er-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .er-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--er-text-muted); border-bottom: 1px solid var(--er-border); white-space: nowrap; }
        .er-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--er-border); white-space: nowrap; }
        .er-table__row:last-child td { border-bottom: none; }
        .er-table__row:not(.er-table__row--static) { cursor: pointer; }
        .er-table__row:not(.er-table__row--static):hover { background: #f0fdf4; }

        .er-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--er-text-muted); }
        .er-badge--saved, .er-badge--synced { background: #f0fdf4; color: #16a34a; }
        .er-badge--draft_ongoing, .er-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .er-empty { padding: 48px 16px; text-align: center; }
        .er-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .er-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--er-text); }
        .er-empty__subtitle { margin: 0; font-size: 13px; color: var(--er-text-muted); }

        .er-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--er-text-muted); }
        .er-pagination__controls { display: flex; gap: 8px; }

        .er-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--er-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .er-button--secondary { background: var(--er-brand); color: #fff; }
        .er-button--secondary:hover { background: var(--er-brand-hover); }
        .er-button--ghost { background: #fff; color: var(--er-text); border: 1px solid var(--er-border); }
        .er-button--ghost:hover:not(:disabled) { border-color: var(--er-brand); color: var(--er-brand); }
        .er-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
