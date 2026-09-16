<div class="th-page">
    <div class="th-page__header">
        <div>
            <h2 class="th-page__title">Detail Threshing</h2>
            <p class="th-page__subtitle">Data record Threshing tersimpan (read-only).</p>
        </div>
        <div class="th-page__actions">
            @if ($record)
                <a href="{{ route('data.threshing.edit', ['id' => $id]) }}" class="th-button th-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.threshing') }}" class="th-button th-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="th-alert th-alert--error" role="alert" data-testid="record-not-found">
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

        <div class="th-detail-section">
            <h4 class="th-detail-section__title">Identitas Threshing</h4>
            <div class="th-detail-grid">
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Thresher ID</span>
                    <span class="th-detail-field__value" data-testid="detail-thresher-id">{{ $record['thresher_id'] }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Tanggal</span>
                    <span class="th-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Station</span>
                    <span class="th-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Note</span>
                    <span class="th-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="th-detail-section">
            <h4 class="th-detail-section__title">Threshing Detail</h4>
            <div class="th-table-scroll">
                <table class="th-detail-table" data-testid="threshing-detail-grid">
                    <thead>
                        <tr>
                            <th>Time-Slot</th>
                            <th>FFB Throughput</th>
                            <th>Drum Speed</th>
                            <th>Motor Current</th>
                            <th>Unstripped Bunch</th>
                            <th>Oil Loss</th>
                            <th>Downtime Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record['details'] as $row)
                            <tr data-testid="threshing-detail-row-{{ $row['id'] }}">
                                <td>{{ $row['time_slot'] }}</td>
                                <td>{{ $row['ffb_throughput_mt_hour'] ?? '-' }}</td>
                                <td>{{ $row['thresher_drum_speed_rpm'] ?? '-' }}</td>
                                <td>{{ $row['motor_current_amps'] ?? '-' }}</td>
                                <td>{{ $row['unstripped_bunch_count_percent'] ?? '-' }}</td>
                                <td>{{ $row['empty_bunch_oil_loss_percent'] ?? '-' }}</td>
                                <td>{{ $row['downtime_reason'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="th-detail-section">
            <h4 class="th-detail-section__title">Target Operasional</h4>
            <div class="th-table-scroll">
                <table class="th-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Standard Operational Target</th>
                            <th>Action Plan on Deviation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->parameter }}</td>
                                <td>{{ $target->standard_operational_target }}</td>
                                <td>{{ $target->action_plan_on_deviation }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="th-detail-section">
            <h4 class="th-detail-section__title">Verifikasi</h4>
            <div class="th-detail-grid">
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Inputted By</span>
                    <span class="th-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Checked By</span>
                    <span class="th-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Acknowledged By</span>
                    <span class="th-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="th-detail-field">
                    <span class="th-detail-field__label">Status</span>
                    <span class="th-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .th-page { display: flex; flex-direction: column; gap: 20px; }
        .th-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .th-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .th-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .th-page__actions { display: flex; gap: 8px; }
        .th-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .th-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .th-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .th-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .th-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .th-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .th-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .th-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .th-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .th-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .th-table-scroll { width: 100%; overflow-x: auto; }
        .th-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .th-detail-table th, .th-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .th-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .th-target-table th, .th-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .th-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .th-target-table th:first-child, .th-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
