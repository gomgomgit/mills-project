<div class="pf-page">
    <div class="pf-page__header">
        <div>
            <h2 class="pf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Depricarping</h2>
            <p class="pf-page__subtitle">{{ $isEdit ? 'Ubah record Depricarping tersimpan.' : 'Buat record Depricarping baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.depricarping.detail', ['id' => $id]) : route('data.depricarping') }}" class="pf-button pf-button--secondary" data-testid="cancel-button">Batal</a>
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
                <h4 class="pf-section__title">Identitas Depricarping</h4>

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
                    <label class="pf-field__label" for="presser_id">Presser ID <span class="pf-required">*</span></label>
                    <input id="presser_id" type="text" wire:model="form.presser_id" class="pf-input" data-testid="presser-id-input">
                    @if (isset($errors_['presser_id']))
                        <span class="pf-field__error">{{ $errors_['presser_id'] }}</span>
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
                <h4 class="pf-section__title">Depricarping Detail</h4>
                <span class="pf-field__hint">Tambahkan baris satu per satu via "Tambah Baris", pilih Time-Slot untuk tiap baris (urutan menaik, tanpa duplikat) — sama seperti Pressing Detail.</span>

                @if ($detailError)
                    <div class="pf-alert pf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <div class="pf-table-wrap">
                    <table class="pf-detail-table" data-testid="depricarping-detail-grid">
                        <thead>
                            <tr>
                                <th>Time-Slot</th>
                                <th>Fan Static Pressure (mmH2O)</th>
                                <th>Polishing Drum Speed (RPM)</th>
                                <th>Air Velocity (m/s)</th>
                                <th>Fibre Moisture (%)</th>
                                <th>Kernel Recovery in Fibre (%)</th>
                                <th>Nut Silo 1 Temp (°C)</th>
                                <th>Nut Silo 2 Temp (°C)</th>
                                <th>Downtime (Mins)</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="depricarping-detail-row-{{ $index }}">
                                    <td>
                                        <select wire:model.live="detailRows.{{ $index }}.time_slot" class="pf-input" data-testid="time-slot-select-{{ $index }}">
                                            <option value="">Pilih Time-Slot</option>
                                            @foreach ($this->availableTimeSlotOptions($index) as $slot)
                                                <option value="{{ $slot }}">{{ $slot }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.fan_static_pressure_mmh2o" class="pf-input" data-testid="fan-static-pressure-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.polishing_drum_speed_rpm" class="pf-input" data-testid="polishing-drum-speed-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.air_velocity_ms" class="pf-input" data-testid="air-velocity-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.fibre_moisture_percent" class="pf-input" data-testid="fibre-moisture-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.kernel_recovery_in_fibre_percent" class="pf-input" data-testid="kernel-recovery-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.nut_silo_1_temp_c" class="pf-input" data-testid="nut-silo-1-temp-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.nut_silo_2_temp_c" class="pf-input" data-testid="nut-silo-2-temp-{{ $index }}"></td>
                                    <td><input type="number" step="1" wire:model="detailRows.{{ $index }}.downtime_minutes" class="pf-input" data-testid="downtime-minutes-{{ $index }}"></td>
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

            <div class="pf-section pf-section--block">
                <h4 class="pf-section__title">Target Operasional</h4>
                <span class="pf-field__hint">Referensi baku mutu operasional — tidak termasuk data yang disimpan.</span>
                <table class="pf-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Parameter/Metric</th>
                            <th>Target Range</th>
                            <th>Critical Limit</th>
                            <th>Operational Consequence / Justification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->parameter_metric }}</td>
                                <td>{{ $target->target_range }}</td>
                                <td>{{ $target->critical_limit }}</td>
                                <td>{{ $target->operational_consequence_justification }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
        .pf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1200px; }
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
        @media (max-width: 767px) { .pf-section { grid-template-columns: 1fr; } }
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
        .pf-detail-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .pf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .pf-detail-table td { padding: 4px 6px; vertical-align: middle; }
        .pf-time-slot-cell { font-weight: 700; color: var(--color-brand, #249360); white-space: nowrap; }
        .pf-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .pf-target-table th, .pf-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .pf-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .pf-target-table th:first-child, .pf-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
