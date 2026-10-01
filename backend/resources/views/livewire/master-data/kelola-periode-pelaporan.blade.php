@php
    /**
     * screen-128--kelola-periode-pelaporan — Kelola Periode Pelaporan.
     *
     * ONE ROW PER PERIOD, AND NOTHING ELSE (2026-09-27). Since 2026-09-25 a
     * period holds one status per station type ({@see App\Models\PeriodStation}),
     * and a mill can have 19 of them. Those rows used to hang under each
     * period in an expandable second <tr>, with every per-station action
     * inside it; they now have their own page — screen-142, route
     * /master-data/periods/{id} — and the period NAME IS THE LINK to it.
     * What is left here is the summary PeriodService::toRow() computes
     * (`status_summary`, `station_count`, `closed_station_count`).
     *
     * NOT ONE PER-STATION ACTION LIVES ON THIS SCREEN ANY MORE: no Tutup
     * Stasiun, no Buka Stasiun, no Buka Kembali, no close-confirmation dialog.
     * Adding one back here would mean this screen needs a `period_stations`
     * id, which is exactly the confusion the split removed.
     *
     * NO PARENT-LEVEL status / station_type / closed_by / closed_at EXISTS
     * ANY MORE — asking a $period row for one of those keys is a bug, not a
     * shortcut. Use `status_summary` for the badge and the station rows for
     * anything per-station.
     *
     * Status vocabulary is Indonesian, per the business spec and the Phase 2
     * mock: draft => "Draft" (neutral), open => "Terbuka" (success), closed =>
     * "Tertutup" (destructive). The stored value stays English
     * (period_stations.status = draft|open|closed) — only the label is
     * translated. The two summary-only values have their own labels: `mixed`
     * (stations in different statuses) and `empty` (no station rows at all).
     */
    $statusLabels = ['draft' => 'Draft', 'open' => 'Terbuka', 'closed' => 'Tertutup'];
    $summaryLabels = $statusLabels + ['mixed' => 'Campuran', 'empty' => 'Tanpa Stasiun'];
    $statusBadgeClass = [
        'draft' => 'kc-badge--neutral',
        'open' => 'kc-badge--success',
        'closed' => 'kc-badge--closed',
        'mixed' => 'kc-badge--mixed',
        'empty' => 'kc-badge--neutral',
    ];
    $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '-';
    $formatDateTime = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y H:i') : '—';
    $immutableHint = 'Ada stasiun yang sudah ditutup pada periode ini. Buka kembali stasiun tersebut terlebih dahulu.';
@endphp

<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Periode Pelaporan</h2>
            <p class="kc-page__subtitle">
                Rentang tanggal pelaporan resmi per mill — satuan periode untuk seluruh laporan Full Cycle per Stasiun.
                Satu periode mencakup seluruh jenis stasiun di mill tersebut, dan tiap stasiun ditutup sendiri-sendiri.
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
            <label for="filterStatus" class="kc-filter__label">Status Stasiun</label>
            {{--
                The filter matches a period with AT LEAST ONE station in the
                chosen status (PeriodService::listPeriods()), so one period can
                appear under two values — the label says "Status Stasiun" to
                stop it being read as a status of the period itself.
            --}}
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

    {{--
        Panel "Periode Terbuka Hari Ini per Mill" (business spec v5).

        LETAKNYA SENGAJA DI SINI — sesudah baris filter, sebelum tabel. Panel ini
        bereaksi pada filter Business Unit, jadi ia harus berada SESUDAH kontrol
        itu; di atasnya, Admin akan membacanya sebagai panel yang tidak
        dipengaruhi filter apa pun.

        "Terbuka hari ini" = DUA syarat sekaligus (stasiun berstatus open DAN
        hari ini di dalam rentang) — seluruhnya dihitung di
        PeriodService::openPeriodsByBusinessUnit(); JANGAN menurunkan ulang
        aturannya di sini.

        Baca saja: satu-satunya elemen interaktif adalah tautan nama periode.
    --}}
    <section class="kc-open-panel" data-testid="open-today-panel" aria-labelledby="kc-open-panel-heading">
        <div class="kc-open-panel__head">
            <h3 class="kc-open-panel__title" id="kc-open-panel-heading">Periode Terbuka Hari Ini per Mill</h3>
            <p class="kc-open-panel__today" data-testid="open-today-date">
                Acuan tanggal server: {{ $formatDate($panelToday) }}
            </p>
        </div>

        @if (empty($openSummary))
            <p class="kc-open-panel__none" data-testid="open-today-no-mills">
                Belum ada Business Unit terdaftar, jadi belum ada periode yang dapat dibuka.
            </p>
        @else
            <div class="kc-open-panel__grid">
                @foreach ($openSummary as $mill)
                    @php
                        // Mill tanpa periode terbuka TETAP mendapat card, hanya diredupkan.
                        // Itu keadaan paling berguna di panel ini, bukan slot kosong yang
                        // layak disembunyikan.
                        $hasOpen = ! empty($mill['open_periods']);
                    @endphp
                    <article
                        @class(['kc-open-card', 'kc-open-card--empty' => ! $hasOpen])
                        data-testid="open-today-card-{{ $mill['business_unit_id'] }}"
                    >
                        <h4 class="kc-open-card__mill">{{ $mill['business_unit_name'] }}</h4>

                        @if ($hasOpen)
                            {{--
                                Normalnya satu card memuat SATU periode, karena rentang tidak
                                boleh tumpang tindih per mill. Tetap di-loop: aturan itu
                                ditegakkan service, bukan constraint database, jadi bila
                                ternyata ada dua, keduanya harus terlihat.
                            --}}
                            <ul class="kc-open-card__list">
                                @foreach ($mill['open_periods'] as $period)
                                    <li class="kc-open-item" data-testid="open-today-item-{{ $period['id'] }}">
                                        <a
                                            href="{{ route('master-data.periods.detail', $period['id']) }}"
                                            class="kc-link kc-open-item__name"
                                            data-testid="open-today-link-{{ $period['id'] }}"
                                            wire:navigate
                                        >{{ $period['name'] }}</a>
                                        <span class="kc-open-item__range">
                                            {{ $formatDate($period['start_date']) }} – {{ $formatDate($period['end_date']) }}
                                        </span>
                                        <span
                                            class="kc-open-item__count"
                                            data-testid="open-today-count-{{ $period['id'] }}"
                                        >{{ $period['open_station_count'] }} dari {{ $period['station_count'] }} stasiun terbuka</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p
                                class="kc-open-card__empty"
                                data-testid="open-today-empty-{{ $mill['business_unit_id'] }}"
                            >Tidak ada periode terbuka hari ini — tidak ada stasiun di mill ini yang dapat menerima input.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="kc-table-wrap">
        <table class="kc-table" data-testid="period-table">
            <thead class="kc-table__head">
                <tr>
                    <th>Nama</th>
                    <th>Business Unit</th>
                    <th>Tanggal Mulai</th>
                    <th>Tanggal Selesai</th>
                    <th>Stasiun</th>
                    <th>Status Stasiun</th>
                    <th class="kc-table__actions-head">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr class="kc-table__row" wire:key="period-{{ $period['id'] }}" data-testid="period-row-{{ $period['id'] }}">
                        <td>
                            {{--
                                The period name IS the way in to screen-142,
                                where that period's stations and every
                                per-station action live. A real <a> with a real
                                href, so it can be opened in a new tab and
                                bookmarked — the detail route takes the period
                                id and nothing else.
                            --}}
                            <a
                                href="{{ route('master-data.periods.detail', $period['id']) }}"
                                wire:navigate
                                class="kc-link"
                                data-testid="period-link-{{ $period['id'] }}"
                            >{{ $period['name'] }}</a>
                        </td>
                        <td>{{ $period['business_unit_name'] ?? '-' }}</td>
                        <td>{{ $formatDate($period['start_date']) }}</td>
                        <td>{{ $formatDate($period['end_date']) }}</td>
                        <td data-testid="station-summary-{{ $period['id'] }}">
                            @if ($period['station_count'] === 0)
                                <span class="kc-muted">Belum ada stasiun</span>
                            @else
                                {{ $period['station_count'] }} stasiun
                                @if ($period['closed_station_count'] > 0)
                                    <span class="kc-muted">&middot; {{ $period['closed_station_count'] }} tertutup</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <span
                                class="kc-badge {{ $statusBadgeClass[$period['status_summary']] ?? 'kc-badge--neutral' }}"
                                data-testid="status-summary-{{ $period['id'] }}"
                            >
                                {{ $summaryLabels[$period['status_summary']] ?? $period['status_summary'] }}
                            </span>
                        </td>
                        <td class="kc-table__actions">
                            {{--
                                Edit and Hapus are driven by `is_immutable` —
                                the exact condition PeriodService::update()/
                                delete() refuse on (409
                                PERIOD_CLOSED_IMMUTABLE). They are DISABLED,
                                not hidden: a period with 1 closed station out
                                of 19 must show that editing is blocked rather
                                than make the Admin hunt for a vanished button.
                                The rule is never re-derived here.
                            --}}
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
                            @else
                                <button
                                    type="button"
                                    wire:click="openEditForm('{{ $period['id'] }}')"
                                    class="kc-button kc-button--ghost kc-button--sm"
                                    @disabled($period['is_immutable'])
                                    @if ($period['is_immutable']) title="{{ $immutableHint }}" @endif
                                    data-testid="edit-button-{{ $period['id'] }}"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    wire:click="askDelete('{{ $period['id'] }}')"
                                    class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text"
                                    @disabled($period['is_immutable'])
                                    @if ($period['is_immutable']) title="{{ $immutableHint }}" @endif
                                    data-testid="delete-button-{{ $period['id'] }}"
                                >
                                    Hapus
                                </button>
                            @endif
                        </td>
                    </tr>

                @empty
                    <tr class="kc-table__row kc-table__row--static">
                        <td colspan="7">
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
            {{--
                kc-pagination__summary SENGAJA tidak punya aturan CSS: induknya
                .kc-pagination sudah menetapkan font-size dan warna yang
                dibutuhkan, jadi ia penanda semantik murni. Dinyatakan di sini
                supaya pemeriksaan "tiap kelas harus punya definisi" tidak
                membacanya sebagai kelas yang terlupakan.
            --}}
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
                {{--
                    THERE IS NO "Jenis Stasiun" FIELD ANY MORE. A period covers
                    its whole mill: create() registers one row per station type
                    active in that mill and update() backfills the ones added
                    since. The Admin lost a choice they used to have, so the
                    preview below tells them exactly which stations that means
                    — read from PeriodService::activeStationTypesForMill(), the
                    same call the service makes, never a second copy of it.
                --}}
                <div class="kc-form-grid">
                    <div class="kc-form-field kc-form-field--span2">
                        <label for="business_unit_id" class="kc-form-field__label">
                            Business Unit <span class="kc-form-field__required">*</span>
                        </label>
                        <x-searchable-select
                            id="business_unit_id"
                            wire:model.live="business_unit_id"
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
                </div>

                <div class="kc-preview" data-testid="station-preview">
                    @if ($business_unit_id === '')
                        <p class="kc-form-field__hint">
                            Pilih Business Unit untuk melihat jenis stasiun yang akan tercakup periode ini.
                        </p>
                    @elseif (count($stationPreview) === 0)
                        <p class="kc-form-field__hint" data-testid="station-preview-empty">
                            Mill ini belum punya stasiun aktif, jadi periode ini belum akan punya satu pun baris stasiun —
                            tidak ada yang bisa ditutup dan tidak ada data yang terkunci. Periodenya tetap tersimpan;
                            tambahkan stasiunnya lalu simpan ulang periode ini.
                        </p>
                    @else
                        <p class="kc-form-field__hint">
                            @if ($editingId !== null)
                                Periode ini mencakup {{ count($stationPreview) }} jenis stasiun aktif di mill tersebut.
                                Menyimpan akan <strong>menambahkan</strong> baris untuk jenis yang belum ada; baris yang
                                sudah ada, termasuk yang sudah tertutup, tidak diubah maupun dihapus.
                            @else
                                Periode ini otomatis mencakup {{ count($stationPreview) }} jenis stasiun aktif di mill tersebut,
                                masing-masing berstatus Draft dan dapat ditutup sendiri-sendiri.
                            @endif
                        </p>
                        <ul class="kc-preview__list">
                            @foreach ($stationPreview as $previewStation)
                                <li>
                                    {{ $previewStation['label'] }}
                                    @if ($editingId !== null && $previewStation['is_new'])
                                        <span class="kc-badge kc-badge--success" data-testid="station-preview-new-{{ $previewStation['code'] }}">Baru</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
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
                        <p class="kc-form-field__hint">Harus unik dalam satu Business Unit.</p>
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
                        <p class="kc-form-field__hint">
                            Tidak boleh lebih awal dari tanggal mulai, dan satu tanggal hanya boleh dimiliki satu periode
                            pada mill yang sama.
                        </p>
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

    <style>
        /* Design tokens — uiux-spec: brand #249360. Reused verbatim (kc-
           prefix) from kelola-production-line.blade.php so every
           master-data screen stays visually consistent; the kc-badge-* and
           kc-alert--success rules at the end are this screen's own additions
           (status-summary badges and the period name link). */
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

        .kc-muted {
            color: var(--kc-text-muted);
        }

        /* `mixed` is not "half good" — it is a state the Admin must notice,
           so it gets its own amber badge rather than reusing the neutral one
           that `draft` and `empty` share. */
        .kc-badge--mixed {
            background: #fffbeb;
            color: var(--kc-warning-hover);
            border: 1px solid var(--kc-warning);
        }

        /* The period name is the only link out of this table, so it looks
           like one rather than like a plain cell. */
        .kc-link {
            font-weight: 600;
            color: var(--kc-brand-hover);
            text-decoration: none;
        }

        .kc-link:hover {
            text-decoration: underline;
        }

        .kc-preview {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px dashed var(--kc-border);
            border-radius: var(--kc-radius-input);
            background: #f9fafb;
        }

        .kc-preview__list {
            margin: 8px 0 0;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.7;
            columns: 2;
        }

        /*
            Panel "Periode Terbuka Hari Ini per Mill".

            SETIAP kelas di bawah ini dipakai markup panel di atas, dan setiap
            kelas yang dipakai markup panel ada di bawah ini — daftarnya
            disengaja lengkap. Kelas tanpa definisi bukan sekadar kurang rapi:
            browser akan merendernya dengan gaya bawaan (ul menjorok 40px, h4
            bermargin tebal) dan cacatnya lolos seluruh test perilaku, persis
            yang terjadi pada accordion kelola-machinery 2026-10-01.

            JANGAN memakai .md-card dari dashboard/partials/report-styles.blade.php
            — partial itu tidak di-include layout master-data, jadi markupnya
            akan tampil tanpa gaya sama sekali.
        */
        .kc-open-panel {
            margin-bottom: 16px;
            padding: 16px;
            border: 1px solid var(--kc-border);
            border-radius: 10px;
            background: #fff;
        }

        .kc-open-panel__head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 4px 16px;
            margin-bottom: 14px;
        }

        .kc-open-panel__title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--kc-text);
        }

        .kc-open-panel__today {
            margin: 0;
            font-size: 12px;
            color: var(--kc-text-muted);
        }

        .kc-open-panel__none {
            margin: 0;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-open-panel__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .kc-open-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid var(--kc-border);
            border-left: 3px solid var(--kc-brand);
            border-radius: var(--kc-radius-input);
            background: #f6fdf9;
        }

        /*
            Mill tanpa periode terbuka: diredupkan, TIDAK disembunyikan. Warna
            netral dan garis kiri yang pudar supaya bedanya terbaca sekilas
            tanpa membuatnya tampak seperti galat — ketiadaan periode terbuka
            adalah keadaan yang sah, sekaligus yang paling perlu terlihat.
        */
        .kc-open-card--empty {
            border-left-color: var(--kc-border);
            background: #f9fafb;
        }

        .kc-open-card__mill {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            color: var(--kc-text);
            overflow-wrap: anywhere;
        }

        .kc-open-card__list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .kc-open-card__empty {
            margin: 0;
            font-size: 12px;
            line-height: 1.5;
            color: var(--kc-text-muted);
        }

        .kc-open-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .kc-open-item__name {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .kc-open-item__range {
            font-size: 12px;
            color: var(--kc-text-muted);
            font-variant-numeric: tabular-nums;
        }

        .kc-open-item__count {
            font-size: 12px;
            font-weight: 600;
            color: var(--kc-brand-hover);
            font-variant-numeric: tabular-nums;
        }

        @media (max-width: 767px) {
            .kc-form-grid {
                grid-template-columns: 1fr;
            }

            .kc-form-field--span2 {
                grid-column: span 1;
            }

            /* Satu kolom di layar sempit — card 240px tidak pernah dipaksa berdampingan. */
            .kc-open-panel__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</div>
