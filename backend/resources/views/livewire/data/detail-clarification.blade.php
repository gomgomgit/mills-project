<div class="cl-page">
    <div class="cl-page__header">
        <div>
            <h2 class="cl-page__title">Detail Clarification</h2>
            <p class="cl-page__subtitle">Data record Clarification tersimpan (read-only).</p>
        </div>
        <div class="cl-page__actions">
            @if ($record)
                <a href="{{ route('data.clarification.edit', ['id' => $id]) }}" class="cl-button cl-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.clarification') }}" class="cl-button cl-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="cl-alert cl-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="cl-detail-section">
            <h4 class="cl-detail-section__title">Identitas Clarification</h4>
            <div class="cl-detail-grid">
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Clarification ID</span>
                    <span class="cl-detail-field__value" data-testid="detail-clarification-id">{{ $record['clarification_id'] }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Tanggal</span>
                    <span class="cl-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Station</span>
                    <span class="cl-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Note</span>
                    <span class="cl-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="cl-detail-section">
            <h4 class="cl-detail-section__title">Clarification Detail</h4>
            <div class="cl-table-wrap">
                <table class="cl-detail-table" data-testid="clarification-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Clarification Tank Temp (&deg;C)</th>
                            <th>Oil Tank Temperature (&deg;C)</th>
                            <th>Sludge Tank Temp (&deg;C)</th>
                            <th>Buffer Tank Level (%)</th>
                            <th>Pure Oil Production Rate (Ton/Hour)</th>
                            <th>Downtime (Mins)</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="clarification-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['clarification_tank_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['oil_tank_temperature_c'] ?? '-' }}</td>
                                <td>{{ $row['sludge_tank_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['buffer_tank_level_percent'] ?? '-' }}</td>
                                <td>{{ $row['pure_oil_production_rate_ton_hour'] ?? '-' }}</td>
                                <td>{{ $row['downtime_mins'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="clarification-detail-rows-empty">
                                <td colspan="8">Belum ada baris clarification detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cl-detail-section">
            <h4 class="cl-detail-section__title">Verifikasi</h4>
            <div class="cl-detail-grid">
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Inputted By</span>
                    <span class="cl-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Checked By</span>
                    <span class="cl-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Acknowledged By</span>
                    <span class="cl-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="cl-detail-field">
                    <span class="cl-detail-field__label">Status</span>
                    <span class="cl-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .cl-page { display: flex; flex-direction: column; gap: 20px; }
        .cl-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .cl-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .cl-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .cl-page__actions { display: flex; gap: 8px; }
        .cl-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .cl-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .cl-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .cl-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .cl-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .cl-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .cl-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .cl-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .cl-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .cl-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .cl-table-wrap { width: 100%; overflow-x: auto; }
        .cl-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1000px; }
        .cl-detail-table th, .cl-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
