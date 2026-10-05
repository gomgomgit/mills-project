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
            <a href="{{ $exportCsvUrl }}" class="pr-button pr-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor CSV</x-busy-label>
            </a>
            <a href="{{ $exportExcelUrl }}" class="pr-button pr-button--secondary" target="_blank" rel="noopener" data-export-link>
                <x-busy-label busy="Mengekspor…">Ekspor Excel</x-busy-label>
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="pr-alert" role="alert">
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

    <div class="pr-table-wrap ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="date_from,date_to,business_unit_id,production_line_id,resetFilters,previousPage,nextPage,goToPage">
        <table class="pr-table">
            <thead class="pr-table__head">
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
                        {{-- Diambil dari KOLOM RECORD (production_line_name), bukan
                             dari station->productionLine: untuk record lama yang
                             stasiunnya sudah dipindah, keduanya berbeda — dan yang
                             benar adalah line tempat data itu benar-benar dihasilkan. --}}
                        <td>{{ $record['production_line_name'] ?? '-' }}</td>
                        <td>{{ $record['presser_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['filled_slot_count'] }} / 24</td>
                        <td>
                            <span class="pr-badge pr-badge--{{ $record['status'] }}">{{ \App\Support\Display::status($record['status']) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="pr-table__row pr-table__row--static">
                        <td colspan="5">
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
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
                    class="pr-button pr-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    wire:target="previousPage,nextPage,goToPage"
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

        .pr-browser__header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .pr-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pr-browser__subtitle { margin: 0; font-size: 14px; color: var(--pr-text-muted); }
        /* flex-shrink 1 + min-width 0 (2026-10-05): di ponsel tombol boleh turun baris di dalam
           wadah ini — teks sibuk "Mengekspor…" lebih lebar dari "Ekspor CSV" dan dulu terpotong. */
        .pr-browser__export { display: flex; flex-wrap: wrap; gap: 8px; flex-shrink: 1; min-width: 0; }

        .pr-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--pr-radius-input); background: #fef2f2; border: 1px solid var(--pr-destructive); color: var(--pr-destructive); font-size: 14px; }


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
