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
            <a href="{{ $exportCsvUrl }}" class="dp-button dp-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor CSV</x-busy-label>
            </a>
            <a href="{{ $exportExcelUrl }}" class="dp-button dp-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor Excel</x-busy-label>
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="dp-alert" role="alert">
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

    <div class="dp-table-wrap ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="date_from,date_to,business_unit_id,production_line_id,resetFilters,previousPage,nextPage,goToPage">
        <table class="dp-table">
            <thead class="dp-table__head">
                <tr>
                    <th>Production Line</th>
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
                        {{-- Diambil dari KOLOM RECORD (production_line_name), bukan
                             dari station->productionLine: untuk record lama yang
                             stasiunnya sudah dipindah, keduanya berbeda — dan yang
                             benar adalah line tempat data itu benar-benar dihasilkan. --}}
                        <td>{{ $record['production_line_name'] ?? '-' }}</td>
                        <td>{{ $record['presser_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="dp-badge dp-badge--{{ $record['status'] }}">{{ \App\Support\Display::status($record['status']) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="dp-table__row dp-table__row--static">
                        <td colspan="5">
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
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
                    class="dp-button dp-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
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

        .dp-browser__header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .dp-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .dp-browser__subtitle { margin: 0; font-size: 14px; color: var(--dp-text-muted); }
        /* flex-shrink 1 + min-width 0 (2026-10-05): di ponsel tombol boleh turun baris di dalam
           wadah ini — teks sibuk "Mengekspor…" lebih lebar dari "Ekspor CSV" dan dulu terpotong. */
        .dp-browser__export { display: flex; flex-wrap: wrap; gap: 8px; flex-shrink: 1; min-width: 0; }

        .dp-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--dp-radius-input); background: #fef2f2; border: 1px solid var(--dp-destructive); color: var(--dp-destructive); font-size: 14px; }


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
