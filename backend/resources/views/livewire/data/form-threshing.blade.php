<div class="tf-page">
    <div class="tf-page__header">
        <div>
            <h2 class="tf-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Threshing</h2>
            <p class="tf-page__subtitle">{{ $isEdit ? 'Ubah record Threshing tersimpan.' : 'Buat record Threshing baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.threshing.detail', ['id' => $id]) : route('data.threshing') }}" class="tf-button tf-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="tf-alert tf-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="tf-alert tf-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="tf-form">
            <div class="tf-section">
                <h4 class="tf-section__title">Identitas Threshing</h4>

                @if (! $isEdit)
                    <div class="tf-field">
                        <label class="tf-field__label" for="production_line_id">Production Line <span class="tf-required">*</span></label>
                        <select id="production_line_id" wire:model="form.production_line_id" class="tf-input" data-testid="production-line-select">
                            <option value="">Pilih Production Line</option>
                            @foreach ($productionLineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['production_line_id']))
                            <span class="tf-field__error">{{ $errors_['production_line_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="tf-field">
                        <span class="tf-field__label">Station</span>
                        <span class="tf-field__readonly" data-testid="station-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="tf-field">
                    <label class="tf-field__label" for="thresher_id">Thresher ID <span class="tf-required">*</span></label>
                    <input id="thresher_id" type="text" wire:model="form.thresher_id" class="tf-input" data-testid="thresher-id-input">
                    @if (isset($errors_['thresher_id']))
                        <span class="tf-field__error">{{ $errors_['thresher_id'] }}</span>
                    @endif
                </div>

                <div class="tf-field">
                    <label class="tf-field__label" for="date">Tanggal <span class="tf-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="tf-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="tf-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>

                <div class="tf-field tf-field--full">
                    <label class="tf-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="tf-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="tf-section tf-section--block">
                <h4 class="tf-section__title">Threshing Detail</h4>
                <span class="tf-field__hint">Tambahkan baris satu per satu via "Tambah Baris", pilih Time-Slot untuk tiap baris (urutan menaik, tanpa duplikat) — sama seperti Cages Tipped Time.</span>

                @if ($detailError)
                    <div class="tf-alert tf-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                <div class="tf-table-wrap">
                    <table class="tf-detail-table" data-testid="threshing-detail-grid">
                        <thead>
                            <tr>
                                <th>Time-Slot</th>
                                <th>FFB Throughput (MT/h)</th>
                                <th>Drum Speed (RPM)</th>
                                <th>Motor Current (A)</th>
                                <th>Unstripped Bunch (%)</th>
                                <th>Oil Loss (%)</th>
                                <th>Downtime Reason</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr data-testid="threshing-detail-row-{{ $index }}">
                                    <td>
                                        <select wire:model.live="detailRows.{{ $index }}.time_slot" class="tf-input" data-testid="time-slot-select-{{ $index }}">
                                            <option value="">Pilih Time-Slot</option>
                                            @foreach ($this->availableTimeSlotOptions($index) as $slot)
                                                <option value="{{ $slot }}">{{ $slot }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.ffb_throughput_mt_hour" class="tf-input" data-testid="ffb-throughput-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.thresher_drum_speed_rpm" class="tf-input" data-testid="drum-speed-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.motor_current_amps" class="tf-input" data-testid="motor-current-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.unstripped_bunch_count_percent" class="tf-input" data-testid="unstripped-bunch-{{ $index }}"></td>
                                    <td><input type="number" step="0.01" wire:model="detailRows.{{ $index }}.empty_bunch_oil_loss_percent" class="tf-input" data-testid="oil-loss-{{ $index }}"></td>
                                    <td><input type="text" wire:model="detailRows.{{ $index }}.downtime_reason" class="tf-input" data-testid="downtime-reason-{{ $index }}"></td>
                                    <td>
                                        <button type="button" wire:click="removeDetailRow({{ $index }})" class="tf-button tf-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="addDetailRow" class="tf-button tf-button--secondary" data-testid="add-row-button" @disabled(! $this->canAddRow())>+ Tambah Baris</button>
            </div>

            <div class="tf-section tf-section--block">
                <h4 class="tf-section__title">Target Operasional</h4>
                <span class="tf-field__hint">Referensi baku mutu operasional — tidak termasuk data yang disimpan.</span>
                <table class="tf-target-table" data-testid="operational-target-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Standard Operational Target</th>
                            <th>Action Plan on Deviation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($operationalTargets as $target)
                            <tr>
                                <td>{{ $target->parameter }}</td>
                                <td>{{ $target->standard_operational_target }}</td>
                                <td>{{ $target->action_plan_on_deviation }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="tf-section">
                    <h4 class="tf-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="tf-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="tf-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="tf-actions">
                <button type="submit" class="tf-button tf-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .tf-page { display: flex; flex-direction: column; gap: 20px; max-width: 1200px; }
        .tf-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .tf-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .tf-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .tf-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .tf-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .tf-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .tf-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .tf-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .tf-form { display: flex; flex-direction: column; gap: 20px; }
        .tf-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .tf-section--block { display: flex; flex-direction: column; gap: 12px; }
        .tf-section > *:not(.tf-field) { grid-column: 1 / -1; }
        .tf-field--full { grid-column: 1 / -1; }
        @media (max-width: 767px) { .tf-section { grid-template-columns: 1fr; } }
        .tf-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .tf-field { display: flex; flex-direction: column; gap: 4px; }
        .tf-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .tf-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .tf-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .tf-field__error { font-size: 12px; color: #b91c1c; }
        .tf-required { color: #b91c1c; }
        .tf-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; box-sizing: border-box; }
        .tf-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .tf-actions { display: flex; justify-content: flex-end; }
        .tf-table-wrap { width: 100%; overflow-x: auto; }
        .tf-detail-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .tf-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: nowrap; }
        .tf-detail-table td { padding: 4px 6px; vertical-align: middle; }
        .tf-target-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .tf-target-table th, .tf-target-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-border, #d1d5db); white-space: normal; word-break: break-word; vertical-align: top; line-height: 1.5; }
        .tf-target-table th { font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); }
        .tf-target-table th:first-child, .tf-target-table td:first-child { white-space: nowrap; }
    </style>
</div>
