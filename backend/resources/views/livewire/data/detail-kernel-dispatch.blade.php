<div class="kd-page">
    <div class="kd-page__header">
        <div>
            <h2 class="kd-page__title">Detail Kernel Dispatch</h2>
            <p class="kd-page__subtitle">Data record Kernel Dispatch tersimpan (read-only).</p>
        </div>
        <div class="kd-page__actions">
            @if ($record)
                <a href="{{ route('data.kernel-dispatch.edit', ['id' => $id]) }}" class="kd-button kd-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.kernel-dispatch') }}" class="kd-button kd-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="kd-alert kd-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="kd-detail-section">
            <h4 class="kd-detail-section__title">Identitas Kernel Dispatch</h4>
            <div class="kd-detail-grid">
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Kernel Dispatch ID</span>
                    <span class="kd-detail-field__value" data-testid="detail-kernel-dispatch-id">{{ $record['kernel_dispatch_id'] }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Tanggal</span>
                    <span class="kd-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Station</span>
                    <span class="kd-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Note</span>
                    <span class="kd-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="kd-detail-section">
            <h4 class="kd-detail-section__title">Log Kejadian Kernel Dispatch</h4>
            @if (count($record['details']) > 0)
                <div class="kd-table-wrap">
                    <table class="kd-detail-table" data-testid="kernel-dispatch-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Weighbridge Ticket No</th>
                                <th>Waybill Number</th>
                                <th>Transporter/Contractor</th>
                                <th>Vehicle Plate No</th>
                                <th>Driver Name</th>
                                <th>Silo Source ID</th>
                                <th>Destination/Buyer</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>Kernel Moisture (%)</th>
                                <th>Dirt/Impurities (%)</th>
                                <th>FFA (%)</th>
                                <th>Broken Kernel (%)</th>
                                <th>Security Seal No (Top)</th>
                                <th>Security Seal No (Bottom)</th>
                                <th>Weighbridge Operator ID</th>
                                <th>Remarks/Gate Status</th>
                                <th>Findings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($record['details'] as $row)
                                <tr>
                                    <td>{{ $row['event_date'] ? \Illuminate\Support\Carbon::parse($row['event_date'])->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $row['shift'] ?: '-' }}</td>
                                    <td>{{ $row['weighbridge_ticket_no'] ?: '-' }}</td>
                                    <td>{{ $row['waybill_number'] ?: '-' }}</td>
                                    <td>{{ $row['transporter_contractor'] ?: '-' }}</td>
                                    <td>{{ $row['vehicle_plate_no'] ?: '-' }}</td>
                                    <td>{{ $row['driver_name'] ?: '-' }}</td>
                                    <td>{{ $row['silo_source_id'] ?: '-' }}</td>
                                    <td>{{ $row['destination_buyer'] ?: '-' }}</td>
                                    <td>{{ $row['gross_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['tare_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['net_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['kernel_moisture_percent'] ?? '-' }}</td>
                                    <td>{{ $row['dirt_impurities_percent'] ?? '-' }}</td>
                                    <td>{{ $row['ffa_percent'] ?? '-' }}</td>
                                    <td>{{ $row['broken_kernel_percent'] ?? '-' }}</td>
                                    <td>{{ $row['security_seal_no_top'] ?: '-' }}</td>
                                    <td>{{ $row['security_seal_no_bottom'] ?: '-' }}</td>
                                    <td>{{ $row['weighbridge_operator_id'] ?: '-' }}</td>
                                    <td>{{ $row['remarks_gate_status'] ?: '-' }}</td>
                                    <td>{{ $row['findings'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="kd-detail-empty">Belum ada log kejadian Kernel Dispatch.</p>
            @endif
        </div>

        <div class="kd-detail-section">
            <h4 class="kd-detail-section__title">Verifikasi</h4>
            <div class="kd-detail-grid">
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Inputted By</span>
                    <span class="kd-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Checked By</span>
                    <span class="kd-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Acknowledged By</span>
                    <span class="kd-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="kd-detail-field">
                    <span class="kd-detail-field__label">Status</span>
                    <span class="kd-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .kd-page { display: flex; flex-direction: column; gap: 20px; }
        .kd-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .kd-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kd-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .kd-page__actions { display: flex; gap: 8px; }
        .kd-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .kd-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .kd-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .kd-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .kd-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .kd-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .kd-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .kd-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .kd-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .kd-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .kd-table-wrap { width: 100%; overflow-x: auto; }
        .kd-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .kd-detail-table th, .kd-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .kd-detail-empty { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
    </style>
</div>
