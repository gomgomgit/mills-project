@php
    /**
     * screen-142--detail-periode-pelaporan — Detail Periode Pelaporan.
     *
     * ONE PERIOD, ALL OF ITS STATIONS. Until 2026-09-27 this table was an
     * expandable second <tr> inside the period list (screen-128); it now has
     * its own page, reached by clicking the period name. The list summarises,
     * this page is where a station is actually opened, closed or reopened.
     *
     * NO PARENT-LEVEL status / station_type / closed_by / closed_at EXISTS —
     * asking $period for one of those keys is a bug, not a shortcut. Use
     * `status_summary` for the header badge and `stations[]` for anything
     * per-station.
     *
     * Status vocabulary is Indonesian and IDENTICAL to the list screen's:
     * draft => "Draft" (neutral), open => "Terbuka" (success), closed =>
     * "Tertutup" (destructive). The stored value stays English
     * (period_stations.status = draft|open|closed) — only the label is
     * translated. The two summary-only values have their own labels: `mixed`
     * ("Campuran") and `empty` ("Tanpa Stasiun").
     *
     * PER-ROW ACTIONS COME FROM $station['status'], NEVER FROM
     * $period['status_summary']: draft -> "Buka Stasiun", open -> "Tutup
     * Stasiun", closed -> "Buka Kembali". No shortcut, no way back to draft.
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

<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="save,confirmDelete,confirmClose,confirmReopen,askClose,askOpen,confirmOpen">
    <div class="kc-page__header">
        <div>
            <button type="button" wire:click="backToList" class="kc-backlink" data-testid="back-to-periods">
                &larr; Kembali ke Daftar Periode
            </button>
            <h2 class="kc-page__title">Detail Periode Pelaporan</h2>
        </div>
    </div>

    @if ($notFound || $period === null)
        {{--
            A bad link or a period another Admin deleted lands here: a plain
            explanation and a way back, never a blank page and never a 500.
        --}}
        <div class="kc-empty" data-testid="period-not-found">
            <div class="kc-empty__illustration" aria-hidden="true">&#128533;</div>
            <p class="kc-empty__title">Periode tidak ditemukan</p>
            <p class="kc-empty__subtitle">
                Periode ini tidak ada atau sudah dihapus oleh pengguna lain.
            </p>
            <button type="button" wire:click="backToList" class="kc-button kc-button--primary" data-testid="back-to-periods-empty">
                Kembali ke Daftar Periode
            </button>
        </div>
    @else
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

        <div class="kc-summary" data-testid="period-summary">
            <div class="kc-summary__main">
                <h3 class="kc-summary__name" data-testid="period-name">{{ $period['name'] }}</h3>
                <span
                    class="kc-badge {{ $statusBadgeClass[$period['status_summary']] ?? 'kc-badge--neutral' }}"
                    data-testid="status-summary-{{ $period['id'] }}"
                >
                    {{ $summaryLabels[$period['status_summary']] ?? $period['status_summary'] }}
                </span>
            </div>

            <dl class="kc-summary__grid">
                <div>
                    <dt>Business Unit</dt>
                    <dd data-testid="period-business-unit">{{ $period['business_unit_name'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Tanggal Mulai</dt>
                    <dd>{{ $formatDate($period['start_date']) }}</dd>
                </div>
                <div>
                    <dt>Tanggal Selesai</dt>
                    <dd>{{ $formatDate($period['end_date']) }}</dd>
                </div>
                <div>
                    <dt>Stasiun</dt>
                    <dd data-testid="station-summary-{{ $period['id'] }}">
                        @if ($period['station_count'] === 0)
                            <span class="kc-muted">Belum ada stasiun</span>
                        @else
                            {{ $period['station_count'] }} stasiun
                            @if ($period['closed_station_count'] > 0)
                                <span class="kc-muted">&middot; {{ $period['closed_station_count'] }} tertutup</span>
                            @endif
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="kc-summary__actions">
                {{--
                    Edit and Hapus are driven by `is_immutable` — the exact
                    condition PeriodService::update()/delete() refuse on (409
                    PERIOD_CLOSED_IMMUTABLE). They are DISABLED, not hidden: a
                    period with 1 closed station out of 19 must show that
                    editing is blocked rather than make the Admin hunt for a
                    vanished button. The rule is never re-derived here.
                --}}
                @if ($confirmingDelete)
                    <span class="kc-confirm">
                        <span class="kc-confirm__label">Yakin hapus periode ini beserta seluruh baris stasiunnya?</span>
                        <button type="button" wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete" class="kc-button kc-button--danger kc-button--sm" data-testid="confirm-delete-button">
                            <x-busy-label target="confirmDelete" busy="Menghapus…">Ya, Hapus</x-busy-label>
                        </button>
                        <button type="button" wire:click="cancelDelete" wire:loading.attr="disabled" wire:target="confirmDelete" class="kc-button kc-button--ghost kc-button--sm">
                            Batal
                        </button>
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="openEditForm"
                        class="kc-button kc-button--ghost kc-button--sm"
                        @disabled($period['is_immutable'])
                        @if ($period['is_immutable']) title="{{ $immutableHint }}" @endif
                        data-testid="edit-button-{{ $period['id'] }}"
                    >
                        Edit Periode
                    </button>
                    <button
                        type="button"
                        wire:click="askDelete"
                        class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text"
                        @disabled($period['is_immutable'])
                        @if ($period['is_immutable']) title="{{ $immutableHint }}" @endif
                        data-testid="delete-button-{{ $period['id'] }}"
                    >
                        Hapus Periode
                    </button>
                @endif
            </div>
        </div>

        <h3 class="kc-section-title">Stasiun Periode</h3>

        @if ($period['station_count'] === 0)
            {{--
                NOT an empty table: a period with no station rows is not an
                empty list to interpret, it is a mill without active stations
                and a concrete way to fix it.
            --}}
            <div class="kc-notice" data-testid="period-stations-empty-{{ $period['id'] }}">
                Periode ini belum punya satu pun baris stasiun — mill-nya belum memiliki stasiun aktif.
                Tambahkan stasiun pada mill tersebut, lalu simpan ulang periode ini untuk mendaftarkannya.
                Tidak ada yang bisa ditutup, dan periode ini tetap dapat diubah maupun dihapus.
            </div>
        @else
            <div class="kc-table-wrap">
                <table class="kc-table" data-testid="period-stations-{{ $period['id'] }}">
                    <thead class="kc-table__head">
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
                            <tr class="kc-table__row" wire:key="period-station-{{ $station['id'] }}" data-testid="period-station-row-{{ $station['id'] }}">
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
                                            <button type="button" wire:click="confirmReopen" wire:loading.attr="disabled" wire:target="confirmReopen" class="kc-button kc-button--primary kc-button--sm" data-testid="confirm-reopen-button">
                                                <x-busy-label target="confirmReopen" busy="Membuka…">Ya, Buka Kembali</x-busy-label>
                                            </button>
                                            <button type="button" wire:click="cancelReopen" wire:loading.attr="disabled" wire:target="confirmReopen" class="kc-button kc-button--ghost kc-button--sm">
                                                Batal
                                            </button>
                                        </span>
                                    @elseif ($station['status'] === 'closed')
                                        <button
                                            type="button"
                                            wire:click="askReopen('{{ $station['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="askReopen('{{ $station['id'] }}')"
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
                                            wire:loading.attr="disabled"
                                            wire:target="askOpen('{{ $station['id'] }}')"
                                            class="kc-button kc-button--primary kc-button--sm"
                                            data-testid="station-open-button-{{ $station['id'] }}"
                                        >
                                            Buka Stasiun
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="askClose('{{ $station['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="askClose('{{ $station['id'] }}')"
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
            </div>
        @endif
    @endif

    @if ($showForm)
        <x-modal
            title="Edit Periode"
            wide
            submit="save"
            backdrop-key="period-detail-form-backdrop"
        >
            <x-slot:error>
                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert" data-testid="form-error">
                        {{ $formErrorMessage }}
                    </div>
                @endif
            </x-slot:error>

            {{--
                THERE IS NO "Jenis Stasiun" FIELD. A period covers its whole
                mill: update() backfills a row for every station type the mill
                has gained since — add-only, and it never touches or removes an
                existing row, closed ones included.
            --}}
            <div class="kc-form-section">
                <h4 class="kc-form-section__title">Cakupan Periode</h4>
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
                        <p class="kc-form-field__hint">
                            Menyimpan hanya <strong>menambahkan</strong> baris stasiun untuk jenis yang belum ada di
                            periode ini; baris yang sudah ada, termasuk yang sudah tertutup, tidak diubah maupun dihapus.
                        </p>
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
                    <x-busy-label target="save" busy="Menyimpan…">Simpan</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    @if ($closingStationId !== null && $closingStation !== null)
        <x-modal
            :title="'Tutup Stasiun — '.$closingStation['station_type_label']"
            backdrop-key="period-detail-close-backdrop"
        >
            {{--
                EVERY FIGURE AND LABEL IN THIS DIALOG COMES FROM ONE STATION
                ROW. The count was fetched with the same $closingStationId that
                confirmClose() passes to close(), so the warning cannot be
                about a station other than the one being closed.
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
                <button type="button" wire:click="cancelClose" wire:loading.attr="disabled" wire:target="confirmClose" class="kc-button kc-button--ghost" data-testid="cancel-close-button">
                    Batal
                </button>
                <button type="button" wire:click="confirmClose" class="kc-button kc-button--warning" wire:loading.attr="disabled" wire:target="confirmClose" data-testid="confirm-close-button">
                    <x-busy-label target="confirmClose" busy="Menutup…">Ya, Tutup Stasiun</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    @if ($openingStationId !== null)
        <x-modal
            :title="'Buka Stasiun'.($openingStation ? ' — '.$openingStation['station_type_label'] : '')"
            backdrop-key="period-detail-open-backdrop"
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
                <button type="button" wire:click="cancelOpen" wire:loading.attr="disabled" wire:target="confirmOpen" class="kc-button kc-button--ghost" data-testid="cancel-open-period">
                    Batal
                </button>
                <button type="button" wire:click="confirmOpen" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="confirmOpen" data-testid="confirm-open-period">
                    <x-busy-label target="confirmOpen" busy="Membuka…">Ya, Buka Stasiun</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    <style>
        /* Design tokens — uiux-spec: brand #249360. Same kc-* vocabulary as
           kelola-periode-pelaporan.blade.php so the list and its detail page
           look like one screen family; kc-summary__* and kc-backlink are this
           page's own additions. */
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
            margin: 4px 0 0;
            font-size: 20px;
            font-weight: 700;
        }

        .kc-backlink {
            padding: 0;
            background: none;
            border: none;
            font: inherit;
            font-size: 13px;
            color: var(--kc-text-muted);
            cursor: pointer;
        }

        .kc-backlink:hover {
            color: var(--kc-brand);
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

        .kc-summary {
            padding: 16px;
            margin-bottom: 24px;
            border: 1px solid var(--kc-border);
            border-radius: 10px;
            background: #fff;
        }

        .kc-summary__main {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .kc-summary__name {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .kc-summary__grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px 16px;
            margin: 0 0 14px;
        }

        .kc-summary__grid dt {
            font-size: 12px;
            font-weight: 500;
            color: var(--kc-text-muted);
        }

        .kc-summary__grid dd {
            margin: 4px 0 0;
            font-size: 14px;
        }

        .kc-summary__actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--kc-border);
        }

        .kc-section-title {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--kc-text-muted);
        }

        .kc-notice {
            padding: 14px 16px;
            border: 1px dashed var(--kc-border);
            border-radius: 10px;
            background: #f9fafb;
            font-size: 13px;
            line-height: 1.7;
            color: var(--kc-text-muted);
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

        /* `mixed` is not "half good" — it is a state the Admin must notice, so
           it gets its own amber badge rather than reusing the neutral one that
           `draft` and `empty` share. */
        .kc-badge--mixed {
            background: #fffbeb;
            color: var(--kc-warning-hover);
            border: 1px solid var(--kc-warning);
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
            margin: 0 0 16px;
            font-size: 13px;
            color: var(--kc-text-muted);
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

        @media (max-width: 767px) {
            .kc-summary__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .kc-form-grid {
                grid-template-columns: 1fr;
            }

            .kc-form-field--span2 {
                grid-column: span 1;
            }
        }
    </style>
</div>
