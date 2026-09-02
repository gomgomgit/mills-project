<div class="br-page">
    <div class="br-page__header">
        <div>
            <h2 class="br-page__title">Detail Boiler Room</h2>
            <p class="br-page__subtitle">Data record Boiler Room tersimpan (read-only).</p>
        </div>
        <div class="br-page__actions">
            @if ($record)
                <a href="{{ route('data.boiler-room.edit', ['id' => $id]) }}" class="br-button br-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.boiler-room') }}" class="br-button br-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="br-alert br-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="br-detail-section">
            <h4 class="br-detail-section__title">Identitas Boiler Room</h4>
            <div class="br-detail-grid">
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Boiler Room ID</span>
                    <span class="br-detail-field__value" data-testid="detail-boiler-room-id">{{ $record['boiler_room_id'] }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Tanggal</span>
                    <span class="br-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Station</span>
                    <span class="br-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Note</span>
                    <span class="br-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="br-detail-section">
            <h4 class="br-detail-section__title">Boiler Room Detail</h4>
            <div class="br-table-wrap">
                <table class="br-detail-table" data-testid="boiler-room-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Steam Pressure (bar)</th>
                            <th>Steam Temp (&deg;C)</th>
                            <th>Feed Water Temp (&deg;C)</th>
                            <th>Feed Water Tank Level (%)</th>
                            <th>Boiler Water Level (%)</th>
                            <th>Water TDS (ppm)</th>
                            <th>Water pH</th>
                            <th>Fuel Feed Rate</th>
                            <th>ID Fan Load</th>
                            <th>SA Fan Load</th>
                            <th>Exhaust Gas Temp (&deg;C)</th>
                            <th>Dust Collector Differential Pressure (mmH2O)</th>
                            <th>Blowdown Executed</th>
                            <th>Sootblowing Executed</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="boiler-room-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['steam_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['steam_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['feed_water_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['feed_water_tank_level_percent'] ?? '-' }}</td>
                                <td>{{ $row['boiler_water_level_percent'] ?? '-' }}</td>
                                <td>{{ $row['water_tds_ppm'] ?? '-' }}</td>
                                <td>{{ $row['water_ph'] ?? '-' }}</td>
                                <td>{{ $row['fuel_feed_rate'] ?? '-' }}</td>
                                <td>{{ $row['id_fan_load'] ?? '-' }}</td>
                                <td>{{ $row['sa_fan_load'] ?? '-' }}</td>
                                <td>{{ $row['exhaust_gas_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['dust_collector_differential_pressure_mmh2o'] ?? '-' }}</td>
                                <td>{{ $row['blowdown_executed'] ?? '-' }}</td>
                                <td>{{ $row['sootblowing_executed'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="boiler-room-detail-rows-empty">
                                <td colspan="16">Belum ada baris boiler room detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="br-detail-section">
            <h4 class="br-detail-section__title">Verifikasi</h4>
            <div class="br-detail-grid">
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Inputted By</span>
                    <span class="br-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Checked By</span>
                    <span class="br-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Acknowledged By</span>
                    <span class="br-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="br-detail-field">
                    <span class="br-detail-field__label">Status</span>
                    <span class="br-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .br-page { display: flex; flex-direction: column; gap: 20px; }
        .br-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .br-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .br-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .br-page__actions { display: flex; gap: 8px; }
        .br-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .br-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .br-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .br-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .br-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .br-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .br-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .br-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .br-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .br-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .br-table-wrap { width: 100%; overflow-x: auto; }
        .br-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1800px; }
        .br-detail-table th, .br-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
