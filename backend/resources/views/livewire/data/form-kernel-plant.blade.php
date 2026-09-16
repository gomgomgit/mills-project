<div class="kf-page">
    <div class="kf-page__header">
        <div>
            <h2 class="kf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Kernel Plant</h2>
            <p class="kf-page__subtitle">{{ $isEdit ? 'Ubah record Kernel Plant tersimpan.' : 'Buat record Kernel Plant baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.kernel-plant.detail', ['id' => $id]) : route('data.kernel-plant') }}" class="kf-button kf-button--secondary" data-testid="cancel-button">Batal</a>
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
                <h4 class="kf-section__title">Identitas Kernel Plant</h4>

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
                        <span class="kf-field__readonly" data-testid="station-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="kf-field">
                    <label class="kf-field__label" for="kernel_plant_id">Kernel Plant ID <span class="kf-required">*</span></label>
                    <input id="kernel_plant_id" type="text" wire:model="form.kernel_plant_id" class="kf-input" data-testid="kernel-plant-id-input">
                    @if (isset($errors_['kernel_plant_id']))
                        <span class="kf-field__error">{{ $errors_['kernel_plant_id'] }}</span>
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

            <div class="kf-section kf-section--block">
                <h4 class="kf-section__title">Kernel Plant Detail</h4>
                <span class="kf-field__hint">Tambahkan baris satu per satu via "Tambah Baris", pilih Time-Slot untuk tiap baris (urutan menaik, tanpa duplikat) — sama seperti Depricarping Detail.</span>

                @if ($detailError)
                    <div class="kf-alert kf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <div class="kf-table-wrap">
                    <table class="kf-detail-table" data-testid="kernel-plant-detail-grid">
                        <thead>
                            <tr>
                                <th>Time-Slot</th>
                                <th>Ripple Mill 1 (Amps)</th>
                                <th>Ripple Mill 2 (Amps)</th>
                                <th>Claybath/Hydro SG</th>
                                <th>Kernel Silo 1 Temp (°C)</th>
                                <th>Kernel Silo 2 Temp (°C)</th>
                                <th>Kernel Moisture (%)</th>
                                <th>Shell Loss (%)</th>
                                <th>Downtime (Mins)</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="kernel-plant-detail-row-{{ $index }}">
                                    <td>
                                        <select wire:model.live="detailRows.{{ $index }}.time_slot" class="kf-input" data-testid="time-slot-select-{{ $index }}">
                                            <option value="">Pilih Time-Slot</option>
                                            @foreach ($this->availableTimeSlotOptions($index) as $slot)
                                                <option value="{{ $slot }}">{{ $slot }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.ripple_mill_1_amps" class="kf-input" data-testid="ripple-mill-1-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.ripple_mill_2_amps" class="kf-input" data-testid="ripple-mill-2-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.claybath_hydro_sg" class="kf-input" data-testid="claybath-hydro-sg-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.kernel_silo_1_temp_c" class="kf-input" data-testid="kernel-silo-1-temp-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.kernel_silo_2_temp_c" class="kf-input" data-testid="kernel-silo-2-temp-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.kernel_moisture_percent" class="kf-input" data-testid="kernel-moisture-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.shell_loss_percent" class="kf-input" data-testid="shell-loss-{{ $index }}"></td>
                                    <td><input type="number" step="1" wire:model="detailRows.{{ $index }}.downtime_minutes" class="kf-input" data-testid="downtime-minutes-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.findings" class="kf-input" data-testid="findings-{{ $index }}"></td>
                                    <td>
                                        <button type="button" wire:click="removeDetailRow({{ $index }})" class="kf-button kf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="kf-button kf-button--secondary" data-testid="add-row-button" @disabled(! $this->canAddRow())>+ Tambah Baris</button>
            </div>

            <div class="kf-section kf-section--block">
                <h4 class="kf-section__title">Target Operasional</h4>
                <span class="kf-field__hint">Referensi baku mutu operasional — tidak termasuk data yang disimpan.</span>
                <table class="kf-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Equipment / Parameter</th>
                            <th>Target Benchmark</th>
                            <th>Corrective Action Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->equipment_parameter }}</td>
                                <td>{{ $target->target_benchmark }}</td>
                                <td>{{ $target->corrective_action_plan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
        .kf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1200px; }
        .kf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .kf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .kf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .kf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .kf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .kf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .kf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .kf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .kf-form { display: flex; flex-direction: column; gap: 20px; }
        .kf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .kf-section--block { display: flex; flex-direction: column; gap: 12px; }
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
        .kf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .kf-actions { display: flex; justify-content: flex-end; }
        .kf-table-wrap { width: 100%; overflow-x: auto; }
        .kf-detail-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .kf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .kf-detail-table td { padding: 4px 6px; vertical-align: middle; }
        .kf-time-slot-cell { font-weight: 700; color: var(--color-brand, #249360); white-space: nowrap; }
        .kf-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .kf-target-table th, .kf-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .kf-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .kf-target-table th:first-child, .kf-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
