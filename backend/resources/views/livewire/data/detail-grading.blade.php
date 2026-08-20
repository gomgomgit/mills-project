<div class="dg-page">
    <div class="dg-page__header">
        <div>
            <h2 class="dg-page__title">Detail Grading</h2>
            <p class="dg-page__subtitle">Data record Grading tersimpan (read-only).</p>
        </div>
        <div class="dg-page__actions">
            @if ($record)
                <a href="{{ route('data.grading.edit', ['id' => $id]) }}" class="dg-button dg-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.grading') }}" class="dg-button dg-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="dg-alert dg-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Identitas Grading</h4>
            <div class="dg-detail-grid">
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Grading Number</span>
                    <span class="dg-detail-field__value" data-testid="detail-grading-number">{{ $record['grading_number'] }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Tanggal</span>
                    <span class="dg-detail-field__value">{{ $record['date'] ? \Illuminate\Support\Carbon::parse($record['date'])->format('d M Y') : '-' }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">No. WB Card</span>
                    <span class="dg-detail-field__value">{{ $record['wb_card_number'] ?: '-' }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Station</span>
                    <span class="dg-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Kendaraan</h4>
            <div class="dg-detail-grid">
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">License Plate No</span>
                    <span class="dg-detail-field__value">{{ $record['license_plate_no'] }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Vehicle Code</span>
                    <span class="dg-detail-field__value">{{ $record['vehicle_code'] }}</span>
                </div>
            </div>
        </div>

        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Asal Muatan</h4>
            <div class="dg-detail-grid">
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Estate/Supplier</span>
                    <span class="dg-detail-field__value">{{ $record['estate_supplier'] }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Divisi</span>
                    <span class="dg-detail-field__value">{{ $record['division'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Data Grading</h4>
            <div class="dg-detail-grid">
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Netto (kg)</span>
                    <span class="dg-detail-field__value">{{ $record['netto'] }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Quantity (bunch)</span>
                    <span class="dg-detail-field__value">{{ $record['quantity'] }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Note</span>
                    <span class="dg-detail-field__value">{{ $record['note'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Grading Detail</h4>
            @if (count($record['details']) > 0)
                <table class="dg-detail-table" data-testid="grading-detail-grid">
                    <thead>
                        <tr>
                            <th>Quality Parameter</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record['details'] as $detail)
                            <tr>
                                <td>{{ $detail['grading_parameter_name'] ?: '-' }}</td>
                                <td>{{ $detail['quantity'] }}</td>
                                <td>{{ $detail['uom'] }}</td>
                                <td>{{ $detail['percentage'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="dg-detail-empty">Belum ada data penilaian.</p>
            @endif
        </div>

        <div class="dg-detail-section">
            <h4 class="dg-detail-section__title">Verifikasi</h4>
            <div class="dg-detail-grid">
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Acknowledged By</span>
                    <span class="dg-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="dg-detail-field">
                    <span class="dg-detail-field__label">Status</span>
                    <span class="dg-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .dg-page {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dg-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .dg-page__actions {
            display: flex;
            gap: 8px;
        }

        .dg-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .dg-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--color-text-muted, #6b7280);
        }

        .dg-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .dg-button--secondary {
            background: #fff;
            color: var(--color-text, #1f2937);
            border: 1px solid var(--color-border, #d1d5db);
        }

        .dg-alert {
            padding: 12px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
        }

        .dg-alert--error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .dg-detail-section {
            background: #fff;
            border: 1px solid var(--color-border, #d1d5db);
            border-radius: 8px;
            padding: 20px;
        }

        .dg-detail-section__title {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 700;
        }

        .dg-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .dg-detail-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dg-detail-field__label {
            font-size: 12px;
            color: var(--color-text-muted, #6b7280);
        }

        .dg-detail-field__value {
            font-size: 14px;
            color: var(--color-text, #1f2937);
            font-weight: 500;
        }

        .dg-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .dg-detail-table th,
        .dg-detail-table td {
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid var(--color-border, #d1d5db);
        }

        .dg-detail-empty {
            margin: 0;
            font-size: 14px;
            color: var(--color-text-muted, #6b7280);
        }
    </style>
</div>
