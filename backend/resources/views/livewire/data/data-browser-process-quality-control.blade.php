<div class="pqc-browser" wire:loading.class="pqc-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="pqc-browser__header">
        <div>
            <h2 class="pqc-browser__title">Data Browser Process Quality Control</h2>
            <p class="pqc-browser__subtitle">Riwayat data log sheet stasiun Process Quality Control</p>
        </div>

        <div class="pqc-browser__export">
            <a href="{{ route('data.process-quality-control.create') }}" class="pqc-button pqc-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="pqc-button pqc-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="pqc-button pqc-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="pqc-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="pqc-filterbar">
        <div class="pqc-filterbar__field">
            <label for="date_from" class="pqc-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="pqc-filterbar__input">
        </div>

        <div class="pqc-filterbar__field">
            <label for="date_to" class="pqc-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="pqc-filterbar__input">
        </div>

        <div class="pqc-filterbar__field">
            <label for="business_unit_id" class="pqc-filterbar__label">Business Unit</label>
            <x-searchable-select
                id="business_unit_id"
                wire:model.live="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Semua Business Unit"
                class="pqc-filterbar__input"
            />
        </div>
    </div>

    <div class="pqc-table-wrap">
        <table class="pqc-table">
            <thead class="pqc-table__head">
                <tr>
                    <th>Process QC ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Baris Terisi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.process-quality-control.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.process-quality-control.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="pqc-table__row @if(!$hasDetailRoute) pqc-table__row--static @endif"
                        wire:key="pqc-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        <td>{{ $record['process_qc_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="pqc-badge pqc-badge--{{ $record['status'] }}">{{ $record['status'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="pqc-table__row pqc-table__row--static">
                        <td colspan="4">
                            <div class="pqc-empty">
                                <div class="pqc-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="pqc-empty__title">Tidak ada data</p>
                                <p class="pqc-empty__subtitle">Tidak ada data process quality control yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="pqc-pagination">
            <span class="pqc-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="pqc-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="pqc-button pqc-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="pqc-button pqc-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here, same
           approach as data-browser-clarification.blade.php. Class names use
           a `pqc-` prefix (distinct from `th-`/`gr-`/`ct-`/`cl-`) since
           Livewire views are not style-scoped. */
        .pqc-browser {
            --pqc-brand: #249360;
            --pqc-brand-hover: #1d7a4e;
            --pqc-destructive: #DC2626;
            --pqc-text: #1f2937;
            --pqc-text-muted: #6b7280;
            --pqc-border: #d1d5db;
            --pqc-radius-input: 6px;
            --pqc-radius-button: 8px;
            color: var(--pqc-text);
        }

        .pqc-browser--busy { opacity: 0.85; }

        .pqc-browser__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .pqc-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pqc-browser__subtitle { margin: 0; font-size: 14px; color: var(--pqc-text-muted); }
        .pqc-browser__export { display: flex; gap: 8px; flex-shrink: 0; }

        .pqc-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--pqc-radius-input); background: #fef2f2; border: 1px solid var(--pqc-destructive); color: var(--pqc-destructive); font-size: 14px; }

        .pqc-filterbar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid var(--pqc-border); border-radius: 10px; }
        .pqc-filterbar__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .pqc-filterbar__label { font-size: 13px; font-weight: 500; color: var(--pqc-text-muted); }
        .pqc-filterbar__input { padding: 8px 10px; font-size: 14px; font-family: inherit; color: var(--pqc-text); border: 1px solid var(--pqc-border); border-radius: var(--pqc-radius-input); background: #fff; }
        .pqc-filterbar__input:focus { outline: none; border-color: var(--pqc-brand); box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15); }

        .pqc-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--pqc-border); border-radius: 10px; background: #fff; }
        .pqc-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .pqc-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--pqc-text-muted); border-bottom: 1px solid var(--pqc-border); white-space: nowrap; }
        .pqc-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--pqc-border); white-space: nowrap; }
        .pqc-table__row:last-child td { border-bottom: none; }
        .pqc-table__row:not(.pqc-table__row--static) { cursor: pointer; }
        .pqc-table__row:not(.pqc-table__row--static):hover { background: #f0fdf4; }

        .pqc-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--pqc-text-muted); }
        .pqc-badge--saved, .pqc-badge--synced { background: #f0fdf4; color: #16a34a; }
        .pqc-badge--draft_ongoing, .pqc-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .pqc-empty { padding: 48px 16px; text-align: center; }
        .pqc-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .pqc-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--pqc-text); }
        .pqc-empty__subtitle { margin: 0; font-size: 13px; color: var(--pqc-text-muted); }

        .pqc-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--pqc-text-muted); }
        .pqc-pagination__controls { display: flex; gap: 8px; }

        .pqc-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--pqc-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .pqc-button--secondary { background: var(--pqc-brand); color: #fff; }
        .pqc-button--secondary:hover { background: var(--pqc-brand-hover); }
        .pqc-button--ghost { background: #fff; color: var(--pqc-text); border: 1px solid var(--pqc-border); }
        .pqc-button--ghost:hover:not(:disabled) { border-color: var(--pqc-brand); color: var(--pqc-brand); }
        .pqc-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
