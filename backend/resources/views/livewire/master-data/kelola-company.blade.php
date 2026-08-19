<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Company</h2>
            <p class="kc-page__subtitle">Kelola daftar Company (di bawah Corporate) dalam struktur organisasi</p>
        </div>

        <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary">
            + Tambah Company
        </button>
    </div>

    @if ($deleteErrorMessage)
        <div class="kc-alert" role="alert">
            {{ $deleteErrorMessage }}
        </div>
    @endif

    <div class="kc-filter">
        <label for="filterCorporateId" class="kc-filter__label">Filter Corporate</label>
        <x-searchable-select
            id="filterCorporateId"
            wire:model.live="filterCorporateId"
            :options="collect($corporateOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['name']])->all()"
            placeholder="Semua Corporate"
            class="kc-form-field__input kc-filter__select"
        />
    </div>

    <div class="kc-table-wrap">
        <table class="kc-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Logo</th>
                    <th>Kode</th>
                    <th>Nama Company</th>
                    <th>Corporate</th>
                    <th>Jumlah Business Unit</th>
                    <th>Dibuat Pada</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr class="kc-table__row" wire:key="company-{{ $company['id'] }}">
                        <td>
                            @if ($company['logo_url'])
                                <img src="{{ $company['logo_url'] }}" alt="Logo {{ $company['name'] }}" class="kc-table__logo">
                            @else
                                <span class="kc-table__logo-placeholder" aria-hidden="true">&#127970;</span>
                            @endif
                        </td>
                        <td>{{ $company['company_code'] ?? '-' }}</td>
                        <td>{{ $company['name'] }}</td>
                        <td>{{ $company['corporate_name'] ?? '-' }}</td>
                        <td>{{ $company['business_unit_count'] }}</td>
                        <td>{{ $company['created_at'] ? \Illuminate\Support\Carbon::parse($company['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                        <td class="kc-table__actions">
                            @if ($confirmingDeleteId === $company['id'])
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
                                <button type="button" wire:click="openEditForm('{{ $company['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                    Edit
                                </button>
                                <button type="button" wire:click="askDelete('{{ $company['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                    Hapus
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="7">
                            <div class="kc-empty">
                                <div class="kc-empty__illustration" aria-hidden="true">&#127970;</div>
                                <p class="kc-empty__title">Belum ada Company</p>
                                <p class="kc-empty__subtitle">Klik "Tambah Company" untuk menambahkan data baru.</p>
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
        <div class="kc-modal-backdrop" wire:key="company-form-backdrop">
            <div class="kc-modal kc-modal--wide" role="dialog" aria-modal="true">
                <h3 class="kc-modal__title">
                    {{ $editingId !== null ? 'Edit Company' : 'Tambah Company' }}
                </h3>

                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert">
                        {{ $formErrorMessage }}
                    </div>
                @endif

                <form wire:submit="save" novalidate>
                    <div class="kc-modal__body">
                        {{-- Logo --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Logo</h4>

                            <div class="kc-logo-field">
                                @if ($logo && $this->logoIsPreviewable)
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo baru" class="kc-logo-field__preview">
                                @elseif ($existingLogoUrl)
                                    <img src="{{ $existingLogoUrl }}" alt="Logo saat ini" class="kc-logo-field__preview">
                                @else
                                    <span class="kc-logo-field__placeholder" aria-hidden="true">&#127970;</span>
                                @endif

                                <div class="kc-logo-field__control">
                                    <input
                                        type="file"
                                        id="logo"
                                        wire:model="logo"
                                        accept="image/jpeg,image/png"
                                        class="kc-form-field__input"
                                    >
                                    <p class="kc-form-field__hint">Format JPG/PNG, maksimal 2MB.</p>
                                    <p wire:loading wire:target="logo" class="kc-form-field__hint">Mengunggah&hellip;</p>
                                    @error('logo')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Identity --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Identitas</h4>
                            <div class="kc-form-grid">
                                <div class="kc-form-field">
                                    <label for="corporate_id" class="kc-form-field__label">
                                        Corporate <span class="kc-form-field__required">*</span>
                                    </label>
                                    <x-searchable-select
                                        id="corporate_id"
                                        wire:model="corporate_id"
                                        :options="collect($corporateOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['name']])->all()"
                                        placeholder="-- Pilih Corporate --"
                                        empty-message="Belum ada Corporate. Buat Corporate terlebih dahulu."
                                        :class="'kc-form-field__input'.($errors->has('corporate_id') ? ' kc-form-field__input--error' : '')"
                                    />
                                    @error('corporate_id')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="company_code" class="kc-form-field__label">
                                        Kode Company <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="company_code"
                                        wire:model="form.company_code"
                                        class="kc-form-field__input @error('form.company_code') kc-form-field__input--error @enderror"
                                        autofocus
                                    >
                                    @error('form.company_code')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="name" class="kc-form-field__label">
                                        Nama Company <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        wire:model="form.name"
                                        class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror"
                                    >
                                    @error('form.name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="short_name" class="kc-form-field__label">Nama Singkat</label>
                                    <input type="text" id="short_name" wire:model="form.short_name" class="kc-form-field__input @error('form.short_name') kc-form-field__input--error @enderror">
                                    @error('form.short_name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="leader_name" class="kc-form-field__label">Nama Pimpinan</label>
                                    <input type="text" id="leader_name" wire:model="form.leader_name" class="kc-form-field__input @error('form.leader_name') kc-form-field__input--error @enderror">
                                    @error('form.leader_name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="lawyer_name" class="kc-form-field__label">Nama Pengacara</label>
                                    <input type="text" id="lawyer_name" wire:model="form.lawyer_name" class="kc-form-field__input @error('form.lawyer_name') kc-form-field__input--error @enderror">
                                    @error('form.lawyer_name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="last_update" class="kc-form-field__label">Terakhir Dilaporkan</label>
                                    <input type="date" id="last_update" wire:model="last_update" class="kc-form-field__input @error('last_update') kc-form-field__input--error @enderror">
                                    @error('last_update')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Contact --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Kontak</h4>
                            <div class="kc-form-grid">
                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="address" class="kc-form-field__label">Alamat</label>
                                    <input type="text" id="address" wire:model="form.address" class="kc-form-field__input @error('form.address') kc-form-field__input--error @enderror">
                                    @error('form.address')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="telephone_no" class="kc-form-field__label">No. Telepon</label>
                                    <input type="text" id="telephone_no" wire:model="form.telephone_no" class="kc-form-field__input @error('form.telephone_no') kc-form-field__input--error @enderror">
                                    @error('form.telephone_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="fax_no" class="kc-form-field__label">No. Fax</label>
                                    <input type="text" id="fax_no" wire:model="form.fax_no" class="kc-form-field__input @error('form.fax_no') kc-form-field__input--error @enderror">
                                    @error('form.fax_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="contact_no" class="kc-form-field__label">No. Kontak</label>
                                    <input type="text" id="contact_no" wire:model="form.contact_no" class="kc-form-field__input @error('form.contact_no') kc-form-field__input--error @enderror">
                                    @error('form.contact_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="extension_no" class="kc-form-field__label">No. Ekstensi</label>
                                    <input type="text" id="extension_no" wire:model="form.extension_no" class="kc-form-field__input @error('form.extension_no') kc-form-field__input--error @enderror">
                                    @error('form.extension_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="email" class="kc-form-field__label">Email</label>
                                    <input type="text" id="email" wire:model="form.email" class="kc-form-field__input @error('form.email') kc-form-field__input--error @enderror">
                                    @error('form.email')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="website" class="kc-form-field__label">Website</label>
                                    <input type="text" id="website" wire:model="form.website" class="kc-form-field__input @error('form.website') kc-form-field__input--error @enderror">
                                    @error('form.website')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="map" class="kc-form-field__label">Peta / Tautan Lokasi</label>
                                    <input type="text" id="map" wire:model="form.map" class="kc-form-field__input @error('form.map') kc-form-field__input--error @enderror">
                                    @error('form.map')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Legal & Employment --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Legal &amp; Ketenagakerjaan</h4>
                            <div class="kc-form-grid">
                                <div class="kc-form-field">
                                    <label for="tax_register_no" class="kc-form-field__label">No. NPWP</label>
                                    <input type="text" id="tax_register_no" wire:model="form.tax_register_no" class="kc-form-field__input @error('form.tax_register_no') kc-form-field__input--error @enderror">
                                    @error('form.tax_register_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="insurance_no" class="kc-form-field__label">No. Asuransi</label>
                                    <input type="text" id="insurance_no" wire:model="form.insurance_no" class="kc-form-field__input @error('form.insurance_no') kc-form-field__input--error @enderror">
                                    @error('form.insurance_no')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="epf_employer" class="kc-form-field__label">EPF Employer</label>
                                    <input type="text" id="epf_employer" wire:model="form.epf_employer" class="kc-form-field__input @error('form.epf_employer') kc-form-field__input--error @enderror">
                                    @error('form.epf_employer')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="socso_employer" class="kc-form-field__label">SOCSO Employer</label>
                                    <input type="text" id="socso_employer" wire:model="form.socso_employer" class="kc-form-field__input @error('form.socso_employer') kc-form-field__input--error @enderror">
                                    @error('form.socso_employer')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="labor_union" class="kc-form-field__label">Serikat Pekerja</label>
                                    <input type="text" id="labor_union" wire:model="form.labor_union" class="kc-form-field__input @error('form.labor_union') kc-form-field__input--error @enderror">
                                    @error('form.labor_union')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
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
        /* Design tokens — uiux-spec: brand #249360. Inlined here (same
           approach as every other Livewire view in this codebase, notably
           resources/views/livewire/master-data/kelola-corporate.blade.php)
           since the backend scaffold has no frontend build pipeline yet —
           see implementation_notes. `kc-` prefix (Kelola Corporate/Company
           — shared prefix reused from KelolaCorporate) keeps these rules
           scoped/non-colliding with other screens' inlined styles; this
           class set is reused verbatim (not duplicated-and-renamed) from
           kelola-corporate.blade.php to keep both master-data screens
           visually consistent — only kc-filter__* (this screen's corporate
           filter dropdown) has no Corporate equivalent. */
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

        .kc-table__logo {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--kc-border);
        }

        .kc-table__logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px dashed var(--kc-border);
            font-size: 15px;
            color: var(--kc-text-muted);
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
            max-width: 720px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .kc-modal--wide form {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
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
            min-height: 0;
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

        .kc-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 16px;
        }

        .kc-form-field--span2 {
            grid-column: span 2;
        }

        .kc-logo-field {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .kc-logo-field__preview {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--kc-border);
            flex-shrink: 0;
        }

        .kc-logo-field__placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 8px;
            border: 1px dashed var(--kc-border);
            font-size: 26px;
            color: var(--kc-text-muted);
            flex-shrink: 0;
        }

        .kc-logo-field__control {
            flex: 1;
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
