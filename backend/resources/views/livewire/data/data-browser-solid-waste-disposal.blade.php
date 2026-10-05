<div class="sw-browser" wire:loading.class="sw-browser--busy" wire:target="nextPage,previousPage,goToPage">
    <div class="sw-browser__header">
        <div>
            <h2 class="sw-browser__title">Data Browser Solid Waste Disposal</h2>
            <p class="sw-browser__subtitle">Riwayat data log sheet stasiun Solid Waste Disposal</p>
        </div>

        <div class="sw-browser__export">
            <a href="{{ route('data.solid-waste-disposal.create') }}" class="sw-button sw-button--secondary" data-testid="add-data-button">
                Tambah Data
            </a>
            <a href="{{ $exportCsvUrl }}" class="sw-button sw-button--secondary" target="_blank" rel="noopener">
                Ekspor CSV
            </a>
            <a href="{{ $exportExcelUrl }}" class="sw-button sw-button--secondary" target="_blank" rel="noopener">
                Ekspor Excel
            </a>
        </div>
    </div>

    @if ($errorMessage)
        <div class="sw-alert" role="alert">
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

    <div class="sw-table-wrap">
        <table class="sw-table">
            <thead class="sw-table__head">
                <tr>
                    <th>Production Line</th>
                    <th>Solid Waste Disp. ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah Kejadian</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $hasDetailRoute = \Illuminate\Support\Facades\Route::has('data.solid-waste-disposal.detail');
                        $detailHref = $hasDetailRoute
                            ? route('data.solid-waste-disposal.detail', ['id' => $record['id']])
                            : '#';
                    @endphp
                    <tr
                        class="sw-table__row @if(!$hasDetailRoute) sw-table__row--static @endif"
                        wire:key="sw-record-{{ $record['id'] }}"
                        @if ($hasDetailRoute)
                            onclick="window.location.href='{{ $detailHref }}'"
                        @endif
                    >
                        {{-- Diambil dari KOLOM RECORD (production_line_name), bukan
                             dari station->productionLine: untuk record lama yang
                             stasiunnya sudah dipindah, keduanya berbeda — dan yang
                             benar adalah line tempat data itu benar-benar dihasilkan. --}}
                        <td>{{ $record['production_line_name'] ?? '-' }}</td>
                        <td>{{ $record['solid_waste_disposal_id'] }}</td>
                        <td>{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $record['event_count'] }}</td>
                        <td>
                            <span class="sw-badge sw-badge--{{ $record['status'] }}">{{ \App\Support\Display::status($record['status']) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr class="sw-table__row sw-table__row--static">
                        <td colspan="5">
                            <div class="sw-empty">
                                <div class="sw-empty__illustration" aria-hidden="true">&#128203;</div>
                                <p class="sw-empty__title">Tidak ada data</p>
                                <p class="sw-empty__subtitle">Tidak ada data solid waste disposal yang cocok dengan filter saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="sw-pagination">
            <span class="sw-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="sw-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="sw-button sw-button--ghost"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="sw-button sw-button--ghost"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    <style>
        .sw-browser {
            --sw-brand: #249360;
            --sw-brand-hover: #1d7a4e;
            --sw-destructive: #DC2626;
            --sw-text: #1f2937;
            --sw-text-muted: #6b7280;
            --sw-border: #d1d5db;
            --sw-radius-input: 6px;
            --sw-radius-button: 8px;
            color: var(--sw-text);
        }

        .sw-browser--busy { opacity: 0.85; }

        .sw-browser__header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .sw-browser__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .sw-browser__subtitle { margin: 0; font-size: 14px; color: var(--sw-text-muted); }
        .sw-browser__export { display: flex; flex-wrap: wrap; gap: 8px; flex-shrink: 0; }

        .sw-alert { margin-bottom: 16px; padding: 10px 12px; border-radius: var(--sw-radius-input); background: #fef2f2; border: 1px solid var(--sw-destructive); color: var(--sw-destructive); font-size: 14px; }


        .sw-table-wrap { width: 100%; overflow-x: auto; border: 1px solid var(--sw-border); border-radius: 10px; background: #fff; }
        .sw-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .sw-table__head th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; padding: 12px 16px; font-weight: 600; color: var(--sw-text-muted); border-bottom: 1px solid var(--sw-border); white-space: nowrap; }
        .sw-table__row td { padding: 12px 16px; border-bottom: 1px solid var(--sw-border); white-space: nowrap; }
        .sw-table__row:last-child td { border-bottom: none; }
        .sw-table__row:not(.sw-table__row--static) { cursor: pointer; }
        .sw-table__row:not(.sw-table__row--static):hover { background: #f0fdf4; }

        .sw-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: #f3f4f6; color: var(--sw-text-muted); }
        .sw-badge--saved, .sw-badge--synced { background: #f0fdf4; color: #16a34a; }
        .sw-badge--draft_ongoing, .sw-badge--draft_paused { background: #fffbeb; color: #b45309; }

        .sw-empty { padding: 48px 16px; text-align: center; }
        .sw-empty__illustration { font-size: 40px; margin-bottom: 12px; }
        .sw-empty__title { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: var(--sw-text); }
        .sw-empty__subtitle { margin: 0; font-size: 13px; color: var(--sw-text-muted); }

        .sw-pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 16px; font-size: 13px; color: var(--sw-text-muted); }
        .sw-pagination__controls { display: flex; gap: 8px; }

        .sw-button { padding: 8px 14px; font-size: 14px; font-weight: 600; font-family: inherit; border-radius: var(--sw-radius-button); border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .sw-button--secondary { background: var(--sw-brand); color: #fff; }
        .sw-button--secondary:hover { background: var(--sw-brand-hover); }
        .sw-button--ghost { background: #fff; color: var(--sw-text); border: 1px solid var(--sw-border); }
        .sw-button--ghost:hover:not(:disabled) { border-color: var(--sw-brand); color: var(--sw-brand); }
        .sw-button--ghost:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</div>
