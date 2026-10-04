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

    <div class="report-filterbar">
        {{-- PRODUCTION LINE WAJIB DIPILIH (temuan audit 2026-10-04 #2b) —
             sama dengan keenam laporan stasiun. Opsi hanya line di mill
             Anda; tidak ada pilihan "semua line". --}}
        <div class="report-filterbar__field">
            <label for="production_line_id" class="report-filterbar__label">Production Line <small>wajib</small></label>
            <select id="production_line_id" wire:model.live="productionLineId" class="report-filterbar__input" data-testid="production-line-select">
                <option value="">&mdash; Pilih Production Line &mdash;</option>
                @foreach ($productionLineOptions as $option)
                    <option value="{{ $option['id'] }}" @selected($option['id'] === $productionLineId)>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="report-filterbar__field">
            <label for="date_from" class="report-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="report-filterbar__input">
        </div>

        <div class="report-filterbar__field">
            <label for="date_to" class="report-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="report-filterbar__input">
        </div>

        @if ($breakdown !== null)
            <div class="report-filterbar__actions">
                <a href="{{ $this->exportUrl('csv') }}" class="report-export-btn" data-testid="report-export-csv">Ekspor CSV</a>
                <a href="{{ $this->exportUrl('excel') }}" class="report-export-btn" data-testid="report-export-excel">Ekspor Excel</a>
            </div>
        @endif
    </div>

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

    <style>
        .report__header { margin-bottom: 16px; }
        .report__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .report__subtitle { margin: 0; font-size: 14px; color: #6b7280; }
        .report-alert { background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .report-empty { color: #6b7280; font-size: 14px; margin: 0 0 16px; }
        .report-filterbar { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 24px; }
        .report-filterbar__field { display: flex; flex-direction: column; gap: 4px; }
        .report-filterbar__label { font-size: 12px; color: #6b7280; }
        .report-filterbar__input { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 200px; }
        .report-filterbar__actions { display: flex; gap: 8px; }
        .report-export-btn { padding: 8px 14px; background: #249360; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .report-export-btn:hover { background: #1d7a4e; }
        .report-table-wrap { background: #fff; border: 1px solid #d1d5db; border-radius: 12px; overflow-x: auto; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 13px; white-space: nowrap; }
        .report-table th { background: #f9fafb; font-weight: 600; color: #6b7280; }
        .report-table__total { font-weight: 700; background: #f9fafb; }
        /* Judul kolom boleh membungkus supaya 10 kolom muat tanpa terpotong. */
        .report-table thead th { white-space: normal; vertical-align: bottom; }
        .report-table th.num, .report-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .report-filterbar__label small { font-weight: 400; color: #9ca3af; }
        .report-context { margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #374151; }
        .report-note { margin: 8px 0 0; font-size: 12px; color: #6b7280; }
    </style>
</div>
