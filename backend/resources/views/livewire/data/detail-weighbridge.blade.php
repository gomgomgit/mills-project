<div class="dw-page">
    <div class="dw-page__header">
        <div>
            <h2 class="dw-page__title">Detail Weighbridge</h2>
            <p class="dw-page__subtitle">Data record Weighbridge tersimpan (read-only).</p>
        </div>
        <div class="dw-page__actions">
            @if ($record)
                <a href="{{ route('data.weighbridge.edit', ['id' => $id]) }}" class="dw-button dw-button--secondary" data-testid="edit-button">Edit</a>
            @endif
            <a href="{{ route('data.weighbridge') }}" class="dw-button dw-button--secondary" data-testid="back-button">Back</a>
        </div>
    </div>

    @if ($notFound)
        <div class="dw-alert dw-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @elseif ($record)
        <div class="dw-detail-section">
            <h4 class="dw-detail-section__title">Identitas Weighbridge</h4>
            <div class="dw-detail-grid">
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Tipe Weighbridge</span>
                    <span class="dw-detail-field__value" data-testid="detail-weighbridge-type">{{ $record['weighbridge_type'] === 'dispatch' ? 'Dispatch' : 'Receive' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">No. WB Card</span>
                    <span class="dw-detail-field__value">{{ $record['wb_card_number'] }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">{{ $record['weighbridge_type'] === 'dispatch' ? 'Tanggal & Waktu Dispatch' : 'Tanggal & Waktu Arrival' }}</span>
                    <span class="dw-detail-field__value" data-testid="detail-record-datetime">{{ $record['record_datetime'] ? \Illuminate\Support\Carbon::parse($record['record_datetime'])->format('d M Y H:i') : '-' }}</span>
                </div>
                @if ($record['weighbridge_type'] === 'dispatch')
                    <div class="dw-detail-field">
                        <span class="dw-detail-field__label">Tujuan Muatan</span>
                        <span class="dw-detail-field__value" data-testid="detail-destination">{{ $record['destination'] ?: '-' }}</span>
                    </div>
                @endif
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Station</span>
                    <span class="dw-detail-field__value">{{ $record['station_name'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dw-detail-section">
            <h4 class="dw-detail-section__title">Kendaraan &amp; Supir</h4>
            <div class="dw-detail-grid">
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">No. Kendaraan</span>
                    <span class="dw-detail-field__value">{{ $record['vehicle_number'] }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Nama Sopir</span>
                    <span class="dw-detail-field__value">{{ $record['driver_name'] }}</span>
                </div>
            </div>
        </div>

        <div class="dw-detail-section">
            <h4 class="dw-detail-section__title">Asal Muatan</h4>
            <div class="dw-detail-grid">
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Estate/Supplier</span>
                    <span class="dw-detail-field__value">{{ $record['estate_supplier'] }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Divisi</span>
                    <span class="dw-detail-field__value">{{ $record['division'] ?: '-' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Blok</span>
                    <span class="dw-detail-field__value">{{ $record['block'] ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dw-detail-section">
            <h4 class="dw-detail-section__title">Data Timbangan</h4>
            <div class="dw-detail-grid">
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Berat Kotor (Gross Weight) (kg)</span>
                    <span class="dw-detail-field__value">{{ $record['gross_weight'] }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Berat Kosong (Tare Weight) (kg)</span>
                    <span class="dw-detail-field__value">{{ $record['tare_weight'] ?? '-' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Berat Bersih (Net Weight) (kg)</span>
                    <span class="dw-detail-field__value">{{ $record['net_weight'] ?? '-' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Kuantitas (tandan)</span>
                    <span class="dw-detail-field__value">{{ $record['quantity'] ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="dw-detail-section">
            <h4 class="dw-detail-section__title">Verifikasi</h4>
            <div class="dw-detail-grid">
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Checked By</span>
                    <span class="dw-detail-field__value">{{ $record['checked_by_name'] ?: '-' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Acknowledged By</span>
                    <span class="dw-detail-field__value">{{ $record['acknowledged_by_name'] ?: '-' }}</span>
                </div>
                <div class="dw-detail-field">
                    <span class="dw-detail-field__label">Status</span>
                    <span class="dw-detail-field__value">{{ $record['status'] }}</span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .dw-page {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .dw-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .dw-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .dw-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--color-text-muted, #6b7280);
        }

        .dw-page__actions {
            display: flex;
            gap: 8px;
        }

        .dw-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .dw-button--secondary {
            background: #fff;
            color: var(--color-text, #1f2937);
            border: 1px solid var(--color-border, #d1d5db);
        }

        .dw-alert {
            padding: 12px 16px;
            border-radius: var(--radius-input, 6px);
            font-size: 14px;
        }

        .dw-alert--error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .dw-detail-section {
            background: #fff;
            border: 1px solid var(--color-border, #d1d5db);
            border-radius: 8px;
            padding: 20px;
        }

        .dw-detail-section__title {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 700;
        }

        .dw-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .dw-detail-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dw-detail-field__label {
            font-size: 12px;
            color: var(--color-text-muted, #6b7280);
        }

        .dw-detail-field__value {
            font-size: 14px;
            color: var(--color-text, #1f2937);
            font-weight: 500;
        }
    </style>
</div>
