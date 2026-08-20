<div class="report">
    <div class="report__header">
        <div>
            <h2 class="report__title">Laporan Manajemen</h2>
            <p class="report__subtitle">Breakdown harian Weighbridge, Grading, dan Cages Track untuk mill Anda</p>
        </div>
    </div>

    @if ($errorMessage)
        <div class="report-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="report-filterbar">
        <div class="report-filterbar__field">
            <label for="date_from" class="report-filterbar__label">Tanggal Dari</label>
            <input type="date" id="date_from" wire:model.live="date_from" class="report-filterbar__input">
        </div>

        <div class="report-filterbar__field">
            <label for="date_to" class="report-filterbar__label">Tanggal Sampai</label>
            <input type="date" id="date_to" wire:model.live="date_to" class="report-filterbar__input">
        </div>

        <div class="report-filterbar__actions">
            <a href="{{ $this->exportUrl('csv') }}" class="report-export-btn" data-testid="report-export-csv">Ekspor CSV</a>
            <a href="{{ $this->exportUrl('excel') }}" class="report-export-btn" data-testid="report-export-excel">Ekspor Excel</a>
        </div>
    </div>

    @if (count($breakdown['rows']) === 0 || collect($breakdown['rows'])->sum(fn ($row) => $row['weighbridge']['count'] + $row['grading']['count'] + $row['cages_track']['count']) === 0)
        <p class="report-empty" data-testid="report-empty">Belum ada data untuk rentang tanggal ini.</p>
    @endif

    <div class="report-table-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th rowspan="2">Tanggal</th>
                    <th colspan="2">Weighbridge</th>
                    <th colspan="3">Grading</th>
                    <th colspan="2">Cages Track</th>
                </tr>
                <tr>
                    <th>Count</th>
                    <th>Net Weight (kg)</th>
                    <th>Count</th>
                    <th>Netto (kg)</th>
                    <th>Quantity (tandan)</th>
                    <th>Count</th>
                    <th>Cages Tipped</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown['rows'] as $row)
                    <tr data-testid="report-row-{{ $row['date'] }}">
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['weighbridge']['count'] }}</td>
                        <td>{{ number_format($row['weighbridge']['total_net_weight'], 2) }}</td>
                        <td>{{ $row['grading']['count'] }}</td>
                        <td>{{ number_format($row['grading']['total_netto'], 2) }}</td>
                        <td>{{ number_format($row['grading']['total_quantity'], 2) }}</td>
                        <td>{{ $row['cages_track']['count'] }}</td>
                        <td>{{ $row['cages_track']['total_cages_tipped'] }}</td>
                    </tr>
                @endforeach
                <tr class="report-table__total" data-testid="report-row-total">
                    <td>Total</td>
                    <td>{{ $breakdown['total']['weighbridge']['count'] }}</td>
                    <td>{{ number_format($breakdown['total']['weighbridge']['total_net_weight'], 2) }}</td>
                    <td>{{ $breakdown['total']['grading']['count'] }}</td>
                    <td>{{ number_format($breakdown['total']['grading']['total_netto'], 2) }}</td>
                    <td>{{ number_format($breakdown['total']['grading']['total_quantity'], 2) }}</td>
                    <td>{{ $breakdown['total']['cages_track']['count'] }}</td>
                    <td>{{ $breakdown['total']['cages_track']['total_cages_tipped'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>

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
    </style>
</div>
