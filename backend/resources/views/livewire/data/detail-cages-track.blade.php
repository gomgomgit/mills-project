<div class="ct-page">
    <div class="ct-page__header">
        <div>
            <h2 class="ct-page__title">Detail Cages Track</h2>
            <p class="ct-page__subtitle">Data record Cages Track tersimpan (read-only).</p>
        </div>
        <div class="ct-page__actions">
            @if ($record)
                <a href="{{ route('data.cages-track.edit', ['id' => $id]) }}" class="ct-button ct-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.cages-track') }}" class="ct-button ct-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="ct-alert ct-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="ct-detail-section">
            <h4 class="ct-detail-section__title">Identitas Cages Track</h4>
            <div class="ct-detail-grid">
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Cages Track Number</span>
                    <span class="ct-detail-field__value" data-testid="detail-cages-track-number">{{ $record['cages_track_number'] }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Tanggal</span>
                    <span class="ct-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Station</span>
                    <span class="ct-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Tippler Start Time</span>
                    <span class="ct-detail-field__value">{{ $record['tippler_start_time'] ? \Illuminate\Support\Carbon::parse($record['tippler_start_time'])->format('d M Y H:i') : '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Tippler Stop Time</span>
                    <span class="ct-detail-field__value">{{ $record['tippler_stop_time'] ? \Illuminate\Support\Carbon::parse($record['tippler_stop_time'])->format('d M Y H:i') : '-' }}</span>
                </div>
            </div>
        </div>

        <div class="ct-detail-section">
            <h4 class="ct-detail-section__title">Data Sesi</h4>
            <div class="ct-detail-grid">
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Cages Out</span>
                    <span class="ct-detail-field__value">{{ $record['cages_out'] }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Cages Tipped</span>
                    <span class="ct-detail-field__value">{{ $record['cages_tipped'] }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Note</span>
                    <span class="ct-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="ct-detail-section">
            <h4 class="ct-detail-section__title">Cages Tipped Time</h4>
            @if (count($record['tipped_times']) > 0)
                <table class="ct-detail-table" data-testid="cages-tipped-time-grid">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Cage Dicentang</th>
                            <th>Total Cages</th>
                            <th>Cages Remain</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record['tipped_times'] as $row)
                            <tr>
                                <td>{{ str_pad((string) $row['tipped_hour'], 2, '0', STR_PAD_LEFT) }}:00</td>
                                <td>{{ $row['checked_cage_numbers'] ?: '-' }}</td>
                                <td>{{ $row['total_cages'] }}</td>
                                <td>{{ $row['cages_remain'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="ct-detail-empty">Belum ada data Cages Tipped Time.</p>
            @endif
        </div>

        <div class="ct-detail-section">
            <h4 class="ct-detail-section__title">Verifikasi</h4>
            <div class="ct-detail-grid">
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Inputted By</span>
                    <span class="ct-detail-field__value">{{ $record['created_by_name'] ?: '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Checked By</span>
                    <span class="ct-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Acknowledged By</span>
                    <span class="ct-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="ct-detail-field">
                    <span class="ct-detail-field__label">Status</span>
                    <span class="ct-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ct-page {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .ct-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .ct-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .ct-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--color-text-muted, #6b7280);
        }

        .ct-page__actions {
            display: flex;
            gap: 8px;
        }

        .ct-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .ct-button--secondary {
            background: #fff;
            color: var(--color-text, #1f2937);
            border: 1px solid var(--color-border, #d1d5db);
        }

        .ct-alert {
            padding: 12px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
        }

        .ct-alert--error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .ct-detail-section {
            background: #fff;
            border: 1px solid var(--color-border, #d1d5db);
            border-radius: 8px;
            padding: 20px;
        }

        .ct-detail-section__title {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 700;
        }

        .ct-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .ct-detail-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ct-detail-field__label {
            font-size: 12px;
            color: var(--color-text-muted, #6b7280);
        }

        .ct-detail-field__value {
            font-size: 14px;
            color: var(--color-text, #1f2937);
            font-weight: 500;
        }

        .ct-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .ct-detail-table th,
        .ct-detail-table td {
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid var(--color-border, #d1d5db);
        }

        .ct-detail-empty {
            margin: 0;
            font-size: 14px;
            color: var(--color-text-muted, #6b7280);
        }
    </style>
</div>
