<div class="cd-page">
    <div class="cd-page__header">
        <div>
            <h2 class="cd-page__title">Detail CPO Dispatch</h2>
            <p class="cd-page__subtitle">Data record CPO Dispatch tersimpan (read-only).</p>
        </div>
        <div class="cd-page__actions">
            @if ($record)
                <a href="{{ route('data.cpo-dispatch.edit', ['id' => $id]) }}" class="cd-button cd-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.cpo-dispatch') }}" class="cd-button cd-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="cd-alert cd-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="cd-detail-section">
            <h4 class="cd-detail-section__title">Identitas CPO Dispatch</h4>
            <div class="cd-detail-grid">
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">CPO Dispatch ID</span>
                    <span class="cd-detail-field__value" data-testid="detail-cpo-dispatch-id">{{ $record['cpo_dispatch_id'] }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Tanggal</span>
                    <span class="cd-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Station</span>
                    <span class="cd-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Note</span>
                    <span class="cd-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="cd-detail-section">
            <h4 class="cd-detail-section__title">Log Kejadian CPO Dispatch</h4>
            @if (count($record['details']) > 0)
                <div class="cd-table-wrap">
                    <table class="cd-detail-table" data-testid="cpo-dispatch-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Waybill Number</th>
                                <th>Tanker Plate No</th>
                                <th>Transport Company</th>
                                <th>Driver Name</th>
                                <th>Storage Tank Source</th>
                                <th>Seal No (Top)</th>
                                <th>Seal No (Bottom)</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>FFA (%)</th>
                                <th>Moisture (%)</th>
                                <th>Impurities (%)</th>
                                <th>DOBI</th>
                                <th>Destination/Buyer</th>
                                <th>Weighbridge Operator</th>
                                <th>Findings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($record['details'] as $row)
                                <tr>
                                    <td>{{ $row['event_date'] ? \Illuminate\Support\Carbon::parse($row['event_date'])->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $row['shift'] ?: '-' }}</td>
                                    <td>{{ $row['time_in'] ?: '-' }}</td>
                                    <td>{{ $row['time_out'] ?: '-' }}</td>
                                    <td>{{ $row['waybill_number'] ?: '-' }}</td>
                                    <td>{{ $row['tanker_plate_no'] ?: '-' }}</td>
                                    <td>{{ $row['transport_company'] ?: '-' }}</td>
                                    <td>{{ $row['driver_name'] ?: '-' }}</td>
                                    <td>{{ $row['storage_tank_source'] ?: '-' }}</td>
                                    <td>{{ $row['seal_no_top'] ?: '-' }}</td>
                                    <td>{{ $row['seal_no_bottom'] ?: '-' }}</td>
                                    <td>{{ $row['gross_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['tare_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['net_weight_mt'] ?? '-' }}</td>
                                    <td>{{ $row['ffa_percent'] ?? '-' }}</td>
                                    <td>{{ $row['moisture_percent'] ?? '-' }}</td>
                                    <td>{{ $row['impurities_percent'] ?? '-' }}</td>
                                    <td>{{ $row['dobi'] ?? '-' }}</td>
                                    <td>{{ $row['destination_buyer'] ?: '-' }}</td>
                                    <td>{{ $row['weighbridge_operator'] ?: '-' }}</td>
                                    <td>{{ $row['findings'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="cd-detail-empty">Belum ada log kejadian CPO Dispatch.</p>
            @endif
        </div>

        <div class="cd-detail-section">
            <h4 class="cd-detail-section__title">Verifikasi</h4>
            <div class="cd-detail-grid">
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Inputted By</span>
                    <span class="cd-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Checked By</span>
                    <span class="cd-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Acknowledged By</span>
                    <span class="cd-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="cd-detail-field">
                    <span class="cd-detail-field__label">Status</span>
                    <span class="cd-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .cd-page { display: flex; flex-direction: column; gap: 20px; }
        .cd-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .cd-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .cd-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .cd-page__actions { display: flex; gap: 8px; }
        .cd-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .cd-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .cd-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .cd-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .cd-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .cd-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .cd-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .cd-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .cd-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .cd-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .cd-table-wrap { width: 100%; overflow-x: auto; }
        .cd-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cd-detail-table th, .cd-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .cd-detail-empty { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
    </style>
</div>
