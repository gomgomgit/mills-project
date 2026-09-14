<div class="dp-page">
    <div class="dp-page__header">
        <div>
            <h2 class="dp-page__title">Detail Depricarping</h2>
            <p class="dp-page__subtitle">Data record Depricarping tersimpan (read-only).</p>
        </div>
        <div class="dp-page__actions">
            @if ($record)
                <a href="{{ route('data.depricarping.edit', ['id' => $id]) }}" class="dp-button dp-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.depricarping') }}" class="dp-button dp-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="dp-alert dp-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="dp-detail-section">
            <h4 class="dp-detail-section__title">Identitas Depricarping</h4>
            <div class="dp-detail-grid">
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Presser ID</span>
                    <span class="dp-detail-field__value" data-testid="detail-presser-id">{{ $record['presser_id'] }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Tanggal</span>
                    <span class="dp-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Station</span>
                    <span class="dp-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Note</span>
                    <span class="dp-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dp-detail-section">
            <h4 class="dp-detail-section__title">Depricarping Detail</h4>
            <table class="dp-detail-table" data-testid="depricarping-detail-grid">
                <thead>
                    <tr>
                        <th>Time-Slot</th>
                        <th>Fan Static Pressure</th>
                        <th>Polishing Drum Speed</th>
                        <th>Air Velocity</th>
                        <th>Fibre Moisture</th>
                        <th>Kernel Recovery in Fibre</th>
                        <th>Nut Silo 1 Temp</th>
                        <th>Nut Silo 2 Temp</th>
                        <th>Downtime (Mins)</th>
                        <th>Findings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record['details'] as $row)
                        <tr data-testid="depricarping-detail-row-{{ $row['id'] }}">
                            <td>{{ $row['time_slot'] }}</td>
                            <td>{{ $row['fan_static_pressure_mmh2o'] ?? '-' }}</td>
                            <td>{{ $row['polishing_drum_speed_rpm'] ?? '-' }}</td>
                            <td>{{ $row['air_velocity_ms'] ?? '-' }}</td>
                            <td>{{ $row['fibre_moisture_percent'] ?? '-' }}</td>
                            <td>{{ $row['kernel_recovery_in_fibre_percent'] ?? '-' }}</td>
                            <td>{{ $row['nut_silo_1_temp_c'] ?? '-' }}</td>
                            <td>{{ $row['nut_silo_2_temp_c'] ?? '-' }}</td>
                            <td>{{ $row['downtime_minutes'] ?? '-' }}</td>
                            <td>{{ $row['findings'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="dp-detail-section">
            <h4 class="dp-detail-section__title">Target Operasional</h4>
            <table class="dp-target-table" data-testid="operational-target-table">
                <thead>
                    <tr>
                        <th>Parameter/Metric</th>
                        <th>Target Range</th>
                        <th>Critical Limit</th>
                        <th>Operational Consequence / Justification</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($operationalTargets as $target)
                        <tr>
                            <td>{{ $target->parameter_metric }}</td>
                            <td>{{ $target->target_range }}</td>
                            <td>{{ $target->critical_limit }}</td>
                            <td>{{ $target->operational_consequence_justification }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="dp-detail-section">
            <h4 class="dp-detail-section__title">Verifikasi</h4>
            <div class="dp-detail-grid">
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Inputted By</span>
                    <span class="dp-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Checked By</span>
                    <span class="dp-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Acknowledged By</span>
                    <span class="dp-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="dp-detail-field">
                    <span class="dp-detail-field__label">Status</span>
                    <span class="dp-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .dp-page { display: flex; flex-direction: column; gap: 20px; }
        .dp-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .dp-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .dp-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .dp-page__actions { display: flex; gap: 8px; }
        .dp-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .dp-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .dp-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .dp-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .dp-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .dp-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .dp-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .dp-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .dp-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .dp-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .dp-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .dp-detail-table th, .dp-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .dp-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .dp-target-table th, .dp-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .dp-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .dp-target-table th:first-child, .dp-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
