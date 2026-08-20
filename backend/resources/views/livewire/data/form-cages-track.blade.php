<div class="fc-page">
    <div class="fc-page__header">
        <div>
            <h2 class="fc-page__title">{{ $isEdit ? 'Edit' : 'Tambah' }} Data Cages Track</h2>
            <p class="fc-page__subtitle">{{ $isEdit ? 'Ubah record Cages Track tersimpan.' : 'Buat record Cages Track baru.' }}</p>
        </div>
        <a href="{{ $isEdit ? route('data.cages-track.detail', ['id' => $id]) : route('data.cages-track') }}" class="fc-button fc-button--secondary" data-testid="cancel-button">Batal</a>
    </div>

    @if ($notFound)
        <div class="fc-alert fc-alert--error" role="alert" data-testid="record-not-found">
            Record tidak ditemukan.
        </div>
    @else
        @if ($generalError)
            <div class="fc-alert fc-alert--error" role="alert" data-testid="general-error">
                {{ $generalError }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="fc-form">
            <div class="fc-section">
                <h4 class="fc-section__title">Identitas Cages Track</h4>

                @if (! $isEdit)
                    <div class="fc-field">
                        <label class="fc-field__label" for="business_unit_id">Business Unit <span class="fc-required">*</span></label>
                        <select id="business_unit_id" wire:model.live="form.business_unit_id" class="fc-input" data-testid="business-unit-select">
                            <option value="">Pilih Business Unit</option>
                            @foreach ($businessUnitOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        @if (isset($errors_['business_unit_id']))
                            <span class="fc-field__error">{{ $errors_['business_unit_id'] }}</span>
                        @endif
                    </div>
                @else
                    <div class="fc-field">
                        <span class="fc-field__label">Business Unit</span>
                        <span class="fc-field__readonly" data-testid="business-unit-readonly">{{ $businessUnitName ?? '-' }}</span>
                    </div>
                @endif

                <div class="fc-field">
                    <label class="fc-field__label" for="cages_track_number">Cages Track Number <span class="fc-required">*</span></label>
                    <input id="cages_track_number" type="text" wire:model="form.cages_track_number" class="fc-input" data-testid="cages-track-number-input">
                    @if (isset($errors_['cages_track_number']))
                        <span class="fc-field__error">{{ $errors_['cages_track_number'] }}</span>
                    @endif
                </div>

                <div class="fc-field">
                    <label class="fc-field__label" for="date">Tanggal <span class="fc-required">*</span></label>
                    <input id="date" type="date" wire:model="form.date" class="fc-input" data-testid="date-input">
                    @if (isset($errors_['date']))
                        <span class="fc-field__error">{{ $errors_['date'] }}</span>
                    @endif
                </div>
            </div>

            <div class="fc-section">
                <h4 class="fc-section__title">Tippler &amp; Cages</h4>

                <div class="fc-field">
                    <label class="fc-field__label" for="tippler_start_time">Tippler Start Time <span class="fc-required">*</span></label>
                    <input id="tippler_start_time" type="datetime-local" wire:model="form.tippler_start_time" class="fc-input" data-testid="tippler-start-time-input">
                    @if (isset($errors_['tippler_start_time']))
                        <span class="fc-field__error">{{ $errors_['tippler_start_time'] }}</span>
                    @endif
                </div>

                <div class="fc-field">
                    <label class="fc-field__label" for="tippler_stop_time">Tippler Stop Time <span class="fc-required">*</span></label>
                    <input id="tippler_stop_time" type="datetime-local" wire:model="form.tippler_stop_time" class="fc-input" data-testid="tippler-stop-time-input">
                    @if (isset($errors_['tippler_stop_time']))
                        <span class="fc-field__error">{{ $errors_['tippler_stop_time'] }}</span>
                    @endif
                </div>

                <div class="fc-field">
                    <label class="fc-field__label" for="cages_out">Cages Out <span class="fc-required">*</span></label>
                    <input id="cages_out" type="number" step="1" wire:model="form.cages_out" class="fc-input" data-testid="cages-out-input">
                    @if (isset($errors_['cages_out']))
                        <span class="fc-field__error">{{ $errors_['cages_out'] }}</span>
                    @endif
                </div>

                <div class="fc-field">
                    <label class="fc-field__label" for="cages_tipped">Cages Tipped <span class="fc-required">*</span></label>
                    <input id="cages_tipped" type="number" step="1" wire:model="form.cages_tipped" class="fc-input" data-testid="cages-tipped-input">
                    @if (isset($errors_['cages_tipped']))
                        <span class="fc-field__error">{{ $errors_['cages_tipped'] }}</span>
                    @endif
                    <span class="fc-field__hint">Jumlah cage yang akan di-tipping sesi ini — tidak menentukan jumlah kolom checklist di bawah.</span>
                </div>

                <div class="fc-field">
                    <label class="fc-field__label" for="note">Note</label>
                    <textarea id="note" wire:model="form.note" class="fc-input" data-testid="note-input"></textarea>
                </div>
            </div>

            <div class="fc-section">
                <h4 class="fc-section__title">Cages Tipped Time</h4>

                @if ($detailError)
                    <div class="fc-alert fc-alert--error" role="alert" data-testid="detail-error">
                        {{ $detailError }}
                    </div>
                @endif

                @if ($jumlahCages <= 0)
                    <span class="fc-field__hint" data-testid="jumlah-cages-unavailable-hint">
                        {{ $isEdit || filled($form['business_unit_id']) ? 'Jumlah Cages dari Mills Setting belum tersedia untuk mill ini.' : 'Pilih Business Unit terlebih dahulu.' }}
                    </span>
                @else
                    <span class="fc-field__hint" data-testid="jumlah-cages-hint">Jumlah kolom checklist (N = {{ $jumlahCages }}) mengacu ke Jumlah Cages Mills Setting mill ini.</span>
                @endif

                <table class="fc-detail-table" data-testid="cages-tipped-time-grid">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Cage Checklist</th>
                            <th>Total Cages</th>
                            <th>Cages Remain</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detailRows as $index => $row)
                            <tr data-testid="cages-tipped-time-row-{{ $index }}">
                                <td>
                                    <select wire:model.live="detailRows.{{ $index }}.tipped_hour" class="fc-input" data-testid="detail-hour-select-{{ $index }}">
                                        <option value="">Pilih Time</option>
                                        @foreach ($this->availableHourOptions($index) as $hour)
                                            <option value="{{ $hour }}">{{ sprintf('%02d:00', $hour) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="fc-cage-grid" data-testid="detail-cage-checklist-{{ $index }}">
                                        @for ($cage = 1; $cage <= $jumlahCages; $cage++)
                                            <label class="fc-checkbox fc-checkbox--cage">
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleCage({{ $index }}, {{ $cage }})"
                                                    @checked(in_array($cage, $row['checked_cage_numbers'] ?? [], true))
                                                    data-testid="detail-cage-{{ $index }}-{{ $cage }}"
                                                >
                                                {{ $cage }}
                                            </label>
                                        @endfor
                                    </div>
                                </td>
                                <td>
                                    <input type="text" value="{{ $this->rowTotalCages($index) }}" class="fc-input" data-testid="detail-total-cages-{{ $index }}" disabled>
                                </td>
                                <td>
                                    <input type="text" value="{{ $this->rowCagesRemain($index) }}" class="fc-input" data-testid="detail-cages-remain-{{ $index }}" disabled>
                                </td>
                                <td>
                                    <button type="button" wire:click="removeDetailRow({{ $index }})" class="fc-button fc-button--secondary" data-testid="remove-row-button-{{ $index }}">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="button" wire:click="addDetailRow" class="fc-button fc-button--secondary" data-testid="add-row-button" @disabled(! $this->canAddRow())>+ Tambah Baris</button>
            </div>

            @if ($this->isSupervisor() || $this->isMillManagement())
                <div class="fc-section">
                    <h4 class="fc-section__title">Verifikasi</h4>

                    @if ($this->isSupervisor())
                        <label class="fc-checkbox">
                            <input type="checkbox" wire:model="checked" data-testid="checked-checkbox">
                            Tandai sudah diperiksa (Checked)
                        </label>
                    @endif

                    @if ($this->isMillManagement())
                        <label class="fc-checkbox">
                            <input type="checkbox" wire:model="acknowledged" data-testid="acknowledged-checkbox">
                            Tandai sudah dikonfirmasi (Acknowledged)
                        </label>
                    @endif
                </div>
            @endif

            <div class="fc-actions">
                <button type="submit" class="fc-button fc-button--primary" data-testid="save-button">Simpan</button>
            </div>
        </form>
    @endif

    <style>
        .fc-page { display: flex; flex-direction: column; gap: 20px; max-width: 900px; }
        .fc-page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .fc-page__title { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .fc-page__subtitle { margin: 0; font-size: 14px; color: var(--color-text-muted, #6b7280); }
        .fc-button { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .fc-button--secondary { background: #fff; color: var(--color-text, #1f2937); border: 1px solid var(--color-border, #d1d5db); }
        .fc-button--primary { background: var(--color-brand, #249360); color: #fff; }
        .fc-button:disabled { opacity: 0.5; cursor: not-allowed; }
        .fc-alert { padding: 12px 16px; border-radius: var(--radius-input, 6px); font-size: 14px; }
        .fc-alert--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .fc-form { display: flex; flex-direction: column; gap: 20px; }
        .fc-section { background: #fff; border: 1px solid var(--color-border, #d1d5db); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
        .fc-section__title { margin: 0; font-size: 16px; font-weight: 700; }
        .fc-field { display: flex; flex-direction: column; gap: 4px; }
        .fc-field__label { font-size: 13px; font-weight: 500; color: var(--color-text, #1f2937); }
        .fc-field__readonly { font-size: 14px; color: var(--color-text-muted, #6b7280); padding: 8px 0; }
        .fc-field__hint { font-size: 12px; color: var(--color-text-muted, #6b7280); }
        .fc-field__error { font-size: 12px; color: #b91c1c; }
        .fc-required { color: #b91c1c; }
        .fc-input { padding: 8px 12px; border: 1px solid var(--color-border, #d1d5db); border-radius: var(--radius-input, 6px); font-size: 14px; font-family: inherit; width: 100%; }
        .fc-input:disabled { background: #f3f4f6; color: var(--color-text-muted, #6b7280); }
        .fc-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .fc-checkbox--cage { display: inline-flex; padding: 2px 6px; }
        .fc-cage-grid { display: flex; flex-wrap: wrap; gap: 4px; max-width: 360px; }
        .fc-actions { display: flex; justify-content: flex-end; }
        .fc-detail-table { width: 100%; border-collapse: collapse; }
        .fc-detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-muted, #6b7280); padding: 6px 8px; border-bottom: 1px solid var(--color-border, #d1d5db); }
        .fc-detail-table td { padding: 6px 8px; vertical-align: top; }
    </style>
</div>
