<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Station</h2>
            <p class="kc-page__subtitle">Kelola daftar Station (di bawah Business Unit) dalam struktur organisasi</p>
        </div>

        <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary">
            + Tambah Station
        </button>
    </div>

    @if ($deleteErrorMessage)
        <div class="kc-alert" role="alert">
            {{ $deleteErrorMessage }}
        </div>
    @endif

    <div class="kc-filter">
        <label for="filterBusinessUnitId" class="kc-filter__label">Filter Business Unit</label>
        <x-searchable-select
            id="filterBusinessUnitId"
            wire:model.live="filterBusinessUnitId"
            :options="collect($businessUnitOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['name']])->all()"
            placeholder="Semua Business Unit"
            class="kc-form-field__input kc-filter__select"
        />
    </div>

    <div class="kc-table-wrap">
        <table class="kc-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Nama</th>
                    <th>Business Unit</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Kode</th>
                    <th>Jumlah Machinery Group</th>
                    <th>Dibuat Pada</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stations as $station)
                    <tr class="kc-table__row" wire:key="station-{{ $station['id'] }}">
                        <td>{{ $station['name'] }}</td>
                        <td>{{ $station['business_unit_name'] ?? '-' }}</td>
                        <td>{{ ucfirst(str_replace('-', ' ', $station['type'])) }}</td>
                        <td>
                            @if ($station['is_active'])
                                <span class="kc-badge kc-badge--active">Aktif</span>
                            @else
                                <span class="kc-badge kc-badge--inactive">Nonaktif</span>
                            @endif
                        </td>
                        <td>{{ $station['code'] ?? '-' }}</td>
                        <td>{{ $station['machinery_group_count'] }}</td>
                        <td>{{ $station['created_at'] ? \Illuminate\Support\Carbon::parse($station['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                        <td class="kc-table__actions">
                            @if ($confirmingDeleteId === $station['id'])
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
                                <button type="button" wire:click="openEditForm('{{ $station['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                    Edit
                                </button>
                                <button type="button" wire:click="askDelete('{{ $station['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                    Hapus
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="8">
                            <div class="kc-empty">
                                <div class="kc-empty__illustration" aria-hidden="true">&#9881;&#65039;</div>
                                <p class="kc-empty__title">Belum ada Station</p>
                                <p class="kc-empty__subtitle">Klik "Tambah Station" untuk menambahkan data baru.</p>
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
        <div class="kc-modal-backdrop" wire:key="station-form-backdrop">
            <div class="kc-modal kc-modal--wide" role="dialog" aria-modal="true">
                <h3 class="kc-modal__title">
                    {{ $editingId !== null ? 'Edit Station' : 'Tambah Station' }}
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
                                    />
                                    @error('business_unit_id')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="name" class="kc-form-field__label">
                                        Nama Station <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        wire:model="form.name"
                                        class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror"
                                        autofocus
                                    >
                                    @error('form.name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="type" class="kc-form-field__label">
                                        Type <span class="kc-form-field__required">*</span>
                                    </label>
                                    <x-searchable-select
                                        id="type"
                                        wire:model="type"
                                        :options="$typeOptions"
                                        placeholder="-- Pilih Type --"
                                        :class="'kc-form-field__input'.($errors->has('type') ? ' kc-form-field__input--error' : '')"
                                    />
                                    @error('type')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="is_active" class="kc-form-field__label">Status</label>
                                    <label class="kc-checkbox">
                                        <input type="checkbox" id="is_active" wire:model="is_active" class="kc-checkbox__input">
                                        <span>Aktif</span>
                                    </label>
                                    <p class="kc-form-field__hint">Nonaktif wajib untuk Station bertipe Other.</p>
                                    @error('is_active')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="code" class="kc-form-field__label">Kode</label>
                                    <input
                                        type="text"
                                        id="code"
                                        wire:model="form.code"
                                        class="kc-form-field__input @error('form.code') kc-form-field__input--error @enderror"
                                    >
                                    @error('form.code')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="description" class="kc-form-field__label">Deskripsi</label>
                                    <textarea
                                        id="description"
                                        wire:model="form.description"
                                        rows="3"
                                        class="kc-form-field__input @error('form.description') kc-form-field__input--error @enderror"
                                    ></textarea>
                                    @error('form.description')
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
           resources/views/livewire/master-data/kelola-business-unit.blade.php)
           since the backend scaffold has no frontend build pipeline yet —
           see implementation_notes. `kc-` prefix (Kelola Corporate/
           Company/Business-Unit/Station — shared prefix reused across all
           four master-data screens) keeps these rules scoped/non-colliding
           with other screens' inlined styles; this class set is reused
           verbatim (not duplicated-and-renamed) from
           kelola-business-unit.blade.php to keep all four master-data
           screens visually consistent — kc-filter__* is copied from that
           same screen's Company filter dropdown pattern. Two NEW classes
           not present in the business-unit precedent are added at the
           end of this block: `kc-badge` (the Aktif/Nonaktif status pill,
           this screen's is_active column has no equivalent in
           Corporate/Company/Business-Unit) and `kc-checkbox` (the Status
           toggle in the form — none of the three prior master-data
           screens have a boolean field, so no existing kc- checkbox class
           to reuse). */
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

        .kc-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .kc-badge--active {
            background: #dcfce7;
            color: #15803d;
        }

        .kc-badge--inactive {
            background: #f3f4f6;
            color: var(--kc-text-muted);
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

        .kc-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--kc-text);
            padding: 9px 0;
        }

        .kc-checkbox__input {
            width: 16px;
            height: 16px;
            accent-color: var(--kc-brand);
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
