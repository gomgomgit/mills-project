<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,toggleStatus">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola User & Role</h2>
            <p class="kc-page__subtitle">Kelola akun user sistem — role, mill terkait, dan status aktif/nonaktif</p>
        </div>

        <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary">
            + Tambah User
        </button>
    </div>

    @if ($statusErrorMessage)
        <div class="kc-alert" role="alert">
            {{ $statusErrorMessage }}
        </div>
    @endif

    <div class="kc-filter">
        <label for="filterRole" class="kc-filter__label">Filter Role</label>
        <x-searchable-select
            id="filterRole"
            wire:model.live="filterRole"
            :options="collect($roleOptions)->map(fn ($role) => ['value' => $role->value, 'label' => ucfirst(str_replace('_', ' ', $role->value))])->all()"
            placeholder="Semua Role"
            class="kc-form-field__input kc-filter__select"
        />

        <label for="filterBusinessUnitId" class="kc-filter__label">Filter Business Unit</label>
        <x-searchable-select
            id="filterBusinessUnitId"
            wire:model.live="filterBusinessUnitId"
            :options="collect($businessUnitOptions)->map(fn ($bu) => ['value' => $bu->id, 'label' => $bu->name])->all()"
            placeholder="Semua Business Unit"
            class="kc-form-field__input kc-filter__select"
        />
    </div>

    <div class="kc-table-wrap">
        <table class="kc-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Business Unit</th>
                    <th>Status</th>
                    <th>Dibuat Pada</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="kc-table__row" wire:key="user-{{ $user['id'] }}">
                        <td>{{ $user['username'] }}</td>
                        <td>{{ $user['name'] }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $user['role'])) }}</td>
                        <td>{{ $user['business_unit_name'] ?? '-' }}</td>
                        <td>
                            @if ($user['is_active'])
                                <span class="kc-badge kc-badge--active">Aktif</span>
                            @else
                                <span class="kc-badge kc-badge--inactive">Nonaktif</span>
                            @endif
                        </td>
                        <td>{{ $user['created_at'] ? \Illuminate\Support\Carbon::parse($user['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                        <td class="kc-table__actions">
                            <button type="button" wire:click="openEditForm('{{ $user['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                Edit
                            </button>
                            @if ($user['is_active'])
                                <button
                                    type="button"
                                    wire:click="toggleStatus('{{ $user['id'] }}', false)"
                                    class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text"
                                    @if ($user['id'] === $currentUserId) disabled title="Anda tidak dapat menonaktifkan akun sendiri" @endif
                                >
                                    Nonaktifkan
                                </button>
                            @else
                                <button type="button" wire:click="toggleStatus('{{ $user['id'] }}', true)" class="kc-button kc-button--ghost kc-button--sm">
                                    Aktifkan
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="7">
                            <div class="kc-empty">
                                <div class="kc-empty__illustration" aria-hidden="true">&#128100;</div>
                                <p class="kc-empty__title">Belum ada user</p>
                                <p class="kc-empty__subtitle">Klik "Tambah User" untuk menambahkan data baru.</p>
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
        <div class="kc-modal-backdrop" wire:key="user-form-backdrop">
            <div class="kc-modal" role="dialog" aria-modal="true">
                <h3 class="kc-modal__title">
                    {{ $editingId !== null ? 'Edit User' : 'Tambah User' }}
                </h3>

                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert">
                        {{ $formErrorMessage }}
                    </div>
                @endif

                <form wire:submit="save" novalidate>
                    <div class="kc-modal__body">
                        <div class="kc-form-section">
                            <div class="kc-form-grid">
                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="username" class="kc-form-field__label">
                                        Username <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="username"
                                        wire:model="form.username"
                                        class="kc-form-field__input @error('form.username') kc-form-field__input--error @enderror"
                                        autofocus
                                        @if ($editingId !== null) disabled @endif
                                    >
                                    @error('form.username')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="name" class="kc-form-field__label">
                                        Nama Lengkap <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input type="text" id="name" wire:model="form.name" class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror">
                                    @error('form.name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="role" class="kc-form-field__label">
                                        Role <span class="kc-form-field__required">*</span>
                                    </label>
                                    <x-searchable-select
                                        id="role"
                                        wire:model.live="form.role"
                                        :options="collect($roleOptions)->map(fn ($role) => ['value' => $role->value, 'label' => ucfirst(str_replace('_', ' ', $role->value))])->all()"
                                        placeholder="-- Pilih Role --"
                                        :class="'kc-form-field__input'.($errors->has('form.role') ? ' kc-form-field__input--error' : '')"
                                    />
                                    @error('form.role')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($form['role'] !== 'admin')
                                    <div class="kc-form-field">
                                        <label for="business_unit_id" class="kc-form-field__label">
                                            Business Unit <span class="kc-form-field__required">*</span>
                                        </label>
                                        <x-searchable-select
                                            id="business_unit_id"
                                            wire:model="form.business_unit_id"
                                            :options="collect($businessUnitOptions)->map(fn ($bu) => ['value' => $bu->id, 'label' => $bu->name])->all()"
                                            placeholder="-- Pilih Business Unit --"
                                            empty-message="Belum ada Business Unit. Buat Business Unit terlebih dahulu."
                                            :class="'kc-form-field__input'.($errors->has('form.business_unit_id') ? ' kc-form-field__input--error' : '')"
                                        />
                                        @error('form.business_unit_id')
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                @if ($editingId === null)
                                    <div class="kc-form-field kc-form-field--span2">
                                        <label for="password" class="kc-form-field__label">
                                            Password Awal <span class="kc-form-field__required">*</span>
                                        </label>
                                        <input type="password" id="password" wire:model="form.password" class="kc-form-field__input @error('form.password') kc-form-field__input--error @enderror">
                                        <p class="kc-form-field__hint">Minimal 6 karakter. User dapat menggantinya sendiri setelah login pertama.</p>
                                        @error('form.password')
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
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
        /* kc-* class set reused verbatim from
           resources/views/livewire/master-data/kelola-business-unit.blade.php
           (kept identical across all screens using this pattern for visual
           consistency — see that file's own docblock comment). Two
           additions specific to this screen: kc-badge__* (status pill,
           no equivalent in the master-data screens since none of them have
           an is_active concept). */
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
            flex-wrap: wrap;
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
            color: #16a34a;
        }

        .kc-badge--inactive {
            background: #f3f4f6;
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
