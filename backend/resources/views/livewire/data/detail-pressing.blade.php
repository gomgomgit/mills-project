<div class="pr-page">
    <div class="pr-page__header">
        <div>
            <h2 class="pr-page__title">Detail Pressing</h2>
            <p class="pr-page__subtitle">Data record Pressing tersimpan (read-only).</p>
        </div>
        <div class="pr-page__actions">
            @if ($record)
                <a href="{{ route('data.pressing.edit', ['id' => $id]) }}" class="pr-button pr-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.pressing') }}" class="pr-button pr-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="pr-alert pr-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="pr-detail-section">
            <h4 class="pr-detail-section__title">Identitas Pressing</h4>
            <div class="pr-detail-grid">
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Presser ID</span>
                    <span class="pr-detail-field__value" data-testid="detail-presser-id">{{ $record['presser_id'] }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Tanggal</span>
                    <span class="pr-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Station</span>
                    <span class="pr-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Note</span>
                    <span class="pr-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="pr-detail-section">
            <h4 class="pr-detail-section__title">Pressing Detail</h4>
            <div class="pr-table-scroll">
                <table class="pr-detail-table" data-testid="pressing-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Digester Temp</th>
                            <th>Digester Level</th>
                            <th>Press Motor Current</th>
                            <th>Cone Hydraulic Pressure</th>
                            <th>Dilution Water Temp</th>
                            <th>Downtime Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record['details'] as $row)
                            <tr data-testid="pressing-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['digester_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['digester_level_percent'] ?? '-' }}</td>
                                <td>{{ $row['press_motor_current_amps'] ?? '-' }}</td>
                                <td>{{ $row['cone_hydraulic_pressure_bar'] ?? '-' }}</td>
                                <td>{{ $row['dilution_water_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['downtime_reason'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pr-detail-section">
            <h4 class="pr-detail-section__title">Target Operasional</h4>
            <div class="pr-table-scroll">
                <table class="pr-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Parameter/Metric</th>
                            <th>Target Operating Range</th>
                            <th>Critical Trigger / Action Limit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->parameter_metric }}</td>
                                <td>{{ $target->target_operating_range }}</td>
                                <td>{{ $target->critical_trigger_action_limit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pr-detail-section">
            <h4 class="pr-detail-section__title">Verifikasi</h4>
            <div class="pr-detail-grid">
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Inputted By</span>
                    <span class="pr-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Checked By</span>
                    <span class="pr-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Acknowledged By</span>
                    <span class="pr-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="pr-detail-field">
                    <span class="pr-detail-field__label">Status</span>
                    <span class="pr-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .pr-page { display: flex; flex-direction: column; gap: 20px; }
        .pr-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .pr-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pr-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .pr-page__actions { display: flex; gap: 8px; }
        .pr-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .pr-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .pr-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .pr-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .pr-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .pr-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .pr-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .pr-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .pr-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .pr-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .pr-table-scroll { width: 100%; overflow-x: auto; }
        .pr-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pr-detail-table th, .pr-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .pr-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .pr-target-table th, .pr-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .pr-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .pr-target-table th:first-child, .pr-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
