<div class="ep-page">
    <div class="ep-page__header">
        <div>
            <h2 class="ep-page__title">Detail Effluent Plant</h2>
            <p class="ep-page__subtitle">Data record Effluent Plant tersimpan (read-only).</p>
        </div>
        <div class="ep-page__actions">
            @if ($record)
                <a href="{{ route('data.effluent-plant.edit', ['id' => $id]) }}" class="ep-button ep-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.effluent-plant') }}" class="ep-button ep-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="ep-alert ep-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="ep-detail-section">
            <h4 class="ep-detail-section__title">Identitas Effluent Plant</h4>
            <div class="ep-detail-grid">
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Effluent Plant ID</span>
                    <span class="ep-detail-field__value" data-testid="detail-effluent-plant-id">{{ $record['effluent_plant_id'] }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Tanggal</span>
                    <span class="ep-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Station</span>
                    <span class="ep-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Note</span>
                    <span class="ep-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="ep-detail-section">
            <h4 class="ep-detail-section__title">Effluent Plant Detail</h4>
            <div class="ep-table-wrap">
                <table class="ep-detail-table" data-testid="effluent-plant-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Anaerobic Pond 1 pH</th>
                            <th>Anaerobic Pond 1 Temp</th>
                            <th>Anaerobic Pond 2 pH</th>
                            <th>Anaerobic Pond 2 Temp</th>
                            <th>Cooling Pond pH</th>
                            <th>Cooling Pond Temp</th>
                            <th>Biogas Flare Status</th>
                            <th>Biogas Flow Rate</th>
                            <th>Raw POME Feed Rate</th>
                            <th>Effluent Discharge Flow Rate</th>
                            <th>Final Discharge pH</th>
                            <th>Final Discharge BOD</th>
                            <th>Final Discharge COD</th>
                            <th>Final Discharge TSS</th>
                            <th>Dosing Pump 1 Status</th>
                            <th>Chemical Consumed</th>
                            <th>Sludge Dewatering Status</th>
                            <th>Remarks/Maintenance Actions</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="effluent-plant-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['anaerobic_pond_1_ph'] ?? '-' }}</td>
                                <td>{{ $row['anaerobic_pond_1_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['anaerobic_pond_2_ph'] ?? '-' }}</td>
                                <td>{{ $row['anaerobic_pond_2_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['cooling_pond_ph'] ?? '-' }}</td>
                                <td>{{ $row['cooling_pond_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['biogas_flare_status'] ?? '-' }}</td>
                                <td>{{ $row['biogas_flow_rate_m3h'] ?? '-' }}</td>
                                <td>{{ $row['raw_pome_feed_rate_m3h'] ?? '-' }}</td>
                                <td>{{ $row['effluent_discharge_flow_rate_m3h'] ?? '-' }}</td>
                                <td>{{ $row['final_discharge_ph'] ?? '-' }}</td>
                                <td>{{ $row['final_discharge_bod_mgl_lab'] ?? '-' }}</td>
                                <td>{{ $row['final_discharge_cod_mgl_lab'] ?? '-' }}</td>
                                <td>{{ $row['final_discharge_tss_mgl_lab'] ?? '-' }}</td>
                                <td>{{ $row['dosing_pump_1_status'] ?? '-' }}</td>
                                <td>{{ $row['chemical_consumed_kgl'] ?? '-' }}</td>
                                <td>{{ $row['sludge_dewatering_status'] ?? '-' }}</td>
                                <td>{{ $row['remarks_maintenance_actions'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="effluent-plant-detail-rows-empty">
                                <td colspan="20">Belum ada baris effluent plant detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ep-detail-section">
            <h4 class="ep-detail-section__title">Verifikasi</h4>
            <div class="ep-detail-grid">
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Inputted By</span>
                    <span class="ep-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Checked By</span>
                    <span class="ep-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Acknowledged By</span>
                    <span class="ep-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="ep-detail-field">
                    <span class="ep-detail-field__label">Status</span>
                    <span class="ep-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ep-page { display: flex; flex-direction: column; gap: 20px; }
        .ep-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .ep-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .ep-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .ep-page__actions { display: flex; gap: 8px; }
        .ep-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .ep-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .ep-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .ep-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .ep-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .ep-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .ep-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .ep-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .ep-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .ep-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .ep-table-wrap { width: 100%; overflow-x: auto; }
        .ep-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1900px; }
        .ep-detail-table th, .ep-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
