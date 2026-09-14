<div class="st-page">
    <div class="st-page__header">
        <div>
            <h2 class="st-page__title">Detail Storage Tank</h2>
            <p class="st-page__subtitle">Data record Storage Tank tersimpan (read-only).</p>
        </div>
        <div class="st-page__actions">
            @if ($record)
                <a href="{{ route('data.storage-tank.edit', ['id' => $id]) }}" class="st-button st-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.storage-tank') }}" class="st-button st-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="st-alert st-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <x-record-verification-actions
            :can-check="$this->canCheck()"
            :can-acknowledge="$this->canAcknowledge()"
            :is-checked="$this->isChecked()"
            :is-acknowledged="$this->isAcknowledged()"
            :message="$verificationMessage"
        />

        <div class="st-detail-section">
            <h4 class="st-detail-section__title">Identitas Storage Tank</h4>
            <div class="st-detail-grid">
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Storage Tank ID</span>
                    <span class="st-detail-field__value" data-testid="detail-storage-tank-id">{{ $record['storage_tank_id'] }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Tanggal</span>
                    <span class="st-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Station</span>
                    <span class="st-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Note</span>
                    <span class="st-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="st-detail-section">
            <h4 class="st-detail-section__title">Storage Tank Detail</h4>
            <div class="st-table-wrap">
                <table class="st-detail-table" data-testid="storage-tank-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>CPO Sounding Depth (mm)</th>
                            <th>Water Dip/Bottom Depth (mm)</th>
                            <th>Net Oil Depth (mm)</th>
                            <th>Oil Temperature - Top (°C)</th>
                            <th>Oil Temperature - Middle (°C)</th>
                            <th>Oil Temperature - Bottom (°C)</th>
                            <th>Average Temperature (°C)</th>
                            <th>Calculated Volume (m³)</th>
                            <th>Calculated Weight (MT)</th>
                            <th>FFA (%)</th>
                            <th>Moisture Content (%)</th>
                            <th>Impurities/Dirt (%)</th>
                            <th>DOBI Index</th>
                            <th>Steam Heating Valve Status</th>
                            <th>Tank Structural Condition</th>
                            <th>Inspector Name</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="storage-tank-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['cpo_sounding_depth_mm'] ?? '-' }}</td>
                                <td>{{ $row['water_dip_bottom_depth_mm'] ?? '-' }}</td>
                                <td>{{ $row['net_oil_depth_mm'] ?? '-' }}</td>
                                <td>{{ $row['oil_temperature_top_c'] ?? '-' }}</td>
                                <td>{{ $row['oil_temperature_middle_c'] ?? '-' }}</td>
                                <td>{{ $row['oil_temperature_bottom_c'] ?? '-' }}</td>
                                <td>{{ $row['average_temperature_c'] ?? '-' }}</td>
                                <td>{{ $row['calculated_volume_m3'] ?? '-' }}</td>
                                <td>{{ $row['calculated_weight_mt'] ?? '-' }}</td>
                                <td>{{ $row['ffa_percent'] ?? '-' }}</td>
                                <td>{{ $row['moisture_content_percent'] ?? '-' }}</td>
                                <td>{{ $row['impurities_dirt_percent'] ?? '-' }}</td>
                                <td>{{ $row['dobi_index'] ?? '-' }}</td>
                                <td>{{ $row['steam_heating_valve_status'] ?? '-' }}</td>
                                <td>{{ $row['tank_structural_condition'] ?? '-' }}</td>
                                <td>{{ $row['inspector_name'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="storage-tank-detail-rows-empty">
                                <td colspan="18">Belum ada baris storage tank detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="st-detail-section">
            <h4 class="st-detail-section__title">Verifikasi</h4>
            <div class="st-detail-grid">
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Inputted By</span>
                    <span class="st-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Checked By</span>
                    <span class="st-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Acknowledged By</span>
                    <span class="st-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="st-detail-field">
                    <span class="st-detail-field__label">Status</span>
                    <span class="st-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .st-page { display: flex; flex-direction: column; gap: 20px; }
        .st-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .st-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .st-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .st-page__actions { display: flex; gap: 8px; }
        .st-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .st-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .st-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .st-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .st-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .st-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .st-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .st-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .st-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .st-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .st-table-wrap { width: 100%; overflow-x: auto; }
        .st-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1900px; }
        .st-detail-table th, .st-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
