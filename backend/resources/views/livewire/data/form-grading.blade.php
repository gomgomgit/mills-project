<div class="fg-page">
    <div class="fg-page__header">
        <div>
            <h2 class="fg-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Grading</h2>
            <p class="fg-page__subtitle">{{ $isEdit ? 'Ubah record Grading tersimpan.' : 'Buat record Grading baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.grading.detail', ['id' => $id]) : route('data.grading') }}" class="fg-button fg-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="fg-alert fg-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="fg-alert fg-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="fg-form">
            <div class="fg-section">
                <h4 class="fg-section__title">Identitas Grading</h4>

                @if (! $isEdit)
                    <div class="fg-field">
                        <label class="fg-field__label" for="business_unit_id">Business Unit <span class="fg-required">*</span></label>
                        <select id="business_unit_id" wire:model.live="form.business_unit_id" class="fg-input" data-testid="business-unit-select">
                            <option value="">Pilih Business Unit</option>
                            @foreach ($businessUnitOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['business_unit_id']))
                            <span class="fg-field__error">{{ $errors_['business_unit_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="fg-field">
                        <span class="fg-field__label">Business Unit</span>
                        <span class="fg-field__readonly" data-testid="business-unit-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="fg-field">
                    <label class="fg-field__label" for="grading_number">Grading Number <span class="fg-required">*</span></label>
                    <input id="grading_number" type="text" wire:model="form.grading_number" class="fg-input" data-testid="grading-number-input">
                    @if (isset($errors_['grading_number']))
                        <span class="fg-field__error">{{ $errors_['grading_number'] }}</span>
                    @endif
                </div>

                <div class="fg-field">
                    <label class="fg-field__label" for="date">Tanggal <span class="fg-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="fg-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="fg-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>
            </div>

            <div class="fg-section">
                <h4 class="fg-section__title">Referensi Weighbridge &amp; Kendaraan</h4>
                <div class="fg-field">
                    <label class="fg-field__label" for="weighbridge_record_id">WB Card No <span class="fg-required">*</span></label>
                    <select id="weighbridge_record_id" wire:model.live="form.weighbridge_record_id" class="fg-input" data-testid="wb-card-no-select">
                        <option value="">Pilih WB Card No</option>
                        @foreach ($weighbridgeOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['wb_card_number'] }}</option>
                        @endforeach
                    </select>
                    @if (isset($errors_['weighbridge_record_id']))
                        <span class="fg-field__error">{{ $errors_['weighbridge_record_id'] }}</span>
                    @endif
                    @if (empty($weighbridgeOptions))
                        <span class="fg-field__hint" data-testid="wb-empty-hint">Belum ada data Weighbridge untuk Business Unit ini.</span>
                    @endif
                </div>
                <div class="fg-field">
                    <label class="fg-field__label" for="license_plate_no">License Plate No <span class="fg-required">*</span></label>
                    <input id="license_plate_no" type="text" wire:model="form.license_plate_no" class="fg-input" data-testid="license-plate-no-input">
                    @if (isset($errors_['license_plate_no']))
                        <span class="fg-field__error">{{ $errors_['license_plate_no'] }}</span>
                    @endif
                </div>
                <div class="fg-field">
                    <label class="fg-field__label" for="vehicle_code">Vehicle Code</label>
                    <input id="vehicle_code" type="text" wire:model="form.vehicle_code" class="fg-input" data-testid="vehicle-code-input">
                </div>
            </div>

            <div class="fg-section">
                <h4 class="fg-section__title">Asal Muatan</h4>
                <div class="fg-field">
                    <label class="fg-field__label" for="estate_supplier">Estate/Supplier <span class="fg-required">*</span></label>
                    <input id="estate_supplier" type="text" wire:model="form.estate_supplier" class="fg-input" data-testid="estate-supplier-input">
                    @if (isset($errors_['estate_supplier']))
                        <span class="fg-field__error">{{ $errors_['estate_supplier'] }}</span>
                    @endif
                </div>
                <div class="fg-field">
                    <label class="fg-field__label" for="division">Divisi</label>
                    <input id="division" type="text" wire:model="form.division" class="fg-input" data-testid="division-input">
                </div>
            </div>

            <div class="fg-section">
                <h4 class="fg-section__title">Data Berat &amp; Kuantitas</h4>
                <div class="fg-field">
                    <label class="fg-field__label" for="netto">Netto (kg) <span class="fg-required">*</span></label>
                    <input id="netto" type="number" step="any" wire:model.live="form.netto" class="fg-input" data-testid="netto-input">
                    @if (isset($errors_['netto']))
                        <span class="fg-field__error">{{ $errors_['netto'] }}</span>
                    @endif
                </div>
                <div class="fg-field">
                    <label class="fg-field__label" for="quantity">Quantity (bunch) <span class="fg-required">*</span></label>
                    <input id="quantity" type="number" step="any" wire:model.live="form.quantity" class="fg-input" data-testid="quantity-input">
                    @if (isset($errors_['quantity']))
                        <span class="fg-field__error">{{ $errors_['quantity'] }}</span>
                    @endif
                </div>
                <div class="fg-field fg-field--full">
                    <label class="fg-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="fg-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="fg-section">
                <h4 class="fg-section__title">Grading Detail</h4>

                @if ($detailError)
                    <div class="fg-alert fg-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <table class="fg-detail-table" data-testid="grading-detail-grid">
                    <thead>
                        <tr>
                            <th>Quality Parameter</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Percentage</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detailRows as $index => $row)
                            <tr data-testid="grading-detail-row-{{ $index }}">
                                <td>
                                    <select wire:model.live="detailRows.{{ $index }}.grading_parameter_id" class="fg-input" data-testid="detail-parameter-select-{{ $index }}">
                                        <option value="">Pilih Quality Parameter</option>
                                        @foreach ($this->availableParameterOptions($index) as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="any" wire:model.live="detailRows.{{ $index }}.quantity" class="fg-input" data-testid="detail-quantity-input-{{ $index }}">
                                </td>
                                <td>
                                    <input type="text" value="{{ $this->rowUom($index) }}" class="fg-input" data-testid="detail-uom-{{ $index }}" disabled>
                                </td>
                                <td>
                                    <input type="text" value="{{ $this->rowPercentage($index) }}" class="fg-input" data-testid="detail-percentage-{{ $index }}" disabled>
                                </td>
                                <td>
                                    <button type="button" wire:click="removeDetailRow({{ $index }})" class="fg-button fg-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="button" wire:click="addDetailRow" class="fg-button fg-button--secondary" data-testid="add-row-button">+ Tambah Baris</button>
            </div>

            @if ($this->isMillManagement())
                <div class="fg-section">
                    <h4 class="fg-section__title">Verifikasi</h4>
                    <label class="fg-checkbox">
                        <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                        Tandai sudah dikonfirmasi (Acknowledged)
                    </label>
                </div>
            @endif

            <div class="fg-actions">
                <button type="submit" class="fg-button fg-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .fg-page { display: flex; flex-direction: column; gap: 20px; max-width: 1040px; }
        .fg-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .fg-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .fg-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .fg-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .fg-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .fg-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .fg-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .fg-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .fg-form { display: flex; flex-direction: column; gap: 20px; }
        .fg-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .fg-section > *:not(.fg-field) { grid-column: 1 / -1; }
        .fg-field--full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .fg-section { grid-template-columns: 1fr; } }
        .fg-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .fg-field { display: flex; flex-direction: column; gap: 4px; }
        .fg-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .fg-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .fg-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .fg-field__error { font-size: 12px; color: #b91c1c; }
        .fg-required { color: #b91c1c; }
        .fg-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; }
        .fg-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .fg-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .fg-actions { display: flex; justify-content: flex-end; }
        .fg-detail-table { width: 100%; border-collapse: collapse; }
        .fg-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); }
        .fg-detail-table td { padding: 6px 8px; vertical-align: top; }
    </style>
</div>
