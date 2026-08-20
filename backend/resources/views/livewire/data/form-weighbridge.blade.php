<div class="fw-page">
    <div class="fw-page__header">
        <div>
            <h2 class="fw-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Weighbridge</h2>
            <p class="fw-page__subtitle">{{ $isEdit ? 'Ubah record Weighbridge tersimpan.' : 'Buat record Weighbridge baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.weighbridge.detail', ['id' => $id]) : route('data.weighbridge') }}" class="fw-button fw-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="fw-alert fw-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="fw-alert fw-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="fw-form">
            <div class="fw-section">
                <h4 class="fw-section__title">Identitas Weighbridge</h4>

                @if (! $isEdit)
                    <div class="fw-field">
                        <label class="fw-field__label" for="business_unit_id">Business Unit <span class="fw-required">*</span></label>
                        <select id="business_unit_id" wire:model="form.business_unit_id" class="fw-input" data-testid="business-unit-select">
                            <option value="">Pilih Business Unit</option>
                            @foreach ($businessUnitOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['business_unit_id']))
                            <span class="fw-field__error">{{ $errors_['business_unit_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="fw-field">
                        <span class="fw-field__label">Business Unit</span>
                        <span class="fw-field__readonly" data-testid="business-unit-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="fw-field">
                    <span class="fw-field__label">Tipe Weighbridge <span class="fw-required">*</span></span>
                    <div class="fw-tabs" role="tablist" data-testid="weighbridge-type-tabs">
                        <button type="button" wire:click="$set('form.weighbridge_type', 'receive')" class="fw-tab {{ $form['weighbridge_type'] === 'receive' ? 'fw-tab--active' : '' }}" data-testid="type-tab-receive">Receive</button>
                        <button type="button" wire:click="$set('form.weighbridge_type', 'dispatch')" class="fw-tab {{ $form['weighbridge_type'] === 'dispatch' ? 'fw-tab--active' : '' }}" data-testid="type-tab-dispatch">Dispatch</button>
                    </div>
                </div>

                <div class="fw-field">
                    <label class="fw-field__label" for="wb_card_number">WB Card Number <span class="fw-required">*</span></label>
                    <input id="wb_card_number" type="text" wire:model="form.wb_card_number" class="fw-input" data-testid="wb-card-number-input">
                    @if (isset($errors_['wb_card_number']))
                        <span class="fw-field__error">{{ $errors_['wb_card_number'] }}</span>
                    @endif
                </div>

                <div class="fw-field">
                    <label class="fw-field__label" for="record_datetime">{{ $form['weighbridge_type'] === 'dispatch' ? 'Tanggal & Waktu Dispatch' : 'Tanggal & Waktu Arrival' }} <span class="fw-required">*</span></label>
                    <input id="record_datetime" type="datetime-local" wire:model="form.record_datetime" class="fw-input" data-testid="record-datetime-input">
                    @if (isset($errors_['record_datetime']))
                        <span class="fw-field__error">{{ $errors_['record_datetime'] }}</span>
                    @endif
                </div>

                @if ($form['weighbridge_type'] === 'dispatch')
                    <div class="fw-field">
                        <label class="fw-field__label" for="destination">Tujuan Muatan <span class="fw-required">*</span></label>
                        <input id="destination" type="text" wire:model="form.destination" class="fw-input" data-testid="destination-input">
                        @if (isset($errors_['destination']))
                            <span class="fw-field__error">{{ $errors_['destination'] }}</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="fw-section">
                <h4 class="fw-section__title">Kendaraan &amp; Supir</h4>
                <div class="fw-field">
                    <label class="fw-field__label" for="vehicle_number">No. Kendaraan <span class="fw-required">*</span></label>
                    <input id="vehicle_number" type="text" wire:model="form.vehicle_number" class="fw-input" data-testid="vehicle-number-input">
                    @if (isset($errors_['vehicle_number']))
                        <span class="fw-field__error">{{ $errors_['vehicle_number'] }}</span>
                    @endif
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="driver_name">Nama Supir <span class="fw-required">*</span></label>
                    <input id="driver_name" type="text" wire:model="form.driver_name" class="fw-input" data-testid="driver-name-input">
                    @if (isset($errors_['driver_name']))
                        <span class="fw-field__error">{{ $errors_['driver_name'] }}</span>
                    @endif
                </div>
            </div>

            <div class="fw-section">
                <h4 class="fw-section__title">Asal Muatan</h4>
                <div class="fw-field">
                    <label class="fw-field__label" for="estate_supplier">Estate/Supplier <span class="fw-required">*</span></label>
                    <input id="estate_supplier" type="text" wire:model="form.estate_supplier" class="fw-input" data-testid="estate-supplier-input">
                    @if (isset($errors_['estate_supplier']))
                        <span class="fw-field__error">{{ $errors_['estate_supplier'] }}</span>
                    @endif
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="division">Divisi</label>
                    <input id="division" type="text" wire:model="form.division" class="fw-input" data-testid="division-input">
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="block">Blok</label>
                    <input id="block" type="text" wire:model="form.block" class="fw-input" data-testid="block-input">
                </div>
            </div>

            <div class="fw-section">
                <h4 class="fw-section__title">Data Timbangan</h4>
                <div class="fw-field">
                    <label class="fw-field__label" for="gross_weight">Gross Weight (kg) <span class="fw-required">*</span></label>
                    <input id="gross_weight" type="number" step="any" wire:model.live="form.gross_weight" class="fw-input" data-testid="gross-weight-input">
                    @if (isset($errors_['gross_weight']))
                        <span class="fw-field__error">{{ $errors_['gross_weight'] }}</span>
                    @endif
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="tare_weight">Tare Weight (kg)</label>
                    <input id="tare_weight" type="number" step="any" wire:model.live="form.tare_weight" class="fw-input" data-testid="tare-weight-input">
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="net_weight_preview">Net Weight (kg)</label>
                    <input id="net_weight_preview" type="text" value="{{ is_numeric($form['gross_weight']) && is_numeric($form['tare_weight']) ? $form['gross_weight'] - $form['tare_weight'] : '' }}" class="fw-input" data-testid="net-weight-preview" disabled>
                    <span class="fw-field__hint">Dihitung otomatis dari Gross &minus; Tare, tidak dapat diubah manual.</span>
                </div>
                <div class="fw-field">
                    <label class="fw-field__label" for="quantity">Kuantitas (tandan)</label>
                    <input id="quantity" type="number" step="any" wire:model="form.quantity" class="fw-input" data-testid="quantity-input">
                </div>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="fw-section">
                    <h4 class="fw-section__title">Verifikasi</h4>
                    @if ($this->isSupervisor())
                        <label class="fw-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif
                    @if ($this->isMillManagement())
                        <label class="fw-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="fw-actions">
                <button type="submit" class="fw-button fw-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .fw-page { display: flex; flex-direction: column; gap: 20px; max-width: 720px; }
        .fw-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .fw-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .fw-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .fw-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .fw-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .fw-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .fw-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .fw-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .fw-form { display: flex; flex-direction: column; gap: 20px; }
        .fw-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
        .fw-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .fw-field { display: flex; flex-direction: column; gap: 4px; }
        .fw-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .fw-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .fw-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .fw-field__error { font-size: 12px; color: #b91c1c; }
        .fw-required { color: #b91c1c; }
        .fw-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; }
        .fw-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .fw-tabs { display: flex; gap: 8px; }
        .fw-tab { padding: 8px 20px; border-radius: var(--radius-input, 6px); border: 1px solid var(--color-border, #d1d5db); background: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .fw-tab--active { background: var(--color-brand, #249360); color: #fff; border-color: var(--color-brand, #249360); }
        .fw-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .fw-actions { display: flex; justify-content: flex-end; }
    </style>
</div>
