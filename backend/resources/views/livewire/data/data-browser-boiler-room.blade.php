<div class="br-browser" wire:loading.class="br-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="br-browser__header">
        <div>
            <h2 class="br-browser__title">Data Browser Boiler Room</h2>
            <p class="br-browser__subtitle">Riwayat data log sheet stasiun Boiler Room</p>
        </div>

        <div class="br-browser__export">
            <a href="{{ route('data.boiler-room.create') }}" class="br-button br-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="br-button br-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="br-button br-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="br-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="br-filterbar">
        <div class="br-filterbar__field">
            <label for="date_from" class="br-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="br-filterbar__input">
        </div>

        <div class="br-filterbar__field">
            <label for="date_to" class="br-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="br-filterbar__input">
        </div>

        <div class="br-filterbar__field">
            <label for="business_unit_id" class="br-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="br-filterbar__input"
            />
        </div>
    </div>

    <div class="br-table-wrap">
        <table class="br-table">
            <thead class="br-table__head">
                <tr>
                    <th>Boiler Room ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.boiler-room.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.boiler-room.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="br-table__row @if(!$hasDetailRoute) br-table__row--static @endif"
                        wire:key="br-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['boiler_room_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="br-badge br-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="br-table__row br-table__row--static">
                        <td colspan="4">
                            <div class="br-empty">
                                <div class="br-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="br-empty__title">Tidak ada data</p>
                                <p class="br-empty__subtitle">Tidak ada data boiler room yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="br-pagination">
            <span class="br-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="br-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="br-button br-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="br-button br-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-engine-room.blade.php. Class names use
           a `br-` prefix (distinct from `th-`/`gr-`/`ct-`/`er-`) since
           Livewire views are not style-scoped. */
        .br-browser {
            --br-brand: #249360;
            --br-brand-hover: #1d7a4e;
            --br-destructive: #DC2626;
            --br-text: #1f2937;
            --br-text-muted: #6b7280;
            --br-border: #d1d5db;
            --br-radius-input: 6px;
            --br-radius-button: 8px;
            color: var(--br-text);
        }

        .br-browser--busy { opacity: 0.85; }

        .br-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .br-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .br-browser__subtitle { margin: 0; font-size: 14px; color: var(--br-text-muted); }
        .br-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .br-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--br-radius-input); background: #fef2f2; border: 1px solid var(--br-destructive); color: var(--br-destructive); font-size: 14px; }

        .br-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--br-border); border-radius: 10px; }
        .br-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .br-filterbar__label { font-size: 13px; font-weight: 500; color: var(--br-text-muted); }
        .br-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--br-text); border: 1px solid var(--br-border); border-radius: var(--br-radius-input); background: #fff; }
        .br-filterbar__input:focus { outline: none; border-color: var(--br-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .br-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--br-border); border-radius: 10px; background: #fff; }
        .br-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .br-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--br-text-muted); border-bottom: 1px solid var(--br-border); white-space: nowrap; }
        .br-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--br-border); white-space: nowrap; }
        .br-table__row:last-child td { border-bottom: none; }
        .br-table__row:not(.br-table__row--static) { cursor: pointer; }
        .br-table__row:not(.br-table__row--static):hover { background: #f0fdf4; }

        .br-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--br-text-muted); }
        .br-badge--saved, .br-badge--synced { background: #f0fdf4; color: #16a34a; }
        .br-badge--draft_ongoing, .br-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .br-empty { padding: 48px 16px; text-align: center; }
        .br-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .br-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--br-text); }
        .br-empty__subtitle { margin: 0; font-size: 13px; color: var(--br-text-muted); }

        .br-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--br-text-muted); }
        .br-pagination__controls { display: flex; gap: 8px; }

        .br-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--br-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .br-button--secondary { background: var(--br-brand); color: #fff; }
        .br-button--secondary:hover { background: var(--br-brand-hover); }
        .br-button--ghost { background: #fff; color: var(--br-text); border: 1px solid var(--br-border); }
        .br-button--ghost:hover:not(:disabled) { border-color: var(--br-brand); color: var(--br-brand); }
        .br-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
