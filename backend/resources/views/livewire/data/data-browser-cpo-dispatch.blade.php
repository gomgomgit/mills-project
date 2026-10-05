<div class="cd-browser" wire:loading.class="cd-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="cd-browser__header">
        <div>
            <h2 class="cd-browser__title">Data Browser CPO Dispatch</h2>
            <p class="cd-browser__subtitle">Riwayat data log sheet stasiun CPO Dispatch</p>
        </div>

        <div class="cd-browser__export">
            <a href="{{ route('data.cpo-dispatch.create') }}" class="cd-button cd-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="cd-button cd-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor CSV</x-busy-label>
            </a>
            <a href="{{ $exportExcelUrl }}" class="cd-button cd-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor Excel</x-busy-label>
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="cd-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Filter bersama x-filter.bar (components/filter/*, CSS di
         components/filter-assets.blade.php). Binding, id, dan opsi sama
         persis dengan filterbar lama; yang berubah tampilannya:
         - rentang tanggal dalam satu field (ketik manual + pemilih);
         - Business Unit hanya PEMILIH bagi Admin — akun terikat mill
           melihat keterangan mill-nya (bukan input disabled), karena
           render() memang memaku filter itu ke mill akun;
         - ringkasan jumlah data, jumlah filter aktif, dan Reset filter. --}}
    @php
        $fbIsAdmin = auth()->user()?->role === \App\Enums\UserRole::Admin;
    @endphp
    <x-filter.bar label="Filter data" :total="$meta['total']"
                  :active="$this->activeFilterCount($fbIsAdmin ? [] : ['business_unit_id'])" reset="resetFilters">
        <x-filter.field label="Tanggal" size="range">
            <x-filter.date-range />
        </x-filter.field>

        @if ($fbIsAdmin)
            <x-filter.field label="Business Unit" for="business_unit_id" icon="mill">
                <x-searchable-select
                    id="business_unit_id"
                    wire:model.live="business_unit_id"
                    :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                    placeholder="Semua Business Unit"
                    class="fb-control"
                />
            </x-filter.field>
        @else
            <x-filter.field label="Business Unit" icon="mill"
                            :static="collect($businessUnits)->first()?->name ?? 'Belum terikat mill'"
                            static-testid="business-unit-current" static-title="Mill mengikuti akun Anda" />
        @endif

        <x-filter.field label="Production Line" for="production_line_id" icon="line">
            <x-searchable-select
                id="production_line_id"
                wire:model.live="production_line_id"
                :options="collect($productionLines)->map(fn ($line) => ['value' => $line['id'], 'label' => $line['name']])->all()"
                placeholder="Semua Line"
                class="fb-control"
            />
        </x-filter.field>
    </x-filter.bar>

    <div class="cd-table-wrap ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="date_from,date_to,business_unit_id,production_line_id,resetFilters,previousPage,nextPage,goToPage">
        <table class="cd-table">
            <thead class="cd-table__head">
                <tr>
                    <th>Production Line</th>
                    <th>CPO Dispatch ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Kejadian</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.cpo-dispatch.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.cpo-dispatch.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="cd-table__row @if(!$hasDetailRoute) cd-table__row--static @endif"
                        wire:key="cd-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        {{-- Diambil dari KOLOM RECORD (production_line_name), bukan
                             dari station->productionLine: untuk record lama yang
                             stasiunnya sudah dipindah, keduanya berbeda — dan yang
                             benar adalah line tempat data itu benar-benar dihasilkan. --}}
                        <td>{{ $record['production_line_name'] ?? '-' }}</td>
                        <td>{{ $record['cpo_dispatch_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['event_count'] }}</td>
                        <td>
                            <span class="cd-badge cd-badge--{{ $record['status'] }}">{{ \App\Support\Display::status($record['status']) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="cd-table__row cd-table__row--static">
                        <td colspan="5">
                            <div class="cd-empty">
                                <div class="cd-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="cd-empty__title">Tidak ada data</p>
                                <p class="cd-empty__subtitle">Tidak ada data cpo dispatch yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="cd-pagination">
            <span class="cd-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="cd-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
                    class="cd-button cd-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
                    class="cd-button cd-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        .cd-browser {
            --cd-brand: #249360;
            --cd-brand-hover: #1d7a4e;
            --cd-destructive: #DC2626;
            --cd-text: #1f2937;
            --cd-text-muted: #6b7280;
            --cd-border: #d1d5db;
            --cd-radius-input: 6px;
            --cd-radius-button: 8px;
            color: var(--cd-text);
        }

        .cd-browser--busy { opacity: 0.85; }

        .cd-browser__header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .cd-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .cd-browser__subtitle { margin: 0; font-size: 14px; color: var(--cd-text-muted); }
        /* flex-shrink 1 + min-width 0 (2026-10-05): di ponsel tombol boleh turun baris di dalam
           wadah ini — teks sibuk "Mengekspor…" lebih lebar dari "Ekspor CSV" dan dulu terpotong. */
        .cd-browser__export { display: flex; flex-wrap: wrap; gap: 8px; flex-shrink: 1; min-width: 0; }

        .cd-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--cd-radius-input); background: #fef2f2; border: 1px solid var(--cd-destructive); color: var(--cd-destructive); font-size: 14px; }


        .cd-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--cd-border); border-radius: 10px; background: #fff; }
        .cd-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .cd-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--cd-text-muted); border-bottom: 1px solid var(--cd-border); white-space: nowrap; }
        .cd-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--cd-border); white-space: nowrap; }
        .cd-table__row:last-child td { border-bottom: none; }
        .cd-table__row:not(.cd-table__row--static) { cursor: pointer; }
        .cd-table__row:not(.cd-table__row--static):hover { background: #f0fdf4; }

        .cd-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--cd-text-muted); }
        .cd-badge--saved, .cd-badge--synced { background: #f0fdf4; color: #16a34a; }
        .cd-badge--draft_ongoing, .cd-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .cd-empty { padding: 48px 16px; text-align: center; }
        .cd-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .cd-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--cd-text); }
        .cd-empty__subtitle { margin: 0; font-size: 13px; color: var(--cd-text-muted); }

        .cd-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--cd-text-muted); }
        .cd-pagination__controls { display: flex; gap: 8px; }

        .cd-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--cd-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .cd-button--secondary { background: var(--cd-brand); color: #fff; }
        .cd-button--secondary:hover { background: var(--cd-brand-hover); }
        .cd-button--ghost { background: #fff; color: var(--cd-text); border: 1px solid var(--cd-border); }
        .cd-button--ghost:hover:not(:disabled) { border-color: var(--cd-brand); color: var(--cd-brand); }
        .cd-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
