<div class="sf-page">
    <div class="sf-page__header">
        <div>
            <h2 class="sf-page__title">Detail Sterilizer</h2>
            <p class="sf-page__subtitle">Data record Sterilizer tersimpan (read-only).</p>
        </div>
        <div class="sf-page__actions">
            @if ($record)
                <a href="{{ route('data.sterilizer.edit', ['id' => $id]) }}" class="sf-button sf-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.sterilizer') }}" class="sf-button sf-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="sf-alert sf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="sf-detail-section">
            <h4 class="sf-detail-section__title">Identitas Sterilizer</h4>
            <div class="sf-detail-grid">
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Sterilizer ID</span>
                    <span class="sf-detail-field__value" data-testid="detail-sterilizer-id">{{ $record['sterilizer_id'] }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Tanggal</span>
                    <span class="sf-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Station</span>
                    <span class="sf-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Note</span>
                    <span class="sf-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="sf-detail-section">
            <h4 class="sf-detail-section__title">Log Siklus Sterilisasi</h4>
            @if (count($record['details']) > 0)
                <div class="sf-table-wrap">
                    <table class="sf-detail-table" data-testid="sterilizer-detail-log">
                        <thead>
                            <tr>
                                <th>Sterilizer No</th>
                                <th>Close Door Time</th>
                                <th>Peak 1 Time</th>
                                <th>Exhaust 1 Time</th>
                                <th>Peak 2 Time</th>
                                <th>Exhaust 2 Time</th>
                                <th>Peak 3 Time</th>
                                <th>Exhaust 3 Time</th>
                                <th>Open Door Time</th>
                                <th>Duration (Minutes)</th>
                                <th>Number of Cages</th>
                                <th>Cages Status</th>
                                <th>Checked by SPV</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($record['details'] as $row)
                                <tr>
                                    <td>{{ $row['sterilizer_no'] ?: '-' }}</td>
                                    <td>{{ $row['close_door_time'] ?: '-' }}</td>
                                    <td>{{ $row['peak_1_time'] ?: '-' }}</td>
                                    <td>{{ $row['exhaust_1_time'] ?: '-' }}</td>
                                    <td>{{ $row['peak_2_time'] ?: '-' }}</td>
                                    <td>{{ $row['exhaust_2_time'] ?: '-' }}</td>
                                    <td>{{ $row['peak_3_time'] ?: '-' }}</td>
                                    <td>{{ $row['exhaust_3_time'] ?: '-' }}</td>
                                    <td>{{ $row['open_door_time'] ?: '-' }}</td>
                                    <td>{{ $row['duration_minutes'] ?? '-' }}</td>
                                    <td>{{ $row['number_of_cages'] ?? '-' }}</td>
                                    <td>{{ $row['cages_status'] ?: '-' }}</td>
                                    <td>{{ $row['checked_by_spv'] ? 'Ya' : 'Tidak' }}</td>
                                    <td>{{ $row['remarks'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="sf-detail-empty">Belum ada log siklus sterilisasi.</p>
            @endif
        </div>

        <div class="sf-detail-section">
            <h4 class="sf-detail-section__title">Verifikasi</h4>
            <div class="sf-detail-grid">
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Inputted By</span>
                    <span class="sf-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Checked By</span>
                    <span class="sf-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Acknowledged By</span>
                    <span class="sf-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="sf-detail-field">
                    <span class="sf-detail-field__label">Status</span>
                    <span class="sf-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .sf-page { display: flex; flex-direction: column; gap: 20px; }
        .sf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .sf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .sf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .sf-page__actions { display: flex; gap: 8px; }
        .sf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .sf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .sf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .sf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .sf-detail-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; }
        .sf-detail-section__title { margin: 0 0 16px; font-size: 16px; font-weight: 700; }
        .sf-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .sf-detail-field { display: flex; flex-direction: column; gap: 4px; }
        .sf-detail-field__label { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .sf-detail-field__value { font-size: 14px; color: var(--color-text, #1f2937); font-weight: 500; }
        .sf-table-wrap { width: 100%; overflow-x: auto; }
        .sf-detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sf-detail-table th, .sf-detail-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .sf-detail-empty { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
    </style>
</div>
