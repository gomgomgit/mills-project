<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Machinery</h2>
            <p class="kc-page__subtitle">Kelola daftar Machinery (di bawah Machinery Group), termasuk data Asuransi dan Pajak/Pembelian</p>
        </div>

        <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary">
            + Tambah Machinery
        </button>
    </div>

    @if ($deleteErrorMessage)
        <div class="kc-alert" role="alert">
            {{ $deleteErrorMessage }}
        </div>
    @endif

    <div class="kc-filter">
        <label for="filterMachineryGroupId" class="kc-filter__label">Filter Machinery Group</label>
        <x-searchable-select
            id="filterMachineryGroupId"
            wire:model.live="filterMachineryGroupId"
            :options="collect($machineryGroupOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['group_code']])->all()"
            placeholder="Semua Machinery Group"
            class="kc-form-field__input kc-filter__select"
        />
    </div>

    <div class="kc-table-wrap">
        <table class="kc-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Kode Equipment</th>
                    <th>Nama</th>
                    <th>Machinery Group</th>
                    <th>Tipe</th>
                    <th>Merk</th>
                    <th>Dibuat Pada</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($machineryRows as $machinery)
                    <tr class="kc-table__row" wire:key="machinery-{{ $machinery['id'] }}">
                        <td>{{ $machinery['equipment_code'] }}</td>
                        <td>{{ $machinery['name'] }}</td>
                        <td>{{ $machinery['machinery_group_code'] ?? '-' }}</td>
                        <td>{{ $machinery['equipment_type'] ?? '-' }}</td>
                        <td>{{ $machinery['brand'] ?? '-' }}</td>
                        <td>{{ $machinery['created_at'] ? \Illuminate\Support\Carbon::parse($machinery['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                        <td class="kc-table__actions">
                            @if ($confirmingDeleteId === $machinery['id'])
                                <span class="kc-confirm">
                                    <span class="kc-confirm__label">Yakin hapus?</span>
                                    <button type="button" wire:click="confirmDelete" class="kc-button kc-button--danger kc-button--sm">
                                        Ya, Hapus
                                    </button>
                                    <button type="button" wire:click="cancelDelete" class="kc-button kc-button--ghost kc-button--sm">
                                        Batal
                                    </button>
                                </span>
                            @else
                                <button type="button" wire:click="openEditForm('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                    Edit
                                </button>
                                <button type="button" wire:click="askDelete('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                    Hapus
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="7">
                            <div class="kc-empty">
                                <div class="kc-empty__illustration" aria-hidden="true">&#9881;&#65039;</div>
                                <p class="kc-empty__title">Belum ada Machinery</p>
                                <p class="kc-empty__subtitle">Klik "Tambah Machinery" untuk menambahkan data baru.</p>
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
        <div class="kc-modal-backdrop" wire:key="machinery-form-backdrop">
            <div class="kc-modal kc-modal--wide" role="dialog" aria-modal="true">
                <h3 class="kc-modal__title">
                    {{ $editingId !== null ? 'Edit Machinery' : 'Tambah Machinery' }}
                </h3>

                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert">
                        {{ $formErrorMessage }}
                    </div>
                @endif

                <form wire:submit="save" novalidate>
                    <div class="kc-modal__body">
                        {{-- Identity --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Identitas</h4>
                            <div class="kc-form-grid">
                                <div class="kc-form-field">
                                    <label for="machinery_group_id" class="kc-form-field__label">
                                        Machinery Group <span class="kc-form-field__required">*</span>
                                    </label>
                                    <x-searchable-select
                                        id="machinery_group_id"
                                        wire:model.live="machinery_group_id"
                                        :options="collect($machineryGroupOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['group_code']])->all()"
                                        placeholder="-- Pilih Machinery Group --"
                                        empty-message="Belum ada Machinery Group. Buat Machinery Group terlebih dahulu."
                                        :class="'kc-form-field__input'.($errors->has('machinery_group_id') ? ' kc-form-field__input--error' : '')"
                                    />
                                    @error('machinery_group_id')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="station_display" class="kc-form-field__label">Station</label>
                                    <input type="text" id="station_display" value="{{ $selectedStationName ?? '-' }}" class="kc-form-field__input" disabled>
                                    <p class="kc-form-field__hint">Otomatis mengikuti Station dari Machinery Group.</p>
                                </div>

                                <div class="kc-form-field">
                                    <label for="business_unit_display" class="kc-form-field__label">Business Unit</label>
                                    <input type="text" id="business_unit_display" value="{{ $selectedBusinessUnitName ?? '-' }}" class="kc-form-field__input" disabled>
                                    <p class="kc-form-field__hint">Otomatis mengikuti Business Unit dari Machinery Group.</p>
                                </div>

                                <div class="kc-form-field">
                                    <label for="equipment_code" class="kc-form-field__label">
                                        Kode Equipment <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input type="text" id="equipment_code" wire:model="form.equipment_code" class="kc-form-field__input @error('form.equipment_code') kc-form-field__input--error @enderror" autofocus>
                                    @error('form.equipment_code')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="name" class="kc-form-field__label">
                                        Nama <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input type="text" id="name" wire:model="form.name" class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror">
                                    @error('form.name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="description" class="kc-form-field__label">Deskripsi</label>
                                    <textarea id="description" wire:model="form.description" rows="2" class="kc-form-field__input @error('form.description') kc-form-field__input--error @enderror"></textarea>
                                    @error('form.description')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="picture" class="kc-form-field__label">Gambar</label>
                                    <input type="file" id="picture" wire:model="picture" class="kc-form-field__input @error('picture') kc-form-field__input--error @enderror">
                                    @error('picture')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror

                                    @if ($picture)
                                        <img src="{{ $picture->temporaryUrl() }}" alt="Preview" class="kc-picture-preview">
                                    @elseif ($existingPictureUrl)
                                        <img src="{{ $existingPictureUrl }}" alt="Gambar tersimpan" class="kc-picture-preview">
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Technical spec --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Spesifikasi Teknis</h4>
                            <div class="kc-form-grid">
                                @foreach ([
                                    'registration_no' => 'No. Registrasi',
                                    'make' => 'Make',
                                    'model' => 'Model',
                                    'equipment_type' => 'Tipe Equipment',
                                    'part_no' => 'No. Part',
                                    'serial_no' => 'No. Serial',
                                    'gearbox' => 'Gearbox',
                                    'motor' => 'Motor',
                                    'mounting' => 'Mounting',
                                    'chain' => 'Chain',
                                    'capacity' => 'Kapasitas',
                                    'brand' => 'Merk',
                                    'fixed_asset' => 'Fixed Asset',
                                    'control_activity' => 'Control Activity',
                                    'owner_ite' => 'Owner ITE',
                                ] as $field => $label)
                                    <div class="kc-form-field">
                                        <label for="{{ $field }}" class="kc-form-field__label">{{ $label }}</label>
                                        <input type="text" id="{{ $field }}" wire:model="form.{{ $field }}" class="kc-form-field__input @error('form.'.$field) kc-form-field__input--error @enderror">
                                        @error('form.'.$field)
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach

                                <div class="kc-form-field">
                                    <label for="rpm" class="kc-form-field__label">RPM</label>
                                    <input type="text" inputmode="decimal" id="rpm" wire:model="form.rpm" class="kc-form-field__input @error('form.rpm') kc-form-field__input--error @enderror">
                                    @error('form.rpm')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="year_made" class="kc-form-field__label">Tahun Pembuatan</label>
                                    <input type="text" inputmode="numeric" id="year_made" wire:model="form.year_made" class="kc-form-field__input @error('form.year_made') kc-form-field__input--error @enderror">
                                    @error('form.year_made')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Insurance grid --}}
                        <div class="kc-form-section">
                            <div class="kc-form-section__header">
                                <h4 class="kc-form-section__title">Asuransi</h4>
                                <button type="button" wire:click="addInsuranceRow" class="kc-button kc-button--ghost kc-button--sm">
                                    + Tambah Baris Asuransi
                                </button>
                            </div>

                            @if (empty($insurances))
                                <p class="kc-grid-empty">Belum ada data asuransi.</p>
                            @else
                                <div class="kc-child-grid-wrap">
                                    <table class="kc-child-grid">
                                        <thead>
                                            <tr>
                                                <th>Kepemilikan</th>
                                                <th>No. Polis</th>
                                                <th>Perusahaan Asuransi</th>
                                                <th>Tgl Kadaluarsa</th>
                                                <th>Premi</th>
                                                <th>Jml Diasuransikan</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($insurances as $index => $row)
                                                <tr wire:key="insurance-row-{{ $index }}">
                                                    <td><input type="text" wire:model="insurances.{{ $index }}.ownership" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="insurances.{{ $index }}.insurance_policy_no" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="insurances.{{ $index }}.insurance_company" class="kc-form-field__input"></td>
                                                    <td><input type="date" wire:model="insurances.{{ $index }}.insurance_expiry_date" class="kc-form-field__input"></td>
                                                    <td><input type="text" inputmode="decimal" wire:model="insurances.{{ $index }}.premium" class="kc-form-field__input"></td>
                                                    <td><input type="text" inputmode="decimal" wire:model="insurances.{{ $index }}.amount_insured" class="kc-form-field__input"></td>
                                                    <td>
                                                        <button type="button" wire:click="removeInsuranceRow({{ $index }})" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                                            Hapus
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Tax / Purchase grid --}}
                        <div class="kc-form-section">
                            <div class="kc-form-section__header">
                                <h4 class="kc-form-section__title">Pajak &amp; Pembelian</h4>
                                <button type="button" wire:click="addTaxPurchaseRow" class="kc-button kc-button--ghost kc-button--sm">
                                    + Tambah Baris Pajak/Pembelian
                                </button>
                            </div>

                            @if (empty($taxPurchases))
                                <p class="kc-grid-empty">Belum ada data pajak/pembelian.</p>
                            @else
                                <div class="kc-child-grid-wrap">
                                    <table class="kc-child-grid">
                                        <thead>
                                            <tr>
                                                <th>Tgl Pembelian</th>
                                                <th>Biaya Pembelian</th>
                                                <th>Jenis Polis</th>
                                                <th>Nama Kontak</th>
                                                <th>Telp Kontak</th>
                                                <th>Fax Kontak</th>
                                                <th>Email Kontak</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($taxPurchases as $index => $row)
                                                <tr wire:key="tax-purchase-row-{{ $index }}">
                                                    <td><input type="date" wire:model="taxPurchases.{{ $index }}.purchase_date" class="kc-form-field__input"></td>
                                                    <td><input type="text" inputmode="decimal" wire:model="taxPurchases.{{ $index }}.purchase_cost" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="taxPurchases.{{ $index }}.policy_type" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="taxPurchases.{{ $index }}.contact_name" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="taxPurchases.{{ $index }}.contact_phone" class="kc-form-field__input"></td>
                                                    <td><input type="text" wire:model="taxPurchases.{{ $index }}.contact_fax" class="kc-form-field__input"></td>
                                                    <td><input type="email" wire:model="taxPurchases.{{ $index }}.contact_email" class="kc-form-field__input"></td>
                                                    <td>
                                                        <button type="button" wire:click="removeTaxPurchaseRow({{ $index }})" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                                            Hapus
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="kc-modal__actions">
                        <button type="button" wire:click="closeForm" class="kc-button kc-button--ghost">
                            Batal
                        </button>
                        <button type="submit" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <style>
        /* Design tokens — shared `kc-` classes reused verbatim from
           kelola-machinery-group.blade.php (see that file's own style
           block docblock) plus a small set of NEW classes this screen's
           two repeatable child-row grids need
           (.kc-form-section__header, .kc-grid-empty, .kc-child-grid-wrap,
           .kc-child-grid, .kc-picture-preview) — additive only, nothing
           renamed/removed from the shared set. */
        .kc-page {
            --kc-brand: #249360;
            --kc-brand-hover: #1d7a4e;
            --kc-destructive: #DC2626;
            --kc-destructive-hover: #b91c1c;
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

        .kc-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .kc-filter__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--kc-text-muted);
        }

        .kc-filter__select {
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

        .kc-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 50;
        }

        .kc-modal {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .kc-modal--wide {
            max-width: 920px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .kc-modal__title {
            margin: 0 0 16px;
            font-size: 17px;
            font-weight: 700;
        }

        .kc-modal__body {
            overflow-y: auto;
            padding-right: 4px;
            flex: 1;
        }

        .kc-modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
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

        .kc-form-section__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--kc-border);
            margin-bottom: 12px;
        }

        .kc-form-section__header .kc-form-section__title {
            margin: 0;
            padding-bottom: 0;
            border-bottom: none;
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
            width: 100%;
        }

        .kc-form-field__input:disabled {
            background: #f3f4f6;
            color: var(--kc-text-muted);
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

        .kc-picture-preview {
            margin-top: 8px;
            max-width: 160px;
            max-height: 120px;
            border-radius: var(--kc-radius-input);
            border: 1px solid var(--kc-border);
            object-fit: cover;
        }

        .kc-grid-empty {
            margin: 0;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-child-grid-wrap {
            overflow-x: auto;
            border: 1px solid var(--kc-border);
            border-radius: 8px;
        }

        .kc-child-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .kc-child-grid th {
            text-align: left;
            padding: 8px 10px;
            background: #f9fafb;
            color: var(--kc-text-muted);
            font-weight: 600;
            white-space: nowrap;
            border-bottom: 1px solid var(--kc-border);
        }

        .kc-child-grid td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--kc-border);
            min-width: 120px;
        }

        .kc-child-grid tbody tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 560px) {
            .kc-form-grid {
                grid-template-columns: 1fr;
            }

            .kc-form-field--span2 {
                grid-column: span 1;
            }
        }
    </style>
</div>
