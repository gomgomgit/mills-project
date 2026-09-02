<div class="cf-page">
    <div class="cf-page__header">
        <div>
            <h2 class="cf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data CPO Dispatch</h2>
            <p class="cf-page__subtitle">{{ $isEdit ? 'Ubah record CPO Dispatch tersimpan.' : 'Buat record CPO Dispatch baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.cpo-dispatch.detail', ['id' => $id]) : route('data.cpo-dispatch') }}" class="cf-button cf-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="cf-alert cf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="cf-alert cf-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="cf-form">
            <div class="cf-section">
                <h4 class="cf-section__title">Identitas CPO Dispatch</h4>

                @if (! $isEdit)
                    <div class="cf-field">
                        <label class="cf-field__label" for="production_line_id">Production Line <span class="cf-required">*</span></label>
                        <select id="production_line_id" wire:model="form.production_line_id" class="cf-input" data-testid="production-line-select">
                            <option value="">Pilih Production Line</option>
                            @foreach ($productionLineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['production_line_id']))
                            <span class="cf-field__error">{{ $errors_['production_line_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="cf-field">
                        <span class="cf-field__label">Station</span>
                        <span class="cf-field__readonly" data-testid="station-readonly">{{ $stationName ?? '-' }}</span>
                    </div>
                @endif

                <div class="cf-field">
                    <label class="cf-field__label" for="cpo_dispatch_id">CPO Dispatch ID <span class="cf-required">*</span></label>
                    <input id="cpo_dispatch_id" type="text" wire:model="form.cpo_dispatch_id" class="cf-input" data-testid="cpo-dispatch-id-input">
                    @if (isset($errors_['cpo_dispatch_id']))
                        <span class="cf-field__error">{{ $errors_['cpo_dispatch_id'] }}</span>
                    @endif
                </div>

                <div class="cf-field">
                    <label class="cf-field__label" for="date">Tanggal <span class="cf-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="cf-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="cf-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>

                <div class="cf-field cf-field--full">
                    <label class="cf-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="cf-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="cf-section cf-section--wide">
                <h4 class="cf-section__title">Log Kejadian CPO Dispatch</h4>

                @if ($detailError)
                    <div class="cf-alert cf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <span class="cf-field__hint">Tambahkan satu baris per kejadian pengiriman CPO &mdash; jumlah baris tidak dibatasi.</span>

                <div class="cf-table-wrap">
                    <table class="cf-detail-table" data-testid="cpo-dispatch-detail-log">
                        <thead>
                            <tr>
                                <th>Tanggal Kejadian</th>
                                <th>Shift</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Waybill Number</th>
                                <th>Tanker Plate No</th>
                                <th>Transport Company</th>
                                <th>Driver Name</th>
                                <th>Storage Tank Source</th>
                                <th>Seal No (Top)</th>
                                <th>Seal No (Bottom)</th>
                                <th>Gross Weight (MT)</th>
                                <th>Tare Weight (MT)</th>
                                <th>Net Weight (MT)</th>
                                <th>FFA (%)</th>
                                <th>Moisture (%)</th>
                                <th>Impurities (%)</th>
                                <th>DOBI</th>
                                <th>Destination/Buyer</th>
                                <th>Weighbridge Operator</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="detail-row-{{ $index }}">
                                    <td><input type="date" wire:model="detailRows.{{ $index }}.event_date" class="cf-input" data-testid="detail-event-date-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.shift" class="cf-input" data-testid="detail-shift-{{ $index }}"></td>
                                    <td><input type="time" wire:model="detailRows.{{ $index }}.time_in" class="cf-input" data-testid="detail-time-in-{{ $index }}"></td>
                                    <td><input type="time" wire:model="detailRows.{{ $index }}.time_out" class="cf-input" data-testid="detail-time-out-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.waybill_number" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.tanker_plate_no" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.transport_company" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.driver_name" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.storage_tank_source" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.seal_no_top" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.seal_no_bottom" class="cf-input"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.gross_weight_mt" class="cf-input" data-testid="detail-gross-weight-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model.live="detailRows.{{ $index }}.tare_weight_mt" class="cf-input" data-testid="detail-tare-weight-{{ $index }}"></td>
                                    <td><input type="text" value="{{ $this->rowNetWeight($index) ?? '-' }}" class="cf-input" data-testid="detail-net-weight-{{ $index }}" disabled></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.ffa_percent" class="cf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.moisture_percent" class="cf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.impurities_percent" class="cf-input"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.dobi" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.destination_buyer" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.weighbridge_operator" class="cf-input"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.findings" class="cf-input"></td>
                                    <td><button type="button" wire:click="removeDetailRow({{ $index }})" class="cf-button cf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="cf-button cf-button--secondary" data-testid="add-row-button">+ Tambah Baris</button>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="cf-section">
                    <h4 class="cf-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="cf-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="cf-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="cf-actions">
                <button type="submit" class="cf-button cf-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .cf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1280px; }
        .cf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .cf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .cf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .cf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .cf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .cf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .cf-button:disabled { opacity: 0.5; cursor: not-allowed; }
        .cf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .cf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .cf-form { display: flex; flex-direction: column; gap: 20px; }
        .cf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .cf-section--wide { display: flex; flex-direction: column; gap: 12px; }
        .cf-section > *:not(.cf-field) { grid-column: 1 / -1; }
        .cf-field--full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .cf-section { grid-template-columns: 1fr; } }
        .cf-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .cf-field { display: flex; flex-direction: column; gap: 4px; }
        .cf-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .cf-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .cf-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .cf-field__error { font-size: 12px; color: #b91c1c; }
        .cf-required { color: #b91c1c; }
        .cf-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
        .cf-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .cf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .cf-actions { display: flex; justify-content: flex-end; }
        .cf-table-wrap { width: 100%; overflow-x: auto; }
        .cf-detail-table { border-collapse: collapse; min-width: 2000px; }
        .cf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .cf-detail-table td { padding: 6px 8px; vertical-align: top; }
        .cf-detail-table .cf-input { min-width: 110px; }
    </style>
</div>
