<div class="pw-page">
    <div class="pw-page__header">
        <div>
            <h2 class="pw-page__title">Detail Process Water</h2>
            <p class="pw-page__subtitle">Data record Process Water tersimpan (read-only).</p>
        </div>
        <div class="pw-page__actions">
            @if ($record)
                <a href="{{ route('data.process-water.edit', ['id' => $id]) }}" class="pw-button pw-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.process-water') }}" class="pw-button pw-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="pw-alert pw-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="pw-detail-section">
            <h4 class="pw-detail-section__title">Identitas Process Water</h4>
            <div class="pw-detail-grid">
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Process Water ID</span>
                    <span class="pw-detail-field__value" data-testid="detail-process-water-id">{{ $record['process_water_id'] }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Tanggal</span>
                    <span class="pw-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Station</span>
                    <span class="pw-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Note</span>
                    <span class="pw-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="pw-detail-section">
            <h4 class="pw-detail-section__title">Process Water Detail</h4>
            <div class="pw-table-wrap">
                <table class="pw-detail-table" data-testid="process-water-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Shift</th>
                            <th>Inspector ID</th>
                            <th>Raw Water Flow</th>
                            <th>Clarified Water Flow</th>
                            <th>Softener Inlet pH</th>
                            <th>Softener Outlet Hardness</th>
                            <th>Alum Dosing</th>
                            <th>Polymer Dosing</th>
                            <th>Boiler Feed Tank Temp</th>
                            <th>Boiler Feed Water pH</th>
                            <th>Boiler Feed TDS</th>
                            <th>Action Taken/Status</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record['details'] as $row)
                            <tr data-testid="process-water-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['shift'] ?? '-' }}</td>
                                <td>{{ $row['inspector_id'] ?? '-' }}</td>
                                <td>{{ $row['raw_water_flow_m3h'] ?? '-' }}</td>
                                <td>{{ $row['clarified_water_flow_m3h'] ?? '-' }}</td>
                                <td>{{ $row['softener_inlet_ph'] ?? '-' }}</td>
                                <td>{{ $row['softener_outlet_hardness_ppm'] ?? '-' }}</td>
                                <td>{{ $row['alum_dosing_kgh'] ?? '-' }}</td>
                                <td>{{ $row['polymer_dosing_gh'] ?? '-' }}</td>
                                <td>{{ $row['boiler_feed_tank_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['boiler_feed_water_ph'] ?? '-' }}</td>
                                <td>{{ $row['boiler_feed_tds_ppm'] ?? '-' }}</td>
                                <td>{{ $row['action_taken_status'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr data-testid="process-water-detail-rows-empty">
                                <td colspan="14">Belum ada baris process water detail.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pw-detail-section">
            <h4 class="pw-detail-section__title">Verifikasi</h4>
            <div class="pw-detail-grid">
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Inputted By</span>
                    <span class="pw-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Checked By</span>
                    <span class="pw-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Acknowledged By</span>
                    <span class="pw-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="pw-detail-field">
                    <span class="pw-detail-field__label">Status</span>
                    <span class="pw-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .pw-page { display: flex; flex-direction: column; gap: 20px; }
        .pw-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .pw-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pw-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .pw-page__actions { display: flex; gap: 8px; }
        .pw-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .pw-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .pw-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .pw-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .pw-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .pw-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .pw-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .pw-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .pw-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .pw-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .pw-table-wrap { width: 100%; overflow-x: auto; }
        .pw-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1400px; }
        .pw-detail-table th, .pw-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
    </style>
</div>
