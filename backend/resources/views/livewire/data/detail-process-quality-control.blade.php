<div class="pqc-page">
    <div class="pqc-page__header">
        <div>
            <h2 class="pqc-page__title">Detail Process Quality Control</h2>
            <p class="pqc-page__subtitle">Data record Process Quality Control tersimpan (read-only).</p>
        </div>
        <div class="pqc-page__actions">
            @if ($record)
                <a href="{{ route('data.process-quality-control.edit', ['id' => $id]) }}" class="pqc-button pqc-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.process-quality-control') }}" class="pqc-button pqc-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="pqc-alert pqc-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="pqc-detail-section">
            <h4 class="pqc-detail-section__title">Identitas Process Quality Control</h4>
            <div class="pqc-detail-grid">
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Process QC ID</span>
                    <span class="pqc-detail-field__value" data-testid="detail-process-qc-id">{{ $record['process_qc_id'] }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Tanggal</span>
                    <span class="pqc-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Station</span>
                    <span class="pqc-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Note</span>
                    <span class="pqc-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="pqc-detail-section">
            <h4 class="pqc-detail-section__title">Process Quality Control Detail</h4>
            <div class="pqc-table-wrap">
                <table class="pqc-detail-table" data-testid="process-quality-control-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Shift</th>
                            <th>Fruit Press Oil Loss in Sludge (%)</th>
                            <th>Fruit Press Oil Loss in Fibre (%)</th>
                            <th>Purifier &amp; Clarification Balance Inlet Temp (&deg;C)</th>
                            <th>Purifier &amp; Clarification Balance Backpressure (Bar)</th>
                            <th>Vacuum Drying Station Drier Temp (&deg;C)</th>
                            <th>Vacuum Drying Station Vacuum Pressure (Bar)</th>
                            <th>Decanter/Centrifuge Feed Rate (MT/h)</th>
                            <th>Decanter/Centrifuge Oil Loss in Cake (%)</th>
                            <th>Final Storage FFA (%)</th>
                            <th>Final Storage Moisture Content (%)</th>
                            <th>Final Storage Impurities/Dirt (%)</th>
                            <th>Final Storage DOBI Index</th>
                            <th>QC Inspector ID</th>
                            <th>QC Engineering Corrective Actions/Remarks</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="process-quality-control-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['shift'] ?? '-' }}</td>
                                <td>{{ $row['fruit_press_oil_loss_in_sludge_percent'] ?? '-' }}</td>
                                <td>{{ $row['fruit_press_oil_loss_in_fibre_percent'] ?? '-' }}</td>
                                <td>{{ $row['purifier_clarification_balance_inlet_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['purifier_clarification_balance_backpressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['vacuum_drying_station_drier_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['vacuum_drying_station_vacuum_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['decanter_centrifuge_feed_rate_mth'] ?? '-' }}</td>
                                <td>{{ $row['decanter_centrifuge_oil_loss_in_cake_percent'] ?? '-' }}</td>
                                <td>{{ $row['final_storage_ffa_percent'] ?? '-' }}</td>
                                <td>{{ $row['final_storage_moisture_content_percent'] ?? '-' }}</td>
                                <td>{{ $row['final_storage_impurities_dirt_percent'] ?? '-' }}</td>
                                <td>{{ $row['final_storage_dobi_index'] ?? '-' }}</td>
                                <td>{{ $row['qc_inspector_id'] ?? '-' }}</td>
                                <td>{{ $row['qc_engineering_corrective_actions'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="process-quality-control-detail-rows-empty">
                                <td colspan="17">Belum ada baris process quality control detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pqc-detail-section">
            <h4 class="pqc-detail-section__title">Verifikasi</h4>
            <div class="pqc-detail-grid">
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Inputted By</span>
                    <span class="pqc-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Checked By</span>
                    <span class="pqc-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Acknowledged By</span>
                    <span class="pqc-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="pqc-detail-field">
                    <span class="pqc-detail-field__label">Status</span>
                    <span class="pqc-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .pqc-page { display: flex; flex-direction: column; gap: 20px; }
        .pqc-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .pqc-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pqc-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .pqc-page__actions { display: flex; gap: 8px; }
        .pqc-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .pqc-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .pqc-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .pqc-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .pqc-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .pqc-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .pqc-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .pqc-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .pqc-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .pqc-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .pqc-table-wrap { width: 100%; overflow-x: auto; }
        .pqc-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 2200px; }
        .pqc-detail-table th, .pqc-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
