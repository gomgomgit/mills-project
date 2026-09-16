<div class="pf-page">
    <div class="pf-page__header">
        <div>
            <h2 class="pf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Clarification</h2>
            <p class="pf-page__subtitle">{{ $isEdit ? 'Ubah record Clarification tersimpan.' : 'Buat record Clarification baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.clarification.detail', ['id' => $id]) : route('data.clarification') }}" class="pf-button pf-button--secondary" data-testid="cancel-button">Batal</a>
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
                <h4 class="pf-section__title">Identitas Clarification</h4>

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
                    <label class="pf-field__label" for="clarification_id">Clarification ID <span class="pf-required">*</span></label>
                    <input id="clarification_id" type="text" wire:model="form.clarification_id" class="pf-input" data-testid="clarification-id-input">
                    @if (isset($errors_['clarification_id']))
                        <span class="pf-field__error">{{ $errors_['clarification_id'] }}</span>
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
                <h4 class="pf-section__title">Clarification Detail</h4>
                <span class="pf-field__hint">Tambahkan baris satu per satu via "Tambah Baris", pilih Time-Slot untuk tiap baris (urutan menaik, tanpa duplikat) — sama seperti Form Boiler Room.</span>

                @if ($detailError)
                    <div class="pf-alert pf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <div class="pf-table-wrap">
                    <table class="pf-detail-table" data-testid="clarification-detail-grid">
                        <thead>
                            <tr>
                                <th>Time-Slot</th>
                                <th>Clarification Tank Temp (&deg;C)</th>
                                <th>Oil Tank Temperature (&deg;C)</th>
                                <th>Sludge Tank Temp (&deg;C)</th>
                                <th>Buffer Tank Level (%)</th>
                                <th>Pure Oil Production Rate (Ton/Hour)</th>
                                <th>Downtime (Mins)</th>
                                <th>Findings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="clarification-detail-row-{{ $index }}">
                                    <td>
                                        <select wire:model.live="detailRows.{{ $index }}.time_slot" class="pf-input" data-testid="time-slot-select-{{ $index }}">
                                            <option value="">Pilih Time-Slot</option>
                                            @foreach ($this->availableTimeSlotOptions($index) as $slot)
                                                <option value="{{ $slot }}">{{ $slot }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.clarification_tank_temp_c" class="pf-input" data-testid="clarification-tank-temp-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.oil_tank_temperature_c" class="pf-input" data-testid="oil-tank-temperature-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.sludge_tank_temp_c" class="pf-input" data-testid="sludge-tank-temp-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.buffer_tank_level_percent" class="pf-input" data-testid="buffer-tank-level-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.pure_oil_production_rate_ton_hour" class="pf-input" data-testid="pure-oil-production-rate-{{ $index }}"></td>
                                <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.downtime_mins" class="pf-input" data-testid="downtime-mins-{{ $index }}"></td>
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
        .pf-detail-table { width: 100%; border-collapse: collapse; min-width: 1300px; }
        .pf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .pf-detail-table td { padding: 4px 6px; vertical-align: middle; }
    </style>
</div>
