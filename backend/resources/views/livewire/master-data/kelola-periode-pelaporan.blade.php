@php
    /**
     * screen-128--kelola-periode-pelaporan — Kelola Periode Pelaporan.
     *
     * Status badge vocabulary is Indonesian, per the business spec and the
     * Phase 2 mock: draft => "Draft" (neutral), open => "Terbuka"
     * (success), closed => "Tertutup" (destructive). The stored value
     * stays English (periods.status = draft|open|closed) — only the label
     * is translated.
     */
    $statusLabels = ['draft' => 'Draft', 'open' => 'Terbuka', 'closed' => 'Tertutup'];
    $statusBadgeClass = ['draft' => 'kc-badge--neutral', 'open' => 'kc-badge--success', 'closed' => 'kc-badge--closed'];
    $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '-';
@endphp

<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete,confirmClose,confirmReopen,askClose">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Periode Pelaporan</h2>
            <p class="kc-page__subtitle">
                Rentang tanggal pelaporan resmi per mill dan jenis stasiun — satuan periode untuk seluruh laporan Full Cycle per Stasiun.
            </p>
        </div>

        <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary" data-testid="add-period-button">
            + Tambah Periode
        </button>
    </div>

    @if ($successMessage)
        <div class="kc-alert kc-alert--success" role="status" data-testid="success-message">
            {{ $successMessage }}
        </div>
    @endif

    @if ($deleteErrorMessage)
        <div class="kc-alert" role="alert" data-testid="delete-error">
            {{ $deleteErrorMessage }}
        </div>
    @endif

    @if ($closeErrorMessage)
        <div class="kc-alert" role="alert" data-testid="close-error">
            {{ $closeErrorMessage }}
        </div>
    @endif

    <div class="kc-filter">
        <div class="kc-filter__group">
            <label for="filterBusinessUnitId" class="kc-filter__label">Business Unit</label>
            <x-searchable-select
                id="filterBusinessUnitId"
                wire:model.live="filterBusinessUnitId"
                :options="collect($businessUnitOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['name']])->all()"
                placeholder="Semua Business Unit"
                class="kc-form-field__input kc-filter__select"
                data-testid="filter-business-unit"
            />
        </div>

        <div class="kc-filter__group">
            <label for="filterStatus" class="kc-filter__label">Status</label>
            <x-searchable-select
                id="filterStatus"
                wire:model.live="filterStatus"
                :options="[
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'open', 'label' => 'Terbuka'],
                    ['value' => 'closed', 'label' => 'Tertutup'],
                ]"
                placeholder="Semua Status"
                class="kc-form-field__input kc-filter__select"
                data-testid="filter-status"
            />
        </div>
    </div>

    <div class="kc-table-wrap">
        <table class="kc-table" data-testid="period-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Nama</th>
                    <th>Business Unit</th>
                    <th>Jenis Stasiun</th>
                    <th>Tanggal Mulai</th>
                    <th>Tanggal Selesai</th>
                    <th>Status</th>
                    <th>Ditutup Oleh</th>
                    <th>Waktu Ditutup</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr class="kc-table__row" wire:key="period-{{ $period['id'] }}" data-testid="period-row-{{ $period['id'] }}">
                        <td>{{ $period['name'] }}</td>
                        <td>{{ $period['business_unit_name'] ?? '-' }}</td>
                        <td>{{ $period['station_type_label'] }}</td>
                        <td>{{ $formatDate($period['start_date']) }}</td>
                        <td>{{ $formatDate($period['end_date']) }}</td>
                        <td>
                            <span class="kc-badge {{ $statusBadgeClass[$period['status']] ?? 'kc-badge--neutral' }}" data-testid="status-badge-{{ $period['id'] }}">
                                {{ $statusLabels[$period['status']] ?? $period['status'] }}
                            </span>
                        </td>
                        <td>{{ $period['closed_by_name'] ?? '—' }}</td>
                        <td>{{ $period['closed_at'] ? \Illuminate\Support\Carbon::parse($period['closed_at'])->format('d/m/Y H:i') : '—' }}</td>
                        <td class="kc-table__actions">
                            @if ($confirmingDeleteId === $period['id'])
                                <span class="kc-confirm">
                                    <span class="kc-confirm__label">Yakin hapus?</span>
                                    <button type="button" wire:click="confirmDelete" class="kc-button kc-button--danger kc-button--sm" data-testid="confirm-delete-button">
                                        Ya, Hapus
                                    </button>
                                    <button type="button" wire:click="cancelDelete" class="kc-button kc-button--ghost kc-button--sm">
                                        Batal
                                    </button>
                                </span>
                            @elseif ($confirmingReopenId === $period['id'])
                                <span class="kc-confirm">
                                    <span class="kc-confirm__label">Buka kembali periode ini?</span>
                                    <button type="button" wire:click="confirmReopen" class="kc-button kc-button--primary kc-button--sm" data-testid="confirm-reopen-button">
                                        Ya, Buka Kembali
                                    </button>
                                    <button type="button" wire:click="cancelReopen" class="kc-button kc-button--ghost kc-button--sm">
                                        Batal
                                    </button>
                                </span>
                            @elseif ($period['status'] === 'closed')
                                {{--
                                    A closed period exposes exactly ONE action. Edit,
                                    Hapus and Tutup Periode are not rendered at all —
                                    the service would refuse them anyway (409
                                    PERIOD_CLOSED_IMMUTABLE / PERIOD_ALREADY_CLOSED),
                                    so offering them would only invite a dead end.
                                --}}
                                <button type="button" wire:click="askReopen('{{ $period['id'] }}')" class="kc-button kc-button--ghost kc-button--sm" data-testid="reopen-button-{{ $period['id'] }}">
                                    Buka Kembali Periode
                                </button>
                            @else
                                <button type="button" wire:click="openEditForm('{{ $period['id'] }}')" class="kc-button kc-button--ghost kc-button--sm" data-testid="edit-button-{{ $period['id'] }}">
                                    Edit
                                </button>
                                <button type="button" wire:click="askClose('{{ $period['id'] }}')" class="kc-button kc-button--warning kc-button--sm" data-testid="close-button-{{ $period['id'] }}">
                                    Tutup Periode
                                </button>
                                <button type="button" wire:click="askDelete('{{ $period['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text" data-testid="delete-button-{{ $period['id'] }}">
                                    Hapus
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="9">
                            <div class="kc-empty" data-testid="period-empty">
                                <div class="kc-empty__illustration" aria-hidden="true">&#128197;</div>
                                <p class="kc-empty__title">Belum ada periode</p>
                                <p class="kc-empty__subtitle">Klik "Tambah Periode" untuk menambahkan periode pelaporan baru.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($meta['total'] > 0)
        <div class="kc-pagination">
            <span class="kc-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="kc-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="kc-button kc-button--ghost kc-button--sm"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="kc-button kc-button--ghost kc-button--sm"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    @if ($showForm)
        <x-modal
            :title="$editingId !== null ? 'Edit Periode' : 'Tambah Periode'"
            wide
            submit="save"
            backdrop-key="period-form-backdrop"
        >
            <x-slot:error>
                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert" data-testid="form-error">
                        {{ $formErrorMessage }}
                    </div>
                @endif
            </x-slot:error>

            <div class="kc-form-section">
                <h4 class="kc-form-section__title">Cakupan Periode</h4>
                <div class="kc-form-grid">
                    <div class="kc-form-field">
                        <label for="business_unit_id" class="kc-form-field__label">
                            Business Unit <span class="kc-form-field__required">*</span>
                        </label>
                        <x-searchable-select
                            id="business_unit_id"
                            wire:model="business_unit_id"
                            :options="collect($businessUnitOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['name']])->all()"
                            placeholder="-- Pilih Business Unit --"
                            empty-message="Belum ada Business Unit. Buat Business Unit terlebih dahulu."
                            :class="'kc-form-field__input'.($errors->has('business_unit_id') ? ' kc-form-field__input--error' : '')"
                            data-testid="business-unit-select"
                        />
                        @error('business_unit_id')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="station_type" class="kc-form-field__label">Jenis Stasiun</label>
                        {{--
                            Options come from the `station_types` master table
                            (PeriodService::stationTypeOptions()), never from a
                            hardcoded list — adding a station type is an INSERT.
                            The empty first option is the NULL "covers every
                            station type in this mill" scope.
                        --}}
                        <x-searchable-select
                            id="station_type"
                            wire:model="station_type"
                            :options="collect($stationTypeOptions)->map(fn ($option) => ['value' => $option['code'], 'label' => $option['name']])->all()"
                            placeholder="Semua Stasiun"
                            :class="'kc-form-field__input'.($errors->has('station_type') ? ' kc-form-field__input--error' : '')"
                            data-testid="station-type-select"
                        />
                        <p class="kc-form-field__hint">Opsional — dikosongkan berarti periode berlaku untuk semua jenis stasiun di mill ini.</p>
                        @error('station_type')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="kc-form-section">
                <h4 class="kc-form-section__title">Identitas &amp; Rentang</h4>
                <div class="kc-form-grid">
                    <div class="kc-form-field kc-form-field--span2">
                        <label for="name" class="kc-form-field__label">
                            Nama Periode <span class="kc-form-field__required">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model="form.name"
                            class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror"
                            autofocus
                            data-testid="name-input"
                        >
                        <p class="kc-form-field__hint">Harus unik dalam satu kombinasi mill + jenis stasiun.</p>
                        @error('form.name')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="start_date" class="kc-form-field__label">
                            Tanggal Mulai <span class="kc-form-field__required">*</span>
                        </label>
                        <input
                            type="date"
                            id="start_date"
                            wire:model="form.start_date"
                            class="kc-form-field__input @error('form.start_date') kc-form-field__input--error @enderror"
                            data-testid="start-date-input"
                        >
                        @error('form.start_date')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="end_date" class="kc-form-field__label">
                            Tanggal Selesai <span class="kc-form-field__required">*</span>
                        </label>
                        <input
                            type="date"
                            id="end_date"
                            wire:model="form.end_date"
                            class="kc-form-field__input @error('form.end_date') kc-form-field__input--error @enderror"
                            data-testid="end-date-input"
                        >
                        <p class="kc-form-field__hint">Tidak boleh lebih awal dari tanggal mulai, dan rentangnya tidak boleh tumpang tindih dengan periode lain pada mill + jenis stasiun yang sama.</p>
                        @error('form.end_date')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <button type="button" wire:click="closeForm" class="kc-button kc-button--ghost">
                    Batal
                </button>
                <button type="submit" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="save" data-testid="save-button">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan&hellip;</span>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    @if ($closingId !== null && $closingPeriod !== null)
        <x-modal
            :title="'Tutup Periode — '.$closingPeriod['name']"
            backdrop-key="period-close-backdrop"
        >
            <div class="kc-alert kc-alert--warning" role="alert" data-testid="unverified-warning">
                <strong data-testid="unverified-count">
                    {{ $closingUnverifiedCount }} data stasiun belum terverifikasi dalam rentang periode ini
                </strong>
                <p class="kc-dialog__note">
                    Setelah periode ditutup, verifikasi ikut terkunci — data tersebut akan tetap berstatus
                    belum terverifikasi sampai periode dibuka kembali.
                </p>

                @if (count($closingBreakdown) > 0)
                    <ul class="kc-dialog__breakdown" data-testid="unverified-breakdown">
                        @foreach ($closingBreakdown as $row)
                            <li>{{ $stationTypeLabels[$row['station_type']] ?? $row['station_type'] }}: {{ $row['count'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <p class="kc-dialog__note">
                Menutup periode
                <strong>{{ $formatDate($closingPeriod['start_date']) }} &ndash; {{ $formatDate($closingPeriod['end_date']) }}</strong>
                pada
                <strong>{{ $closingPeriod['business_unit_name'] ?? '-' }} &middot; {{ $closingPeriod['station_type_label'] }}</strong>
                akan mengunci seluruh data stasiun yang tanggal kejadiannya berada di dalam rentang tersebut:
                tidak bisa diinput baru, tidak bisa diubah, dan tidak bisa diverifikasi.
            </p>

            <x-slot:actions>
                <button type="button" wire:click="cancelClose" class="kc-button kc-button--ghost" data-testid="cancel-close-button">
                    Batal
                </button>
                <button type="button" wire:click="confirmClose" class="kc-button kc-button--warning" wire:loading.attr="disabled" wire:target="confirmClose" data-testid="confirm-close-button">
                    Ya, Tutup Periode
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Reused verbatim (kc-
           prefix) from kelola-production-line.blade.php so every
           master-data screen stays visually consistent; the kc-badge-*,
           kc-button--warning, kc-alert--success/--warning and kc-dialog__*
           rules at the end are this screen's own additions (status badges
           and the close-confirmation dialog). */
        .kc-page {
            --kc-brand: #249360;
            --kc-brand-hover: #1d7a4e;
            --kc-destructive: #DC2626;
            --kc-destructive-hover: #b91c1c;
            --kc-warning: #B45309;
            --kc-warning-hover: #92400e;
            --kc-text: #1f2937;
            --kc-text-muted: #6b7280;
            --kc-border: #d1d5db;
            --kc-radius-input: 6px;
            --kc-radius-button: 8px;
            color: var(--kc-text);
        }

        .kc-page--busy {
            opacity: 0.85;
        }

        .kc-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .kc-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .kc-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--kc-text-muted);
        }

        .kc-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--kc-radius-input);
            background: #fef2f2;
            border: 1px solid var(--kc-destructive);
            color: var(--kc-destructive);
            font-size: 14px;
        }

        .kc-alert--success {
            background: #ecfdf5;
            border-color: var(--kc-brand);
            color: var(--kc-brand-hover);
        }

        .kc-alert--warning {
            background: #fffbeb;
            border-color: var(--kc-warning);
            color: var(--kc-warning-hover);
        }

        .kc-filter {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .kc-filter__group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .kc-filter__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--kc-text-muted);
        }

        .kc-filter__select {
            min-width: 220px;
            max-width: 260px;
        }

        .kc-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--kc-border);
            border-radius: 10px;
            background: #fff;
        }

        .kc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .kc-table__head th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--kc-text-muted);
            border-bottom: 1px solid var(--kc-border);
            white-space: nowrap;
        }

        .kc-table__actions-head {
            text-align: right;
        }

        .kc-table__row td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--kc-border);
            vertical-align: middle;
        }

        .kc-table__row:last-child td {
            border-bottom: none;
        }

        .kc-table__actions {
            text-align: right;
            white-space: nowrap;
        }

        .kc-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .kc-badge--neutral {
            background: #f3f4f6;
            color: var(--kc-text-muted);
            border: 1px solid var(--kc-border);
        }

        .kc-badge--success {
            background: #ecfdf5;
            color: var(--kc-brand-hover);
            border: 1px solid var(--kc-brand);
        }

        .kc-badge--closed {
            background: #fef2f2;
            color: var(--kc-destructive-hover);
            border: 1px solid var(--kc-destructive);
        }

        .kc-confirm {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .kc-confirm__label {
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-empty {
            padding: 48px 16px;
            text-align: center;
        }

        .kc-empty__illustration {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .kc-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--kc-text);
        }

        .kc-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-pagination__controls {
            display: flex;
            gap: 8px;
        }

        .kc-button {
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--kc-radius-button);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .kc-button--sm {
            padding: 6px 10px;
            font-size: 13px;
        }

        .kc-button--primary {
            background: var(--kc-brand);
            color: #fff;
        }

        .kc-button--primary:hover:not(:disabled) {
            background: var(--kc-brand-hover);
        }

        .kc-button--danger {
            background: var(--kc-destructive);
            color: #fff;
        }

        .kc-button--danger:hover:not(:disabled) {
            background: var(--kc-destructive-hover);
        }

        .kc-button--warning {
            background: var(--kc-warning);
            color: #fff;
        }

        .kc-button--warning:hover:not(:disabled) {
            background: var(--kc-warning-hover);
        }

        .kc-button--ghost {
            background: #fff;
            color: var(--kc-text);
            border: 1px solid var(--kc-border);
        }

        .kc-button--ghost:hover:not(:disabled) {
            border-color: var(--kc-brand);
            color: var(--kc-brand);
        }

        .kc-button--danger-text:hover:not(:disabled) {
            border-color: var(--kc-destructive);
            color: var(--kc-destructive);
        }

        .kc-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .kc-form-section {
            margin-bottom: 20px;
        }

        .kc-form-section:last-child {
            margin-bottom: 0;
        }

        .kc-form-section__title {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--kc-text-muted);
            padding-bottom: 6px;
            border-bottom: 1px solid var(--kc-border);
        }

        .kc-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 16px;
        }

        .kc-form-field--span2 {
            grid-column: span 2;
        }

        .kc-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .kc-form-field__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--kc-text-muted);
        }

        .kc-form-field__required {
            color: var(--kc-destructive);
        }

        .kc-form-field__input {
            padding: 9px 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--kc-text);
            border: 1px solid var(--kc-border);
            border-radius: var(--kc-radius-input);
            background: #fff;
        }

        .kc-form-field__input:focus {
            outline: none;
            border-color: var(--kc-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .kc-form-field__input--error {
            border-color: var(--kc-destructive);
        }

        .kc-form-field__error {
            margin: 0;
            font-size: 12px;
            color: var(--kc-destructive);
        }

        .kc-form-field__hint {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--kc-text-muted);
        }

        .kc-dialog__note {
            margin: 8px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: var(--kc-text);
        }

        .kc-dialog__breakdown {
            margin: 8px 0 0;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 767px) {
            .kc-form-grid {
                grid-template-columns: 1fr;
            }

            .kc-form-field--span2 {
                grid-column: span 1;
            }
        }
    </style>
</div>
