<div class="er-page">
    <div class="er-page__header">
        <div>
            <h2 class="er-page__title">Detail Engine Room</h2>
            <p class="er-page__subtitle">Data record Engine Room tersimpan (read-only).</p>
        </div>
        <div class="er-page__actions">
            @if ($record)
                <a href="{{ route('data.engine-room.edit', ['id' => $id]) }}" class="er-button er-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.engine-room') }}" class="er-button er-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="er-alert er-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="er-detail-section">
            <h4 class="er-detail-section__title">Identitas Engine Room</h4>
            <div class="er-detail-grid">
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Engine Room ID</span>
                    <span class="er-detail-field__value" data-testid="detail-engine-room-id">{{ $record['engine_room_id'] }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Tanggal</span>
                    <span class="er-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Station</span>
                    <span class="er-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Note</span>
                    <span class="er-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="er-detail-section">
            <h4 class="er-detail-section__title">Engine Room Detail</h4>
            <div class="er-table-wrap">
                <table class="er-detail-table" data-testid="engine-room-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Steam Turbine Inlet Pressure (bar)</th>
                            <th>Steam Turbine Inlet Temp (°C)</th>
                            <th>Steam Turbine Exhaust Pressure (bar)</th>
                            <th>Steam Turbine RPM</th>
                            <th>Steam Turbine Alternator Bearing Temp 1 (°C)</th>
                            <th>Steam Turbine Alternator Bearing Temp 2 (°C)</th>
                            <th>Diesel Gen 1 Status</th>
                            <th>Diesel Gen 1 Load (kW)</th>
                            <th>Diesel Gen 1 Amperage (A)</th>
                            <th>Diesel Gen 1 Jacket Water Temp (°C)</th>
                            <th>Diesel Gen 1 Lube Oil Pressure (bar)</th>
                            <th>Diesel Gen 2 Status</th>
                            <th>Diesel Gen 2 Load (kW)</th>
                            <th>Diesel Gen 2 Amperage (A)</th>
                            <th>Diesel Gen 2 Jacket Water Temp (°C)</th>
                            <th>Diesel Gen 2 Lube Oil Pressure (bar)</th>
                            <th>Electrical Sync Total Factory Load (kW)</th>
                            <th>Electrical Sync System Frequency (Hz)</th>
                            <th>Electrical Sync Power Factor (PF)</th>
                            <th>Electrical Sync Busbar Voltage (V)</th>
                            <th>Air Compressor 1 Pressure (bar)</th>
                            <th>Compressor 2 Pressure (bar)</th>
                            <th>Battery Charger/UPS Voltage (V)</th>
                            <th>Fuel Tank Level (Liters/%)</th>
                            <th>Daily Energy Export (kWh)</th>
                            <th>Action Taken/Maintenance Remark</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="engine-room-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['steam_turbine_inlet_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['steam_turbine_inlet_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['steam_turbine_exhaust_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['steam_turbine_rpm'] ?? '-' }}</td>
                                <td>{{ $row['steam_turbine_alternator_bearing_temp_1_c'] ?? '-' }}</td>
                                <td>{{ $row['steam_turbine_alternator_bearing_temp_2_c'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_1_status'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_1_load_kw'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_1_amperage_a'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_1_jacket_water_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_1_lube_oil_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_2_status'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_2_load_kw'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_2_amperage_a'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_2_jacket_water_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['diesel_gen_2_lube_oil_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['electrical_sync_total_factory_load_kw'] ?? '-' }}</td>
                                <td>{{ $row['electrical_sync_system_frequency_hz'] ?? '-' }}</td>
                                <td>{{ $row['electrical_sync_power_factor'] ?? '-' }}</td>
                                <td>{{ $row['electrical_sync_busbar_voltage_v'] ?? '-' }}</td>
                                <td>{{ $row['air_compressor_1_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['compressor_2_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['battery_charger_ups_voltage_v'] ?? '-' }}</td>
                                <td>{{ $row['fuel_tank_level'] ?? '-' }}</td>
                                <td>{{ $row['daily_energy_export_kwh'] ?? '-' }}</td>
                                <td>{{ $row['action_taken_maintenance_remark'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="engine-room-detail-rows-empty">
                                <td colspan="28">Belum ada baris engine room detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="er-detail-section">
            <h4 class="er-detail-section__title">Verifikasi</h4>
            <div class="er-detail-grid">
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Inputted By</span>
                    <span class="er-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Checked By</span>
                    <span class="er-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Acknowledged By</span>
                    <span class="er-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="er-detail-field">
                    <span class="er-detail-field__label">Status</span>
                    <span class="er-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .er-page { display: flex; flex-direction: column; gap: 20px; }
        .er-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .er-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .er-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .er-page__actions { display: flex; gap: 8px; }
        .er-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .er-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .er-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .er-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .er-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .er-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .er-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .er-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .er-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .er-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .er-table-wrap { width: 100%; overflow-x: auto; }
        .er-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 2800px; }
        .er-detail-table th, .er-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
