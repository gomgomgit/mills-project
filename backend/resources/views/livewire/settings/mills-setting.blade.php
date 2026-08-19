<div class="ms-page" wire:loading.class="ms-page--busy" wire:target="save,setStationIcon,updatedSelectedBusinessUnitId">
    <div class="ms-page__header">
        <div>
            <h2 class="ms-page__title">Mills Setting</h2>
            <p class="ms-page__subtitle">Atur nama aplikasi, logo, gambar halaman utama, icon station, dan jumlah cages untuk mill Anda.</p>
        </div>
    </div>

    @if ($successMessage)
        <div class="ms-alert ms-alert--success" role="status">{{ $successMessage }}</div>
    @endif

    @if ($formErrorMessage)
        <div class="ms-alert ms-alert--error" role="alert">{{ $formErrorMessage }}</div>
    @endif

    @if ($isAdmin)
        <div class="ms-filter">
            <label for="selectedBusinessUnitId" class="ms-filter__label">Pilih Mill</label>
            <select id="selectedBusinessUnitId" wire:model.live="selectedBusinessUnitId" class="ms-form-field__input ms-filter__select">
                <option value="">— Pilih mill —</option>
                @foreach ($businessUnitOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($selectedBusinessUnitId === '')
        <div class="ms-empty">
            <div class="ms-empty__illustration" aria-hidden="true">&#9881;&#65039;</div>
            <p class="ms-empty__title">Pilih mill untuk mulai mengatur Mills Setting</p>
        </div>
    @else
        <form wire:submit.prevent="save">
            <div class="ms-form-section">
                <h4 class="ms-form-section__title">Identitas Aplikasi</h4>
                <div class="ms-form-grid">
                    <div class="ms-form-field">
                        <label for="app_name" class="ms-form-field__label">Nama Aplikasi</label>
                        <input
                            type="text"
                            id="app_name"
                            wire:model="app_name"
                            class="ms-form-field__input @error('app_name') ms-form-field__input--error @enderror"
                        >
                        @error('app_name')
                            <p class="ms-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="ms-form-field">
                        <label for="jumlah_cages" class="ms-form-field__label">
                            Jumlah Cages <span class="ms-form-field__required">*</span>
                        </label>
                        <input
                            type="number"
                            min="1"
                            id="jumlah_cages"
                            wire:model="jumlah_cages"
                            class="ms-form-field__input @error('jumlah_cages') ms-form-field__input--error @enderror"
                        >
                        <p class="ms-form-field__hint">Menentukan jumlah kolom checklist pada grid Cages Tipped Time (Form Cages Track).</p>
                        @error('jumlah_cages')
                            <p class="ms-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="ms-form-field ms-form-field--span2">
                        <label class="ms-form-field__label">Logo</label>
                        <div class="ms-image-field">
                            @if ($logo && in_array(strtolower($logo->getClientOriginalExtension()), $previewableExtensions, true))
                                <img src="{{ $logo->temporaryUrl() }}" alt="Preview logo" class="ms-image-field__preview">
                            @elseif ($existingLogoUrl)
                                <img src="{{ $existingLogoUrl }}" alt="Logo saat ini" class="ms-image-field__preview">
                            @else
                                <span class="ms-image-field__placeholder" aria-hidden="true">&#128247;</span>
                            @endif
                            <div class="ms-image-field__control">
                                <input type="file" wire:model="logo" accept="image/png,image/jpeg" class="ms-form-field__input">
                                @error('logo')
                                    <p class="ms-form-field__error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="ms-form-field ms-form-field--span2">
                        <label class="ms-form-field__label">Gambar Halaman Utama (Home Mobile)</label>
                        <div class="ms-image-field">
                            @if ($home_page_image && in_array(strtolower($home_page_image->getClientOriginalExtension()), $previewableExtensions, true))
                                <img src="{{ $home_page_image->temporaryUrl() }}" alt="Preview gambar halaman utama" class="ms-image-field__preview ms-image-field__preview--wide">
                            @elseif ($existingHomePageImageUrl)
                                <img src="{{ $existingHomePageImageUrl }}" alt="Gambar halaman utama saat ini" class="ms-image-field__preview ms-image-field__preview--wide">
                            @else
                                <span class="ms-image-field__placeholder ms-image-field__placeholder--wide" aria-hidden="true">&#128247;</span>
                            @endif
                            <div class="ms-image-field__control">
                                <input type="file" wire:model="home_page_image" accept="image/png,image/jpeg" class="ms-form-field__input">
                                @error('home_page_image')
                                    <p class="ms-form-field__error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ms-form-actions">
                <button type="submit" class="ms-button ms-button--primary" wire:loading.attr="disabled" wire:target="save">Simpan</button>
            </div>
        </form>

        <div class="ms-form-section ms-form-section--stations">
            <h4 class="ms-form-section__title">Icon Station</h4>

            @if (count($stations) === 0)
                <div class="ms-empty">
                    <div class="ms-empty__illustration" aria-hidden="true">&#127981;</div>
                    <p class="ms-empty__title">Belum ada station terdaftar</p>
                    <p class="ms-empty__subtitle">Tambahkan station terlebih dahulu melalui Kelola Station.</p>
                </div>
            @else
                <div class="ms-table-wrap">
                    <table class="ms-table">
                        <thead class="ms-table__head">
                            <tr>
                                <th>Nama Station</th>
                                <th>Tipe</th>
                                <th>Icon</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stations as $station)
                                <tr class="ms-table__row" wire:key="station-icon-{{ $station['id'] }}">
                                    <td>{{ $station['name'] }}</td>
                                    <td>{{ ucfirst(str_replace('-', ' ', $station['type'])) }}</td>
                                    <td>
                                        <select
                                            class="ms-form-field__input"
                                            wire:change="setStationIcon('{{ $station['id'] }}', $event.target.value)"
                                        >
                                            <option value="" @selected($station['icon'] === null)>Default</option>
                                            @foreach ($iconOptions as $icon)
                                                <option value="{{ $icon['value'] }}" @selected($station['icon'] === $icon['value'])>{{ $icon['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Inlined here (same
           approach as every other Livewire view in this codebase, e.g.
           resources/views/livewire/master-data/kelola-business-unit.blade.php)
           since the backend scaffold has no frontend build pipeline yet.
           `ms-` prefix (Mills Setting) — class set adapted from
           kelola-business-unit.blade.php's `kc-` set (form-field/button/
           alert/empty/table patterns reused verbatim, renamed), plus new
           `ms-image-field`/`ms-filter` rules specific to this screen's
           dual-image-upload + Admin mill-picker layout. */
        .ms-page {
            --ms-brand: #249360;
            --ms-brand-hover: #1d7a4e;
            --ms-destructive: #DC2626;
            --ms-success: #16A34A;
            --ms-text: #1f2937;
            --ms-text-muted: #6b7280;
            --ms-border: #d1d5db;
            --ms-radius-input: 6px;
            --ms-radius-button: 8px;
            color: var(--ms-text);
        }

        .ms-page--busy {
            opacity: 0.85;
        }

        .ms-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .ms-page__subtitle {
            margin: 0 0 16px;
            font-size: 14px;
            color: var(--ms-text-muted);
        }

        .ms-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--ms-radius-input);
            font-size: 14px;
        }

        .ms-alert--error {
            background: #fef2f2;
            border: 1px solid var(--ms-destructive);
            color: var(--ms-destructive);
        }

        .ms-alert--success {
            background: #f0fdf4;
            border: 1px solid var(--ms-success);
            color: var(--ms-success);
        }

        .ms-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .ms-filter__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--ms-text-muted);
        }

        .ms-filter__select {
            max-width: 320px;
        }

        .ms-form-section {
            margin-bottom: 24px;
        }

        .ms-form-section--stations {
            padding-top: 8px;
            border-top: 1px solid var(--ms-border);
        }

        .ms-form-section__title {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--ms-text-muted);
            padding-bottom: 6px;
            border-bottom: 1px solid var(--ms-border);
        }

        .ms-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 16px;
        }

        .ms-form-field--span2 {
            grid-column: span 2;
        }

        .ms-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ms-form-field__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--ms-text-muted);
        }

        .ms-form-field__required {
            color: var(--ms-destructive);
        }

        .ms-form-field__input {
            padding: 9px 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--ms-text);
            border: 1px solid var(--ms-border);
            border-radius: var(--ms-radius-input);
            background: #fff;
        }

        .ms-form-field__input:focus {
            outline: none;
            border-color: var(--ms-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .ms-form-field__input--error {
            border-color: var(--ms-destructive);
        }

        .ms-form-field__error {
            margin: 0;
            font-size: 12px;
            color: var(--ms-destructive);
        }

        .ms-form-field__hint {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--ms-text-muted);
        }

        .ms-image-field {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ms-image-field__preview {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--ms-border);
            flex-shrink: 0;
        }

        .ms-image-field__preview--wide {
            width: 120px;
            height: 64px;
        }

        .ms-image-field__placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 8px;
            border: 1px dashed var(--ms-border);
            font-size: 26px;
            color: var(--ms-text-muted);
            flex-shrink: 0;
        }

        .ms-image-field__placeholder--wide {
            width: 120px;
        }

        .ms-image-field__control {
            flex: 1;
        }

        .ms-form-actions {
            display: flex;
            justify-content: flex-end;
        }

        .ms-button {
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--ms-radius-button);
            border: none;
            cursor: pointer;
        }

        .ms-button--primary {
            background: var(--ms-brand);
            color: #fff;
        }

        .ms-button--primary:hover:not(:disabled) {
            background: var(--ms-brand-hover);
        }

        .ms-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .ms-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--ms-border);
            border-radius: 10px;
            background: #fff;
        }

        .ms-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .ms-table__head th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--ms-text-muted);
            border-bottom: 1px solid var(--ms-border);
            white-space: nowrap;
        }

        .ms-table__row td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--ms-border);
            vertical-align: middle;
        }

        .ms-table__row:last-child td {
            border-bottom: none;
        }

        .ms-empty {
            padding: 32px 16px;
            text-align: center;
        }

        .ms-empty__illustration {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .ms-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--ms-text);
        }

        .ms-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--ms-text-muted);
        }

        @media (max-width: 560px) {
            .ms-form-grid {
                grid-template-columns: 1fr;
            }

            .ms-form-field--span2 {
                grid-column: span 1;
            }
        }
    </style>
</div>
