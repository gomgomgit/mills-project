<div class="pf-page">
    <div class="pf-page__header">
        <div>
            <h2 class="pf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Engine Room</h2>
            <p class="pf-page__subtitle">{{ $isEdit ? 'Ubah record Engine Room tersimpan.' : 'Buat record Engine Room baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.engine-room.detail', ['id' => $id]) : route('data.engine-room') }}" class="pf-button pf-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="pf-alert pf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="pf-alert pf-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="pf-form">
            <div class="pf-section">
                <h4 class="pf-section__title">Identitas Engine Room</h4>

                @if (! $isEdit)
                    <div class="pf-field">
                        <label class="pf-field__label" for="production_line_id">Production Line <span class="pf-required">*</span></label>
                        <select id="production_line_id" wire:model="form.production_line_id" class="pf-input" data-testid="production-line-select">
                            <option value="">Pilih Production Line</option>
                            @foreach ($productionLineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['production_line_id']))
                            <span class="pf-field__error">{{ $errors_['production_line_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="pf-field">
                        <span class="pf-field__label">Station</span>
                        <span class="pf-field__readonly" data-testid="station-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="pf-field">
                    <label class="pf-field__label" for="engine_room_id">Engine Room ID <span class="pf-required">*</span></label>
                    <input id="engine_room_id" type="text" wire:model="form.engine_room_id" class="pf-input" data-testid="engine-room-id-input">
                    @if (isset($errors_['engine_room_id']))
                        <span class="pf-field__error">{{ $errors_['engine_room_id'] }}</span>
                    @endif
                </div>

                <div class="pf-field">
                    <label class="pf-field__label" for="date">Tanggal <span class="pf-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="pf-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="pf-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>

                <div class="pf-field pf-field--full">
                    <label class="pf-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="pf-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="pf-section pf-section--block">
                <h4 class="pf-section__title">Engine Room Detail</h4>
                <span class="pf-field__hint">Tambahkan baris satu per satu via "Tambah Baris", pilih Time-Slot untuk tiap baris (urutan menaik, tanpa duplikat) — sama seperti Form Storage Tank.</span>

                @if ($detailError)
                    <div class="pf-alert pf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <div class="pf-table-wrap">
                    <table class="pf-detail-table" data-testid="engine-room-detail-grid">
                        <thead>
                            <tr>
                                <th>Time-Slot</th>
                                <th>Steam Turbine Inlet Pressure (bar)</th>
                                <th>Steam Turbine Inlet Temp (°C)</th>
                                <th>Steam Turbine Exhaust Pressure (bar)</th>
                                <th>Steam Turbine RPM</th>
                                <th>Steam Turbine Alternator Bearing Temp 1 (°C)</th>
                                <th>Steam Turbine Alternator Bearing Temp 2 (°C)</th>
                                <th>Diesel Gen 1 Status</th>
                                <th>Diesel Gen 1 Load (kW)</th>
                                <th>Diesel Gen 1 Amperage (A)</th>
                                <th>Diesel Gen 1 Jacket Water Temp (°C)</th>
                                <th>Diesel Gen 1 Lube Oil Pressure (bar)</th>
                                <th>Diesel Gen 2 Status</th>
                                <th>Diesel Gen 2 Load (kW)</th>
                                <th>Diesel Gen 2 Amperage (A)</th>
                                <th>Diesel Gen 2 Jacket Water Temp (°C)</th>
                                <th>Diesel Gen 2 Lube Oil Pressure (bar)</th>
                                <th>Electrical Sync Total Factory Load (kW)</th>
                                <th>Electrical Sync System Frequency (Hz)</th>
                                <th>Electrical Sync Power Factor (PF)</th>
                                <th>Electrical Sync Busbar Voltage (V)</th>
                                <th>Air Compressor 1 Pressure (bar)</th>
                                <th>Compressor 2 Pressure (bar)</th>
                                <th>Battery Charger/UPS Voltage (V)</th>
                                <th>Fuel Tank Level (Liters/%)</th>
                                <th>Daily Energy Export (kWh)</th>
                                <th>Action Taken/Maintenance Remark</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="engine-room-detail-row-{{ $index }}">
                                    <td>
                                        <select wire:model.live="detailRows.{{ $index }}.time_slot" class="pf-input" data-testid="time-slot-select-{{ $index }}">
                                            <option value="">Pilih Time-Slot</option>
                                            @foreach ($this->availableTimeSlotOptions($index) as $slot)
                                                <option value="{{ $slot }}">{{ $slot }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_inlet_pressure_bar" class="pf-input" data-testid="steam-inlet-pressure-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_inlet_temp_c" class="pf-input" data-testid="steam-inlet-temp-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_exhaust_pressure_bar" class="pf-input" data-testid="steam-exhaust-pressure-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_rpm" class="pf-input" data-testid="steam-rpm-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_alternator_bearing_temp_1_c" class="pf-input" data-testid="steam-bearing-temp-1-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.steam_turbine_alternator_bearing_temp_2_c" class="pf-input" data-testid="steam-bearing-temp-2-{{ $index }}"></td>
                                <td>
                                    <select wire:model="detailRows.{{ $index }}.diesel_gen_1_status" class="pf-input" data-testid="diesel-gen-1-status-{{ $index }}">
                                        <option value="">-- Pilih --</option>
                                            <option value="run">Run</option>
                                            <option value="standby">Standby</option>
                                            <option value="off">Off</option>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_1_load_kw" class="pf-input" data-testid="diesel-gen-1-load-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_1_amperage_a" class="pf-input" data-testid="diesel-gen-1-amperage-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_1_jacket_water_temp_c" class="pf-input" data-testid="diesel-gen-1-jacket-temp-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_1_lube_oil_pressure_bar" class="pf-input" data-testid="diesel-gen-1-lube-oil-{{ $index }}"></td>
                                <td>
                                    <select wire:model="detailRows.{{ $index }}.diesel_gen_2_status" class="pf-input" data-testid="diesel-gen-2-status-{{ $index }}">
                                        <option value="">-- Pilih --</option>
                                            <option value="run">Run</option>
                                            <option value="standby">Standby</option>
                                            <option value="off">Off</option>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_2_load_kw" class="pf-input" data-testid="diesel-gen-2-load-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_2_amperage_a" class="pf-input" data-testid="diesel-gen-2-amperage-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_2_jacket_water_temp_c" class="pf-input" data-testid="diesel-gen-2-jacket-temp-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.diesel_gen_2_lube_oil_pressure_bar" class="pf-input" data-testid="diesel-gen-2-lube-oil-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.electrical_sync_total_factory_load_kw" class="pf-input" data-testid="sync-total-load-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.electrical_sync_system_frequency_hz" class="pf-input" data-testid="sync-frequency-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.electrical_sync_power_factor" class="pf-input" data-testid="sync-power-factor-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.electrical_sync_busbar_voltage_v" class="pf-input" data-testid="sync-busbar-voltage-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.air_compressor_1_pressure_bar" class="pf-input" data-testid="air-compressor-1-pressure-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.compressor_2_pressure_bar" class="pf-input" data-testid="compressor-2-pressure-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.battery_charger_ups_voltage_v" class="pf-input" data-testid="battery-charger-ups-voltage-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.fuel_tank_level" class="pf-input" data-testid="fuel-tank-level-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.daily_energy_export_kwh" class="pf-input" data-testid="daily-energy-export-{{ $index }}"></td>
                                <td><input type="text" wire:model="detailRows.{{ $index }}.action_taken_maintenance_remark" class="pf-input" data-testid="action-taken-remark-{{ $index }}"></td>
                                <td><input type="text" wire:model="detailRows.{{ $index }}.findings" class="pf-input" data-testid="findings-{{ $index }}"></td>
                                    <td>
                                        <button type="button" wire:click="removeDetailRow({{ $index }})" class="pf-button pf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="pf-button pf-button--secondary" data-testid="add-row-button" @disabled(! $this->canAddRow())>+ Tambah Baris</button>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="pf-section">
                    <h4 class="pf-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="pf-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="pf-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="pf-actions">
                <button type="submit" class="pf-button pf-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .pf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1400px; }
        .pf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .pf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .pf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .pf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .pf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .pf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .pf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .pf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .pf-form { display: flex; flex-direction: column; gap: 20px; }
        .pf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .pf-section--block { display: flex; flex-direction: column; gap: 12px; }
        .pf-section > *:not(.pf-field) { grid-column: 1 / -1; }
        .pf-field--full { grid-column: 1 / -1; }
        @media (max-width: 640px) { .pf-section { grid-template-columns: 1fr; } }
        .pf-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .pf-field { display: flex; flex-direction: column; gap: 4px; }
        .pf-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .pf-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .pf-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .pf-field__error { font-size: 12px; color: #b91c1c; }
        .pf-required { color: #b91c1c; }
        .pf-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
        .pf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .pf-actions { display: flex; justify-content: flex-end; }
        .pf-table-wrap { width: 100%; overflow-x: auto; }
        .pf-detail-table { width: 100%; border-collapse: collapse; min-width: 3200px; }
        .pf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .pf-detail-table td { padding: 4px 6px; vertical-align: middle; }
    </style>
</div>
