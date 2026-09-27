@php
    /**
     * screen-128--kelola-periode-pelaporan — Kelola Periode Pelaporan.
     *
     * ONE PARENT ROW PER PERIOD, EXPANDABLE. Since 2026-09-25 a period holds
     * one status per station type ({@see App\Models\PeriodStation}), and a
     * mill can have 19 of them — rendering every station row of every period
     * would turn a 20-period page into a 380-row wall. The parent row carries
     * the summary PeriodService::toRow() computes (`status_summary`,
     * `station_count`, `closed_station_count`); the station rows live in a
     * second <tr> that is rendered only while the period's id is in
     * $expandedPeriodIds.
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

<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete,confirmClose,confirmReopen,askClose,askOpen,confirmOpen">
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
                    @php($isExpanded = in_array($period['id'], $expandedPeriodIds, true))
                    <tr class="kc-table__row" wire:key="period-{{ $period['id'] }}" data-testid="period-row-{{ $period['id'] }}">
                        <td>
                            <button
                                type="button"
                                wire:click="toggleExpanded('{{ $period['id'] }}')"
                                class="kc-expand"
                                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                data-testid="expand-button-{{ $period['id'] }}"
                            >
                                <span class="kc-expand__caret" aria-hidden="true">{{ $isExpanded ? '&#9662;' : '&#9656;' }}</span>
                                <span class="kc-expand__name">{{ $period['name'] }}</span>
                            </button>
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

                    @if ($isExpanded)
                        <tr class="kc-table__row kc-table__row--stations" wire:key="period-stations-{{ $period['id'] }}">
                            <td colspan="7">
                                @if ($period['station_count'] === 0)
                                    <p class="kc-substation__empty" data-testid="period-stations-empty-{{ $period['id'] }}">
                                        Periode ini belum punya satu pun baris stasiun — mill-nya belum memiliki stasiun aktif.
                                        Tambahkan stasiun pada mill tersebut, lalu simpan ulang periode ini untuk mendaftarkannya.
                                    </p>
                                @else
                                    <table class="kc-subtable" data-testid="period-stations-{{ $period['id'] }}">
                                        <thead>
                                            <tr>
                                                <th>Jenis Stasiun</th>
                                                <th>Status</th>
                                                <th>Ditutup Oleh</th>
                                                <th>Waktu Ditutup</th>
                                                <th class="kc-table__actions-head">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($period['stations'] as $station)
                                                <tr wire:key="period-station-{{ $station['id'] }}" data-testid="period-station-row-{{ $station['id'] }}">
                                                    <td>{{ $station['station_type_label'] }}</td>
                                                    <td>
                                                        <span
                                                            class="kc-badge {{ $statusBadgeClass[$station['status']] ?? 'kc-badge--neutral' }}"
                                                            data-testid="station-status-badge-{{ $station['id'] }}"
                                                        >
                                                            {{ $statusLabels[$station['status']] ?? $station['status'] }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $station['closed_by_name'] ?? '—' }}</td>
                                                    <td>{{ $formatDateTime($station['closed_at']) }}</td>
                                                    <td class="kc-table__actions">
                                                        {{--
                                                            EVERY ACTION HERE CARRIES $station['id'] — a
                                                            `period_stations` id, which is what
                                                            PeriodClosureService::close()/reopen()/open()/
                                                            unverifiedCount() take. Passing the period's id
                                                            would either 404 or, worse, hit some other
                                                            period's station row.
                                                        --}}
                                                        @if ($confirmingReopenStationId === $station['id'])
                                                            <span class="kc-confirm">
                                                                <span class="kc-confirm__label">
                                                                    Buka kembali {{ $station['station_type_label'] }}?
                                                                </span>
                                                                <button type="button" wire:click="confirmReopen" class="kc-button kc-button--primary kc-button--sm" data-testid="confirm-reopen-button">
                                                                    Ya, Buka Kembali
                                                                </button>
                                                                <button type="button" wire:click="cancelReopen" class="kc-button kc-button--ghost kc-button--sm">
                                                                    Batal
                                                                </button>
                                                            </span>
                                                        @elseif ($station['status'] === 'closed')
                                                            <button
                                                                type="button"
                                                                wire:click="askReopen('{{ $station['id'] }}')"
                                                                class="kc-button kc-button--ghost kc-button--sm"
                                                                data-testid="station-reopen-button-{{ $station['id'] }}"
                                                            >
                                                                Buka Kembali
                                                            </button>
                                                        @elseif ($station['status'] === 'draft')
                                                            {{--
                                                                "Buka Stasiun" is offered on DRAFT stations
                                                                ONLY. An already-open station has nothing to
                                                                open, and a closed one gets "Buka Kembali" —
                                                                a different action that also clears
                                                                closed_by/closed_at.
                                                            --}}
                                                            <button
                                                                type="button"
                                                                wire:click="askOpen('{{ $station['id'] }}')"
                                                                class="kc-button kc-button--primary kc-button--sm"
                                                                data-testid="station-open-button-{{ $station['id'] }}"
                                                            >
                                                                Buka Stasiun
                                                            </button>
                                                        @else
                                                            <button
                                                                type="button"
                                                                wire:click="askClose('{{ $station['id'] }}')"
                                                                class="kc-button kc-button--warning kc-button--sm"
                                                                data-testid="station-close-button-{{ $station['id'] }}"
                                                            >
                                                                Tutup Stasiun
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endif
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

    @if ($closingStationId !== null && $closingStation !== null)
        <x-modal
            :title="'Tutup Stasiun — '.$closingStation['station_type_label']"
            backdrop-key="period-close-backdrop"
        >
            {{--
                EVERY FIGURE AND LABEL IN THIS DIALOG COMES FROM ONE STATION
                ROW. The count was fetched with the same
                $closingStationId that confirmClose() passes to close(), so
                the warning cannot be about a station other than the one being
                closed.
            --}}
            <div class="kc-alert kc-alert--warning" role="alert" data-testid="unverified-warning">
                <strong data-testid="unverified-count">
                    {{ $closingUnverifiedCount }} data {{ $closingStation['station_type_label'] }} belum terverifikasi dalam rentang periode ini
                </strong>
                <p class="kc-dialog__note">
                    Setelah stasiun ini ditutup, verifikasi ikut terkunci — data tersebut akan tetap berstatus
                    belum terverifikasi sampai stasiun ini dibuka kembali.
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
                Menutup <strong>{{ $closingStation['station_type_label'] }}</strong> pada periode
                <strong>{{ $closingStation['period_name'] }}</strong>
                ({{ $formatDate($closingStation['start_date']) }} &ndash; {{ $formatDate($closingStation['end_date']) }})
                di <strong>{{ $closingStation['business_unit_name'] ?? '-' }}</strong>
                akan mengunci data stasiun tersebut yang tanggal kejadiannya berada di dalam rentang itu:
                tidak bisa diinput baru, tidak bisa diubah, dan tidak bisa diverifikasi.
                Jenis stasiun lain pada periode ini tidak terpengaruh.
            </p>

            <x-slot:actions>
                <button type="button" wire:click="cancelClose" class="kc-button kc-button--ghost" data-testid="cancel-close-button">
                    Batal
                </button>
                <button type="button" wire:click="confirmClose" class="kc-button kc-button--warning" wire:loading.attr="disabled" wire:target="confirmClose" data-testid="confirm-close-button">
                    Ya, Tutup Stasiun
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    @if ($openingStationId !== null)
        <x-modal
            :title="'Buka Stasiun'.($openingStation ? ' — '.$openingStation['station_type_label'] : '')"
            backdrop-key="period-open-backdrop"
        >
            <div data-testid="open-period-dialog">
                <p class="kc-dialog__note">
                    @if ($openingStation)
                        <strong>{{ $openingStation['station_type_label'] }}</strong> pada periode
                        <strong>{{ $openingStation['period_name'] }}</strong>
                        ({{ $formatDate($openingStation['start_date']) }} &ndash; {{ $formatDate($openingStation['end_date']) }})
                        di <strong>{{ $openingStation['business_unit_name'] ?? '-' }}</strong>
                        akan dinyatakan resmi berjalan.
                    @else
                        Stasiun ini akan dinyatakan resmi berjalan.
                    @endif
                </p>

                <p class="kc-dialog__note">
                    Statusnya berubah dari <strong>Draft</strong> menjadi <strong>Terbuka</strong> dan
                    <strong>tidak dapat dikembalikan ke Draft</strong>. Periode tetap dapat diubah
                    dan dihapus selama belum ada stasiun yang ditutup. Tidak ada data stasiun yang berubah,
                    dan jenis stasiun lain pada periode ini tidak tersentuh.
                </p>
            </div>

            <x-slot:actions>
                <button type="button" wire:click="cancelOpen" class="kc-button kc-button--ghost" data-testid="cancel-open-period">
                    Batal
                </button>
                <button type="button" wire:click="confirmOpen" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="confirmOpen" data-testid="confirm-open-period">
                    Ya, Buka Stasiun
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

        /* The period name IS the expand control — a separate chevron next to a
           plain name gives two targets for one job and a smaller hit area. */
        .kc-expand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0;
            background: none;
            border: none;
            font: inherit;
            color: inherit;
            cursor: pointer;
            text-align: left;
        }

        .kc-expand__caret {
            color: var(--kc-text-muted);
            font-size: 11px;
            width: 10px;
        }

        .kc-expand__name {
            font-weight: 600;
        }

        .kc-expand:hover .kc-expand__name {
            color: var(--kc-brand);
        }

        .kc-table__row--stations > td {
            padding: 0 16px 14px 34px;
            background: #f9fafb;
        }

        .kc-subtable {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
            border: 1px solid var(--kc-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .kc-subtable th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            color: var(--kc-text-muted);
            border-bottom: 1px solid var(--kc-border);
            white-space: nowrap;
        }

        .kc-subtable td {
            padding: 8px 12px;
            border-bottom: 1px solid #eef0f2;
            vertical-align: middle;
        }

        .kc-subtable tbody tr:last-child td {
            border-bottom: none;
        }

        .kc-substation__empty {
            margin: 0;
            padding: 12px 0 0;
            font-size: 13px;
            color: var(--kc-text-muted);
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
