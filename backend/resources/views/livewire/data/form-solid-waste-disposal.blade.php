<div class="sf-page">
    <div class="sf-page__header">
        <div>
            <h2 class="sf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Solid Waste Disposal</h2>
            <p class="sf-page__subtitle">{{ $isEdit ? 'Ubah record Solid Waste Disposal tersimpan.' : 'Buat record Solid Waste Disposal baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.solid-waste-disposal.detail', ['id' => $id]) : route('data.solid-waste-disposal') }}" class="sf-button sf-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="sf-alert sf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="sf-alert sf-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="sf-form">
            <div class="sf-section">
                <h4 class="sf-section__title">Identitas Solid Waste Disposal</h4>

                @if (! $isEdit)
                    <div class="sf-field">
                        <label class="sf-field__label" for="production_line_id">Production Line <span class="sf-required">*</span></label>
                        <select id="production_line_id" wire:model="form.production_line_id" class="sf-input" data-testid="production-line-select">
                            <option value="">Pilih Production Line</option>
                            @foreach ($productionLineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['production_line_id']))
                            <span class="sf-field__error">{{ $errors_['production_line_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="sf-field">
                        <span class="sf-field__label">Station</span>
                        <span class="sf-field__readonly" data-testid="station-readonly">{{ $stationName ?? '-' }}</span>
                    </div>
                @endif

                <div class="sf-field">
                    <label class="sf-field__label" for="solid_waste_disposal_id">Solid Waste Disp. ID <span class="sf-required">*</span></label>
                    <input id="solid_waste_disposal_id" type="text" wire:model="form.solid_waste_disposal_id" class="sf-input" data-testid="solid-waste-disposal-id-input">
                    @if (isset($errors_['solid_waste_disposal_id']))
                        <span class="sf-field__error">{{ $errors_['solid_waste_disposal_id'] }}</span>
                    @endif
                </div>

                <div class="sf-field">
                    <label class="sf-field__label" for="date">Tanggal <span class="sf-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="sf-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="sf-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>

                <div class="sf-field sf-field--full">
                    <label class="sf-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="sf-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="sf-section sf-section--wide">
                <h4 class="sf-section__title">Log Kejadian Solid Waste Disposal</h4>

                @if ($detailError)
                    <div class="sf-alert sf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <span class="sf-field__hint">Tambahkan satu baris per kejadian pembuangan/pengiriman limbah padat — jumlah baris tidak dibatasi.</span>

                <div class="sf-table-wrap">
                    <table class="sf-detail-table" data-testid="solid-waste-disposal-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Weighbridge Ticket No</th>
                                <th>Vehicle No</th>
                                <th>Driver Name</th>
                                <th>Solid Waste Type</th>
                                <th>Source Station</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>Disposal/Utilization Site</th>
                                <th>Purpose/End Use</th>
                                <th>Gate Pass No</th>
                                <th>Security Seal No</th>
                                <th>Operator ID</th>
                                <th>Remarks</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="detail-row-{{ $index }}">
                                    <td><input type="date" wire:model="detailRows.{{ $index }}.event_date" class="sf-input" data-testid="detail-event-date-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.shift" class="sf-input" data-testid="detail-shift-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.weighbridge_ticket_no" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.vehicle_no" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.driver_name" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.solid_waste_type" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.source_station" class="sf-input"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.gross_weight_mt" class="sf-input" data-testid="detail-gross-weight-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.tare_weight_mt" class="sf-input" data-testid="detail-tare-weight-{{ $index }}"></td>
                                    <td><input type="text" value="{{ $this->rowNetWeight($index) ?? '-' }}" class="sf-input" data-testid="detail-net-weight-{{ $index }}" disabled></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.disposal_utilization_site" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.purpose_end_use" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.gate_pass_no" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.security_seal_no" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.operator_id" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.remarks" class="sf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.findings" class="sf-input"></td>
                                    <td><button type="button" wire:click="removeDetailRow({{ $index }})" class="sf-button sf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="sf-button sf-button--secondary" data-testid="add-row-button">+ Tambah Baris</button>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="sf-section">
                    <h4 class="sf-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="sf-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="sf-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="sf-actions">
                <button type="submit" class="sf-button sf-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .sf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1280px; }
        .sf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .sf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .sf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .sf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .sf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .sf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .sf-button:disabled { opacity: 0.5; cursor: not-allowed; }
        .sf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .sf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .sf-form { display: flex; flex-direction: column; gap: 20px; }
        .sf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .sf-section--wide { display: flex; flex-direction: column; gap: 12px; }
        .sf-section > *:not(.sf-field) { grid-column: 1 / -1; }
        .sf-field--full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .sf-section { grid-template-columns: 1fr; } }
        .sf-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .sf-field { display: flex; flex-direction: column; gap: 4px; }
        .sf-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .sf-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .sf-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .sf-field__error { font-size: 12px; color: #b91c1c; }
        .sf-required { color: #b91c1c; }
        .sf-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
        .sf-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .sf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .sf-actions { display: flex; justify-content: flex-end; }
        .sf-table-wrap { width: 100%; overflow-x: auto; }
        .sf-detail-table { border-collapse: collapse; min-width: 1600px; }
        .sf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .sf-detail-table td { padding: 6px 8px; vertical-align: top; }
        .sf-detail-table .sf-input { min-width: 110px; }
    </style>
</div>
