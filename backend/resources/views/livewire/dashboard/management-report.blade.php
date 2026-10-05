<div class="report">
    <div class="report__header">
        <div>
            <h2 class="report__title">Laporan Manajemen</h2>
            <p class="report__subtitle">Rekap harian Weighbridge, Grading, dan Cages Track per production line di mill Anda</p>
        </div>
    </div>

    @if ($errorMessage)
        <div class="report-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Filter bersama x-filter.bar. PRODUCTION LINE WAJIB DIPILIH
         (temuan audit 2026-10-04 #2b) — sama dengan keenam laporan stasiun.
         Opsi hanya line di mill Anda; tidak ada pilihan "semua line". Mill
         akun tampil sebagai keterangan, bukan pemilih (laporan ini memang
         selalu memakai mill akun — ManagementReport::render()). Tidak ada
         Reset filter: tanggal punya bawaan sendiri dan line wajib. --}}
    <x-filter.bar label="Filter laporan">
        <x-filter.field label="Mill" icon="mill" size="md"
                        :static="auth()->user()?->businessUnit?->name ?? 'Belum terikat mill'"
                        static-testid="mill-current" static-title="Mill mengikuti akun Anda" />

        <x-filter.field label="Production Line" for="production_line_id" icon="line" size="lg" required>
            <select id="production_line_id" wire:model.live="productionLineId" class="fb-control fb-control--select" data-testid="production-line-select">
                <option value="">Pilih Production Line</option>
                @foreach ($productionLineOptions as $option)
                    <option value="{{ $option['id'] }}" @selected($option['id'] === $productionLineId)>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </x-filter.field>

        <x-filter.field label="Tanggal" size="range">
            <x-filter.date-range />
        </x-filter.field>

        @if ($breakdown !== null)
            <x-slot:actions>
                {{-- data-export-link: spinner + "Mengekspor…" sampai respons
                     unduhan tiba (components/loading-assets). --}}
                <a href="{{ $this->exportUrl('csv') }}" class="fb-btn fb-btn--primary" data-testid="report-export-csv" data-export-link>
                    <x-busy-label busy="Mengekspor…"><x-filter.icon name="download" />Ekspor CSV</x-busy-label>
                </a>
                <a href="{{ $this->exportUrl('excel') }}" class="fb-btn" data-testid="report-export-excel" data-export-link>
                    <x-busy-label busy="Mengekspor…"><x-filter.icon name="sheet" />Ekspor Excel</x-busy-label>
                </a>
            </x-slot:actions>
        @endif
    </x-filter.bar>

    {{-- Area hasil: diredupkan + spinner selama line / tanggal berganti. --}}
    <div class="ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="productionLineId,date_from,date_to">
    @if ($productionLineId === '')
        <p class="report-empty" data-testid="select-production-line-hint">
            Pilih production line terlebih dahulu. Laporan ini menghasilkan angka gabungan per line,
            jadi tidak ada angka yang ditampilkan sebelum satu line dipilih.
        </p>
    @elseif ($breakdown !== null)
        @php
            $fmt = fn ($value) => \App\Support\Display::number($value);
            $hasData = collect($breakdown['rows'])->sum(fn ($row) => $row['weighbridge']['receive']['count'] + $row['weighbridge']['dispatch']['count'] + $row['grading']['count'] + $row['cages_track']['count']) > 0;
        @endphp

        <p class="report-context" data-testid="report-context">
            {{ $selectedProductionLine['name'] ?? '' }} &middot;
            {{ \App\Support\Display::date($breakdown['rows'][0]['date'] ?? null) }} &ndash;
            {{ \App\Support\Display::date($breakdown['rows'][count($breakdown['rows']) - 1]['date'] ?? null) }}
        </p>

        @if (! $hasData)
            <p class="report-empty" data-testid="report-empty">Belum ada data untuk rentang tanggal ini.</p>
        @endif

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th rowspan="2">Tanggal</th>
                        <th colspan="2">Weighbridge &mdash; Masuk</th>
                        <th colspan="2">Weighbridge &mdash; Keluar</th>
                        <th colspan="3">Grading</th>
                        <th colspan="2">Cages Track</th>
                    </tr>
                    <tr>
                        <th class="num">Jumlah Trip</th>
                        <th class="num">Berat Bersih (kg)</th>
                        <th class="num">Jumlah Trip</th>
                        <th class="num">Berat Bersih (kg)</th>
                        <th class="num">Jumlah Record</th>
                        <th class="num">Netto (kg)</th>
                        <th class="num">Jumlah Tandan</th>
                        <th class="num">Jumlah Record</th>
                        <th class="num">Lori Ditumpahkan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($breakdown['rows'] as $row)
                        <tr data-testid="report-row-{{ $row['date'] }}">
                            <td>{{ \App\Support\Display::date($row['date'], 'D, d M Y') }}</td>
                            <td class="num">{{ $fmt($row['weighbridge']['receive']['count']) }}</td>
                            <td class="num">{{ $fmt($row['weighbridge']['receive']['total_net_weight']) }}</td>
                            <td class="num">{{ $fmt($row['weighbridge']['dispatch']['count']) }}</td>
                            <td class="num">{{ $fmt($row['weighbridge']['dispatch']['total_net_weight']) }}</td>
                            <td class="num">{{ $fmt($row['grading']['count']) }}</td>
                            <td class="num">{{ $fmt($row['grading']['total_netto']) }}</td>
                            <td class="num">{{ $fmt($row['grading']['total_quantity']) }}</td>
                            <td class="num">{{ $fmt($row['cages_track']['count']) }}</td>
                            <td class="num">{{ $fmt($row['cages_track']['total_cages_tipped']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="report-table__total" data-testid="report-row-total">
                        <td>Total</td>
                        <td class="num">{{ $fmt($breakdown['total']['weighbridge']['receive']['count']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['weighbridge']['receive']['total_net_weight']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['weighbridge']['dispatch']['count']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['weighbridge']['dispatch']['total_net_weight']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['grading']['count']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['grading']['total_netto']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['grading']['total_quantity']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['cages_track']['count']) }}</td>
                        <td class="num">{{ $fmt($breakdown['total']['cages_track']['total_cages_tipped']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="report-note">Arus masuk (TBS diterima) dan arus keluar (pengiriman) Weighbridge ditampilkan terpisah dan tidak pernah dijumlahkan.</p>
    @endif
    </div>

    <style>
        .report__header { margin-bottom: 16px; }
        .report__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .report__subtitle { margin: 0; font-size: 14px; color: #6b7280; }
        .report-alert { background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .report-empty { color: #6b7280; font-size: 14px; margin: 0 0 16px; }
        .report-table-wrap { background: #fff; border: 1px solid #d1d5db; border-radius: 12px; overflow-x: auto; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 13px; white-space: nowrap; }
        .report-table th { background: #f9fafb; font-weight: 600; color: #6b7280; }
        .report-table__total { font-weight: 700; background: #f9fafb; }
        /* Judul kolom boleh membungkus supaya 10 kolom muat tanpa terpotong. */
        .report-table thead th { white-space: normal; vertical-align: bottom; }
        .report-table th.num, .report-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .report-context { margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #374151; }
        .report-note { margin: 8px 0 0; font-size: 12px; color: #6b7280; }
    </style>
</div>
