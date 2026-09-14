<div class="sw-page">
    <div class="sw-page__header">
        <div>
            <h2 class="sw-page__title">Detail Solid Waste Disposal</h2>
            <p class="sw-page__subtitle">Data record Solid Waste Disposal tersimpan (read-only).</p>
        </div>
        <div class="sw-page__actions">
            @if ($record)
                <a href="{{ route('data.solid-waste-disposal.edit', ['id' => $id]) }}" class="sw-button sw-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.solid-waste-disposal') }}" class="sw-button sw-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="sw-alert sw-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="sw-detail-section">
            <h4 class="sw-detail-section__title">Identitas Solid Waste Disposal</h4>
            <div class="sw-detail-grid">
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Solid Waste Disp. ID</span>
                    <span class="sw-detail-field__value" data-testid="detail-solid-waste-disposal-id">{{ $record['solid_waste_disposal_id'] }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Tanggal</span>
                    <span class="sw-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Station</span>
                    <span class="sw-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Note</span>
                    <span class="sw-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="sw-detail-section">
            <h4 class="sw-detail-section__title">Log Kejadian Solid Waste Disposal</h4>
            @if (count($record['details']) > 0)
                <div class="sw-table-wrap">
                    <table class="sw-detail-table" data-testid="solid-waste-disposal-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Weighbridge Ticket No</th>
                                <th>Vehicle No</th>
                                <th>Driver Name</th>
                                <th>Solid Waste Type</th>
                                <th>Source Station</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>Disposal/Utilization Site</th>
                                <th>Purpose/End Use</th>
                                <th>Gate Pass No</th>
                                <th>Security Seal No</th>
                                <th>Operator ID</th>
                                <th>Remarks</th>
                                <th>Findings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($record['details'] as $row)
                                <tr>
                                    <td>{{ $row['event_date'] ? \Illuminate\Support\Carbon::parse($row['event_date'])->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $row['shift'] ?: '-' }}</td>
                                    <td>{{ $row['weighbridge_ticket_no'] ?: '-' }}</td>
                                    <td>{{ $row['vehicle_no'] ?: '-' }}</td>
                                    <td>{{ $row['driver_name'] ?: '-' }}</td>
                                    <td>{{ $row['solid_waste_type'] ?: '-' }}</td>
                                    <td>{{ $row['source_station'] ?: '-' }}</td>
                                    <td>{{ $row['gross_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['tare_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['net_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['disposal_utilization_site'] ?: '-' }}</td>
                                    <td>{{ $row['purpose_end_use'] ?: '-' }}</td>
                                    <td>{{ $row['gate_pass_no'] ?: '-' }}</td>
                                    <td>{{ $row['security_seal_no'] ?: '-' }}</td>
                                    <td>{{ $row['operator_id'] ?: '-' }}</td>
                                    <td>{{ $row['remarks'] ?: '-' }}</td>
                                    <td>{{ $row['findings'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="sw-detail-empty">Belum ada log kejadian Solid Waste Disposal.</p>
            @endif
        </div>

        <div class="sw-detail-section">
            <h4 class="sw-detail-section__title">Verifikasi</h4>
            <div class="sw-detail-grid">
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Inputted By</span>
                    <span class="sw-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Checked By</span>
                    <span class="sw-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Acknowledged By</span>
                    <span class="sw-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="sw-detail-field">
                    <span class="sw-detail-field__label">Status</span>
                    <span class="sw-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .sw-page { display: flex; flex-direction: column; gap: 20px; }
        .sw-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .sw-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .sw-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .sw-page__actions { display: flex; gap: 8px; }
        .sw-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .sw-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .sw-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .sw-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .sw-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .sw-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .sw-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .sw-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .sw-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .sw-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .sw-table-wrap { width: 100%; overflow-x: auto; }
        .sw-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sw-detail-table th, .sw-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .sw-detail-empty { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
    </style>
</div>
