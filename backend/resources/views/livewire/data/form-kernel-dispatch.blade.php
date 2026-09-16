<div class="kf-page">
    <div class="kf-page__header">
        <div>
            <h2 class="kf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Kernel Dispatch</h2>
            <p class="kf-page__subtitle">{{ $isEdit ? 'Ubah record Kernel Dispatch tersimpan.' : 'Buat record Kernel Dispatch baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.kernel-dispatch.detail', ['id' => $id]) : route('data.kernel-dispatch') }}" class="kf-button kf-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="kf-alert kf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="kf-alert kf-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="kf-form">
            <div class="kf-section">
                <h4 class="kf-section__title">Identitas Kernel Dispatch</h4>

                @if (! $isEdit)
                    <div class="kf-field">
                        <label class="kf-field__label" for="production_line_id">Production Line <span class="kf-required">*</span></label>
                        <select id="production_line_id" wire:model="form.production_line_id" class="kf-input" data-testid="production-line-select">
                            <option value="">Pilih Production Line</option>
                            @foreach ($productionLineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['production_line_id']))
                            <span class="kf-field__error">{{ $errors_['production_line_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="kf-field">
                        <span class="kf-field__label">Station</span>
                        <span class="kf-field__readonly" data-testid="station-readonly">{{ $stationName ?? '-' }}</span>
                    </div>
                @endif

                <div class="kf-field">
                    <label class="kf-field__label" for="kernel_dispatch_id">Kernel Dispatch ID <span class="kf-required">*</span></label>
                    <input id="kernel_dispatch_id" type="text" wire:model="form.kernel_dispatch_id" class="kf-input" data-testid="kernel-dispatch-id-input">
                    @if (isset($errors_['kernel_dispatch_id']))
                        <span class="kf-field__error">{{ $errors_['kernel_dispatch_id'] }}</span>
                    @endif
                </div>

                <div class="kf-field">
                    <label class="kf-field__label" for="date">Tanggal <span class="kf-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="kf-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="kf-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>

                <div class="kf-field kf-field--full">
                    <label class="kf-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="kf-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="kf-section kf-section--wide">
                <h4 class="kf-section__title">Log Kejadian Kernel Dispatch</h4>

                @if ($detailError)
                    <div class="kf-alert kf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <span class="kf-field__hint">Tambahkan satu baris per kejadian pengiriman kernel &mdash; jumlah baris tidak dibatasi.</span>

                <div class="kf-table-wrap">
                    <table class="kf-detail-table" data-testid="kernel-dispatch-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Weighbridge Ticket No</th>
                                <th>Waybill Number</th>
                                <th>Transporter/Contractor</th>
                                <th>Vehicle Plate No</th>
                                <th>Driver Name</th>
                                <th>Silo Source ID</th>
                                <th>Destination/Buyer</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>Kernel Moisture (%)</th>
                                <th>Dirt/Impurities (%)</th>
                                <th>FFA (%)</th>
                                <th>Broken Kernel (%)</th>
                                <th>Security Seal No (Top)</th>
                                <th>Security Seal No (Bottom)</th>
                                <th>Weighbridge Operator ID</th>
                                <th>Remarks/Gate Status</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="detail-row-{{ $index }}">
                                    <td><input type="date" wire:model="detailRows.{{ $index }}.event_date" class="kf-input" data-testid="detail-event-date-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.shift" class="kf-input" data-testid="detail-shift-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.weighbridge_ticket_no" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.waybill_number" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.transporter_contractor" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.vehicle_plate_no" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.driver_name" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.silo_source_id" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.destination_buyer" class="kf-input"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.gross_weight_mt" class="kf-input" data-testid="detail-gross-weight-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.tare_weight_mt" class="kf-input" data-testid="detail-tare-weight-{{ $index }}"></td>
                                    <td><input type="text" value="{{ $this->rowNetWeight($index) ?? '-' }}" class="kf-input" data-testid="detail-net-weight-{{ $index }}" disabled></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.kernel_moisture_percent" class="kf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.dirt_impurities_percent" class="kf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.ffa_percent" class="kf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.broken_kernel_percent" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.security_seal_no_top" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.security_seal_no_bottom" class="kf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.weighbridge_operator_id" class="kf-input"></td>
                                    <td>
                                        <select wire:model="detailRows.{{ $index }}.remarks_gate_status" class="kf-input" data-testid="detail-gate-status-{{ $index }}">
                                            <option value="">-</option>
                                            <option value="released">Released</option>
                                            <option value="not_released">Not Released</option>
                                        </select>
                                    </td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.findings" class="kf-input"></td>
                                    <td><button type="button" wire:click="removeDetailRow({{ $index }})" class="kf-button kf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="kf-button kf-button--secondary" data-testid="add-row-button">+ Tambah Baris</button>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="kf-section">
                    <h4 class="kf-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="kf-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="kf-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="kf-actions">
                <button type="submit" class="kf-button kf-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .kf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1280px; }
        .kf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .kf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .kf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .kf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .kf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .kf-button:disabled { opacity: 0.5; cursor: not-allowed; }
        .kf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .kf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .kf-form { display: flex; flex-direction: column; gap: 20px; }
        .kf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .kf-section--wide { display: flex; flex-direction: column; gap: 12px; }
        .kf-section > *:not(.kf-field) { grid-column: 1 / -1; }
        .kf-field--full { grid-column: 1 / -1; }
        @media (max-width: 767px) { .kf-section { grid-template-columns: 1fr; } }
        .kf-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .kf-field { display: flex; flex-direction: column; gap: 4px; }
        .kf-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .kf-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .kf-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .kf-field__error { font-size: 12px; color: #b91c1c; }
        .kf-required { color: #b91c1c; }
        .kf-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
        .kf-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .kf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .kf-actions { display: flex; justify-content: flex-end; }
        .kf-table-wrap { width: 100%; overflow-x: auto; }
        .kf-detail-table { border-collapse: collapse; min-width: 2000px; }
        .kf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .kf-detail-table td { padding: 6px 8px; vertical-align: top; }
        .kf-detail-table .kf-input { min-width: 110px; }
    </style>
</div>
