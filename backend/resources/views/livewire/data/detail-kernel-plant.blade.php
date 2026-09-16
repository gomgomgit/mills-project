<div class="kp-page">
    <div class="kp-page__header">
        <div>
            <h2 class="kp-page__title">Detail Kernel Plant</h2>
            <p class="kp-page__subtitle">Data record Kernel Plant tersimpan (read-only).</p>
        </div>
        <div class="kp-page__actions">
            @if ($record)
                <a href="{{ route('data.kernel-plant.edit', ['id' => $id]) }}" class="kp-button kp-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.kernel-plant') }}" class="kp-button kp-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="kp-alert kp-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="kp-detail-section">
            <h4 class="kp-detail-section__title">Identitas Kernel Plant</h4>
            <div class="kp-detail-grid">
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Kernel Plant ID</span>
                    <span class="kp-detail-field__value" data-testid="detail-kernel-plant-id">{{ $record['kernel_plant_id'] }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Tanggal</span>
                    <span class="kp-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Station</span>
                    <span class="kp-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Note</span>
                    <span class="kp-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="kp-detail-section">
            <h4 class="kp-detail-section__title">Kernel Plant Detail</h4>
            <div class="kp-table-scroll">
                <table class="kp-detail-table" data-testid="kernel-plant-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>Ripple Mill 1</th>
                            <th>Ripple Mill 2</th>
                            <th>Claybath/Hydro SG</th>
                            <th>Kernel Silo 1 Temp</th>
                            <th>Kernel Silo 2 Temp</th>
                            <th>Kernel Moisture</th>
                            <th>Shell Loss</th>
                            <th>Downtime (Mins)</th>
                            <th>Findings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record['details'] as $row)
                            <tr data-testid="kernel-plant-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['ripple_mill_1_amps'] ?? '-' }}</td>
                                <td>{{ $row['ripple_mill_2_amps'] ?? '-' }}</td>
                                <td>{{ $row['claybath_hydro_sg'] ?? '-' }}</td>
                                <td>{{ $row['kernel_silo_1_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['kernel_silo_2_temp_c'] ?? '-' }}</td>
                                <td>{{ $row['kernel_moisture_percent'] ?? '-' }}</td>
                                <td>{{ $row['shell_loss_percent'] ?? '-' }}</td>
                                <td>{{ $row['downtime_minutes'] ?? '-' }}</td>
                                <td>{{ $row['findings'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kp-detail-section">
            <h4 class="kp-detail-section__title">Target Operasional</h4>
            <div class="kp-table-scroll">
                <table class="kp-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Equipment / Parameter</th>
                            <th>Target Benchmark</th>
                            <th>Corrective Action Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->equipment_parameter }}</td>
                                <td>{{ $target->target_benchmark }}</td>
                                <td>{{ $target->corrective_action_plan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kp-detail-section">
            <h4 class="kp-detail-section__title">Verifikasi</h4>
            <div class="kp-detail-grid">
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Inputted By</span>
                    <span class="kp-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Checked By</span>
                    <span class="kp-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Acknowledged By</span>
                    <span class="kp-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="kp-detail-field">
                    <span class="kp-detail-field__label">Status</span>
                    <span class="kp-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .kp-page { display: flex; flex-direction: column; gap: 20px; }
        .kp-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .kp-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kp-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .kp-page__actions { display: flex; gap: 8px; }
        .kp-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .kp-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .kp-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .kp-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .kp-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .kp-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .kp-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .kp-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .kp-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .kp-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .kp-table-scroll { width: 100%; overflow-x: auto; }
        .kp-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .kp-detail-table th, .kp-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .kp-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .kp-target-table th, .kp-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .kp-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .kp-target-table th:first-child, .kp-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
