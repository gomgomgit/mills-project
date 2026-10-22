@php
    /**
     * screen-171--jadwal-shift — Jadwal Shift: mengisi SEL shift × tanggal
     * dengan orang, per Production Line, per bulan.
     *
     * BLADE TIDAK MENGHITUNG ATURAN APA PUN. Hari, kolom shift, isi sel, dan
     * ringkasan "N dari M sel sudah terisi" datang dari ShiftScheduleService
     * (grid()); blade hanya menata. Sumbu kolom (urutan shift) SAMA dengan
     * Kelola Shift karena sama-sama diurutkan service pada menit-ke-dalam-hari
     * start_time dengan titik nol 00:00.
     *
     * STATUS DITULIS DENGAN KATA, bukan hanya warna: akhir pekan ("akhir
     * pekan"), hari ini ("Hari ini"), shift tertutup ("shift sudah dihapus"),
     * user nonaktif ("nonaktif"), sel kosong ("belum ada petugas"). Teks
     * minimal 14px di seluruh layar.
     *
     * TIAP KELAS .jd-* DI MARKUP INI PUNYA DEFINISI DI BLOK <style> DI BAWAH,
     * dan kelas bersama (.btn, .badge, .alert, .card, .input, ...) ada di
     * partials/components-styles.blade.php.
     *
     * Tidak ada input yang dinonaktifkan atau readonly. Field tanggal adalah
     * <input type="date"> asli: dapat diketik manual MAUPUN dipilih lewat
     * picker.
     */
    $canKelolaShift = \App\Support\RouteAccess::allows('master-data.shifts');
    $canKelolaLine = \App\Support\RouteAccess::allows('master-data.production-lines');
    $visibleLimit = 3;
    $liveShifts = $grid ? array_values(array_filter($grid['shifts'], fn ($s) => ! $s['closed'])) : [];
@endphp

<div class="jd-page">
    <div class="page-header">
        <h1 class="page-title">Jadwal Shift</h1>
        <div class="page-actions">
            @if ($grid && $grid['state'] === 'ready')
                <button
                    type="button"
                    class="btn btn-outline"
                    wire:click="openApply"
                    data-testid="open-apply-button"
                >Terapkan ke beberapa tanggal</button>
                <button
                    type="button"
                    class="btn btn-outline"
                    wire:click="previewCopy"
                    wire:loading.attr="disabled"
                    wire:target="previewCopy"
                    data-testid="copy-previous-button"
                >Salin dari bulan sebelumnya</button>
            @endif
            @if ($canKelolaShift)
                <a class="btn btn-secondary" href="{{ route('master-data.shifts') }}" wire:navigate data-testid="open-kelola-shift">Buka Kelola Shift</a>
            @endif
        </div>
    </div>

    <div class="alert alert-info" data-testid="schedule-note">
        <div>
            Jam shift diatur di
            @if ($canKelolaShift)
                <a class="jd-link" href="{{ route('master-data.shifts') }}" wire:navigate>Kelola Shift</a>;
            @else
                Kelola Shift (khusus Admin);
            @endif
            layar ini mengisi shift dengan orang. Petugas shift lintas tengah malam
            (mis. Malam 23:00 &mdash; 07:00 +1) ditaruh pada <strong>TANGGAL MULAI</strong> shift.
        </div>
    </div>

    @if ($copyResult)
        <div class="alert alert-success" role="status" data-testid="copy-result">
            <div>{{ $copyResult }}</div>
        </div>
    @endif
    @if ($copyError)
        <div class="alert alert-error" role="alert" data-testid="copy-error">
            <div>{{ $copyError }}</div>
        </div>
    @endif
    @if ($copyPreview)
        <div class="alert alert-warning" data-testid="copy-preview">
            <div>
                <div class="alert-title">Salin dari {{ $this->monthLabel($copyPreview['source_month']) }} ke {{ $monthText }}</div>
                <div data-testid="copy-preview-text">
                    {{ $copyPreview['filled'] }} terisi, {{ $copyPreview['kept'] }} dipertahankan,
                    {{ array_sum($copyPreview['skipped']) }} dilewati.
                    Sel yang sudah berisi tidak ditimpa.
                </div>
                <div class="jd-note">
                    Dilewati: {{ $copyPreview['skipped']['closed_shift'] }} karena shift sudah dihapus,
                    {{ $copyPreview['skipped']['inactive_or_not_candidate'] }} karena orangnya nonaktif atau bukan calon lagi,
                    {{ $copyPreview['skipped']['missing_day'] }} karena tanggalnya tidak ada di bulan ini.
                </div>
                <div class="jd-actions">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirmCopy" wire:loading.attr="disabled" wire:target="confirmCopy" data-testid="confirm-copy-button">
                        <x-busy-label target="confirmCopy" busy="Menyalin…">Ya, salin</x-busy-label>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="cancelCopy">Batal</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ MILL + PRODUCTION LINE + BULAN ============ --}}
    <div class="card jd-card">
        <div class="card-body">
            <div class="jd-filter-bar">
                @if ($fixed)
                    <div class="input-group">
                        <span class="input-label">Mill</span>
                        <span class="jd-fixed-mill" data-testid="mill-fixed">{{ $millName ?? '—' }}</span>
                    </div>
                @else
                    <div class="input-group">
                        <label class="input-label" for="jd-mill">Mill <span class="required">*</span></label>
                        <select class="input" id="jd-mill" wire:model.live="businessUnitId" data-testid="mill-select">
                            <option value="">-- Pilih Mill --</option>
                            @foreach ($mills as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="input-group">
                    <label class="input-label" for="jd-line">Production Line <span class="required">*</span></label>
                    <select class="input" id="jd-line" wire:model.live="productionLineId" data-testid="line-select">
                        <option value="">-- Pilih Production Line --</option>
                        @foreach ($lineOptions as $option)
                            <option value="{{ $option['id'] }}">
                                {{ $option['name'] }}{{ $option['live_shift_count'] === 0 ? ' — belum punya shift' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="jd-month-nav" data-testid="month-nav">
                    <button type="button" class="btn btn-outline btn-icon" wire:click="previousMonth" aria-label="Bulan sebelumnya" data-testid="prev-month">&lsaquo;</button>
                    <span class="jd-month-name" aria-live="polite" data-testid="month-name">{{ $monthText }}</span>
                    <button type="button" class="btn btn-outline btn-icon" wire:click="nextMonth" aria-label="Bulan berikutnya" data-testid="next-month">&rsaquo;</button>
                    <button type="button" class="btn btn-ghost" wire:click="thisMonth" data-testid="this-month">Bulan ini</button>
                </div>
            </div>

            @if ($grid && $grid['state'] === 'ready')
                <div class="jd-summary" data-testid="schedule-summary">
                    <span class="badge {{ $grid['summary']['empty_cells'] === 0 ? 'badge-success' : 'badge-warning' }}" data-testid="summary-text">{{ $grid['summary']['text'] }}</span>
                    <span>{{ $grid['summary']['empty_cells'] }} sel belum ada petugas</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ============ KEADAAN-KEADAAN TANPA GRID ============ --}}
    @if ($businessUnitId === '')
        <div class="card jd-card">
            <div class="card-body">
                <div class="jd-guide" data-testid="pick-mill-guide">
                    <p class="jd-guide-title">Pilih mill terlebih dahulu</p>
                    <p class="jd-note">Admin tidak terikat satu mill. Setelah mill dipilih, pilih satu Production Line untuk melihat jadwal bulanannya.</p>
                </div>
            </div>
        </div>
    @elseif (empty($lineOptions))
        <div class="card jd-card">
            <div class="card-body">
                <div class="jd-guide" data-testid="no-production-line-guide">
                    <p class="jd-guide-title">Mill ini belum punya Production Line</p>
                    <p class="jd-note">
                        Jadwal shift melekat pada Production Line.
                        @if ($canKelolaLine)
                            Tambahkan line-nya lebih dahulu di
                            <a class="jd-link" href="{{ route('master-data.production-lines') }}" wire:navigate data-testid="production-line-link">Kelola Production Line</a>,
                            lalu kembali ke layar ini.
                        @else
                            Minta Admin menambahkan Production Line di Kelola Production Line, lalu kembali ke layar ini.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @elseif ($productionLineId === '')
        <div class="card jd-card">
            <div class="card-body">
                <div class="jd-guide" data-testid="pick-line-guide">
                    <p class="jd-guide-title">Pilih Production Line</p>
                    <p class="jd-note">Jadwal ditampilkan untuk satu line dalam satu bulan; tidak ada tampilan semua line sekaligus.</p>
                </div>
            </div>
        </div>
    @elseif ($grid && $grid['state'] === 'no_live_shift')
        <div class="card jd-card">
            <div class="card-body">
                <div class="jd-guide" data-testid="no-live-shift-guide">
                    <p class="jd-guide-title">Line {{ $grid['production_line']['name'] }} belum punya shift</p>
                    <p class="jd-note">
                        Jadwal tidak dapat diisi sebelum line ini punya shift.
                        @if ($canKelolaShift)
                            Tambahkan shift-nya di
                            <a class="jd-link" href="{{ route('master-data.shifts') }}" wire:navigate data-testid="kelola-shift-link">Kelola Shift</a>,
                            lalu kembali ke layar ini.
                        @else
                            Minta Admin menambahkan shift di Kelola Shift, lalu kembali ke layar ini.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @elseif ($grid)
        {{-- ============ PEMILIH TAMPILAN (diingat per peramban, bukan di server) ============ --}}
        <div
            class="jd-view-bar"
            x-data
            x-init="(() => { try { const v = window.localStorage.getItem('jd-view-mode'); if ((v === 'calendar' || v === 'table') && v !== $wire.viewMode) { $wire.setViewMode(v); } } catch (e) {} })()"
        >
            <div class="jd-switch" role="group" aria-label="Tampilan jadwal" data-testid="jd-view-switch">
                <button
                    type="button"
                    class="btn btn-sm {{ $viewMode === 'calendar' ? 'btn-primary' : 'btn-outline' }}"
                    wire:click="setViewMode('calendar')"
                    x-on:click="(() => { try { window.localStorage.setItem('jd-view-mode', 'calendar'); } catch (e) {} })()"
                    aria-pressed="{{ $viewMode === 'calendar' ? 'true' : 'false' }}"
                    data-testid="jd-view-calendar"
                >Kalender{{ $viewMode === 'calendar' ? ' (aktif)' : '' }}</button>
                <button
                    type="button"
                    class="btn btn-sm {{ $viewMode === 'table' ? 'btn-primary' : 'btn-outline' }}"
                    wire:click="setViewMode('table')"
                    x-on:click="(() => { try { window.localStorage.setItem('jd-view-mode', 'table'); } catch (e) {} })()"
                    aria-pressed="{{ $viewMode === 'table' ? 'true' : 'false' }}"
                    data-testid="jd-view-table"
                >Tabel{{ $viewMode === 'table' ? ' (aktif)' : '' }}</button>
            </div>
        </div>

        <div class="jd-layout">
            {{-- ============ GRID BULANAN ============ --}}
            <div class="card jd-grid-card">
                @if ($viewMode === 'calendar')
                    {{-- ============ KALENDER ============ --}}
                    <div class="jd-cal-scroll" data-testid="jd-calendar-scroll">
                        <div class="jd-cal" role="group" aria-label="Kalender jadwal {{ $grid['production_line']['name'] }} — {{ $monthText }}" data-testid="jd-calendar">
                            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $head)
                                <div class="jd-cal-head" data-testid="jd-cal-head-{{ $head }}">{{ $head }}</div>
                            @endforeach

                            @for ($i = 0; $i < $calendarLead; $i++)
                                <div class="jd-cal-box jd-cal-blank" aria-hidden="true" data-testid="jd-blank-lead-{{ $i }}"></div>
                            @endfor

                            @foreach ($grid['days'] as $day)
                                @php
                                    $isOpenDay = $openDate === $day['date'];
                                    $dayNum = (int) substr($day['date'], 8, 2);
                                @endphp
                                <div
                                    class="jd-cal-box {{ $day['is_weekend'] ? 'jd-cal-weekend' : '' }} {{ $day['is_today'] ? 'jd-cal-today' : '' }} {{ $isOpenDay ? 'jd-cal-opened' : '' }}"
                                    role="button"
                                    tabindex="0"
                                    wire:key="cal-{{ $day['date'] }}"
                                    wire:click="openDay('{{ $day['date'] }}')"
                                    wire:keydown.enter.self="openDay('{{ $day['date'] }}')"
                                    wire:keydown.space.prevent.self="openDay('{{ $day['date'] }}')"
                                    aria-label="Buka tanggal {{ $day['date'] }} ({{ $day['weekday_label'] }} {{ $dayNum }})"
                                    data-testid="jd-day-{{ $day['date'] }}"
                                >
                                    <div class="jd-cal-date">
                                        <span class="jd-cal-num">{{ $dayNum }}</span>
                                        <span class="jd-cal-wk">{{ $day['weekday_label'] }}</span>
                                        @if ($day['is_today'])
                                            <span class="badge badge-brand" data-testid="jd-today-{{ $day['date'] }}">Hari ini</span>
                                        @endif
                                        @if ($isOpenDay)
                                            <span class="badge badge-success" data-testid="jd-opened-{{ $day['date'] }}">Dibuka</span>
                                        @endif
                                    </div>
                                    @foreach ($grid['shifts'] as $shift)
                                        @php
                                            $cell = $cellMap[$shift['id']][$day['date']] ?? null;
                                            $names = $cell ? array_values(array_map(
                                                fn ($a) => $a['name'].($a['is_active'] ? '' : ' (nonaktif)'),
                                                $cell['assignees']
                                            )) : [];
                                            $ckey = 'cal|'.$shift['id'].'|'.$day['date'];
                                            $cOpen = in_array($ckey, $expanded, true);
                                            $calLimit = 2;
                                        @endphp
                                        @if ($cell !== null && ! ($shift['closed'] && $names === []))
                                            <div class="jd-sline {{ $shift['closed'] ? 'jd-sline-closed' : '' }}" wire:key="line-{{ $day['date'] }}-{{ $shift['id'] }}" data-testid="jd-line-{{ $day['date'] }}-{{ $shift['id'] }}">
                                                <strong>{{ $shift['name'] }}</strong>
                                                @if ($names === [])
                                                    <span class="jd-empty-cell" data-testid="jd-empty-{{ $day['date'] }}-{{ $shift['id'] }}">belum ada petugas</span>
                                                @else
                                                    <span>{{ implode(', ', $cOpen ? $names : array_slice($names, 0, $calLimit)) }}</span>
                                                    @if (count($names) > $calLimit)
                                                        <button
                                                            type="button"
                                                            class="jd-more"
                                                            wire:click.stop="toggleCell('{{ $ckey }}')"
                                                            data-testid="jd-more-{{ $day['date'] }}-{{ $shift['id'] }}"
                                                        >{{ $cOpen ? 'sembunyikan' : '+'.(count($names) - $calLimit).' lagi' }}</button>
                                                    @endif
                                                @endif
                                                @if ($shift['closed'])
                                                    <span class="badge badge-neutral">shift sudah dihapus</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach

                            @for ($i = 0; $i < $calendarTrail; $i++)
                                <div class="jd-cal-box jd-cal-blank" aria-hidden="true" data-testid="jd-blank-trail-{{ $i }}"></div>
                            @endfor
                        </div>
                    </div>
                @else
                    {{-- ============ TABEL ============ --}}
                    <div class="jd-grid-scroll" data-testid="schedule-grid-scroll">
                        <table class="jd-grid" data-testid="schedule-grid">
                            <caption class="jd-caption">
                                Jadwal {{ $grid['production_line']['name'] }} — {{ $monthText }}
                            </caption>
                            <thead>
                                <tr>
                                    <th class="jd-date-col jd-head-date" scope="col">Tanggal</th>
                                    @foreach ($grid['shifts'] as $shift)
                                        <th scope="col" wire:key="head-{{ $shift['id'] }}" data-testid="shift-head-{{ $shift['id'] }}">
                                            {{ $shift['name'] }}
                                            <span class="jd-col-time">{{ $shift['start_time'] }} &mdash; {{ $shift['end_time'] }}{{ $shift['crosses_midnight'] ? ' +1' : '' }}</span>
                                            @if ($shift['closed'])
                                                <span class="badge badge-neutral" data-testid="closed-badge-{{ $shift['id'] }}">shift sudah dihapus</span>
                                                <span class="jd-col-time">hanya dapat dibaca sebelum {{ $shift['closed_from'] }}</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grid['days'] as $day)
                                    <tr
                                        class="{{ $day['is_weekend'] ? 'jd-weekend' : '' }} {{ $day['is_today'] ? 'jd-today' : '' }}"
                                        wire:key="day-{{ $day['date'] }}"
                                        data-testid="day-row-{{ $day['date'] }}"
                                    >
                                        <th scope="row" class="jd-date-col">
                                            {{ $day['weekday_label'] }} {{ (int) substr($day['date'], 8, 2) }}
                                            @if ($day['is_weekend'])
                                                <span class="badge badge-neutral">akhir pekan</span>
                                            @endif
                                            @if ($day['is_today'])
                                                <span class="badge badge-brand" data-testid="jd-today-{{ $day['date'] }}">Hari ini</span>
                                            @endif
                                            @if ($openDate === $day['date'])
                                                <span class="badge badge-success" data-testid="jd-opened-{{ $day['date'] }}">Dibuka</span>
                                            @endif
                                        </th>
                                        @foreach ($grid['shifts'] as $shift)
                                            @php
                                                $cell = $cellMap[$shift['id']][$day['date']] ?? null;
                                                $active = $openDate === $day['date'] && $openShiftId === $shift['id'];
                                            @endphp
                                            @if ($cell === null)
                                                <td class="jd-closed" wire:key="cell-{{ $shift['id'] }}-{{ $day['date'] }}" data-testid="cell-{{ $shift['id'] }}-{{ $day['date'] }}">
                                                    tidak ada — shift sudah dihapus
                                                </td>
                                            @else
                                                <td
                                                    class="jd-td-click {{ $cell['editable'] ? '' : 'jd-readonly' }} {{ $active ? 'jd-cell-active' : '' }}"
                                                    wire:key="cell-{{ $shift['id'] }}-{{ $day['date'] }}"
                                                    data-testid="cell-{{ $shift['id'] }}-{{ $day['date'] }}"
                                                >
                                                    <button
                                                        type="button"
                                                        class="jd-cell-btn"
                                                        wire:click="openDay('{{ $day['date'] }}', '{{ $shift['id'] }}')"
                                                        aria-label="Buka tanggal {{ $day['date'] }}, shift {{ $shift['name'] }}{{ $cell['editable'] ? '' : ' (shift sudah dihapus, hanya dapat dibaca)' }}"
                                                        data-testid="jd-cell-btn-{{ $shift['id'] }}-{{ $day['date'] }}"
                                                    >
                                                        @if (empty($cell['assignees']))
                                                            <span class="jd-empty-cell" data-testid="jd-tcell-empty-{{ $shift['id'] }}-{{ $day['date'] }}">belum ada petugas</span>
                                                        @else
                                                            <span class="jd-chips">
                                                                @foreach ($cell['assignees'] as $assignee)
                                                                    <span class="jd-chip" wire:key="chip-{{ $shift['id'] }}-{{ $day['date'] }}-{{ $assignee['user_id'] }}">
                                                                        {{ $assignee['name'] }}
                                                                        @unless ($assignee['is_active'])
                                                                            <span class="badge badge-neutral">nonaktif</span>
                                                                        @endunless
                                                                    </span>
                                                                @endforeach
                                                            </span>
                                                        @endif
                                                        @unless ($cell['editable'])
                                                            <span class="jd-ro-note">hanya dapat dibaca</span>
                                                        @endunless
                                                    </button>
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ============ KARTU TANGGAL (panel samping) ============ --}}
            <div class="jd-stack">
                @if ($pendingDate !== null)
                    <div class="alert alert-warning" role="alert" data-testid="jd-discard-confirm">
                        <div>
                            Ada perubahan yang belum disimpan pada tanggal {{ $openDate }}. Pindah ke tanggal {{ $pendingDate }} akan membuangnya.
                            <div class="jd-actions">
                                <button type="button" class="btn btn-destructive btn-sm" wire:click="confirmDiscard" wire:loading.attr="disabled" wire:target="confirmDiscard" data-testid="jd-discard-yes"><span class="ld-spinner" aria-hidden="true" style="display:none" wire:loading.inline-block wire:target="confirmDiscard"></span>Ya, buang perubahan</button>
                                <button type="button" class="btn btn-outline btn-sm" wire:click="cancelDiscard" data-testid="jd-discard-no">Tetap di sini</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($panelError && ! $dayCard)
                    <div class="alert alert-error" role="alert" data-testid="panel-error">
                        <div>{{ $panelError }}</div>
                    </div>
                @endif

                @if ($dayCard)
                    @php
                        $cd = $dayCard['day'];
                        $editor = $dayCard['editor'];
                        $editingId = $editor['shift_id'] ?? null;
                    @endphp
                    <div class="card" data-testid="jd-day-card" data-date="{{ $dayCard['date'] }}">
                        <div class="card-header jd-card-head">
                            <span data-testid="jd-card-title">{{ $cd['weekday_label'] }} {{ (int) substr($dayCard['date'], 8, 2) }} {{ $monthText }}</span>
                            @if ($cd['is_today'])
                                <span class="badge badge-brand">Hari ini</span>
                            @endif
                            <button type="button" class="btn btn-ghost btn-sm jd-card-close" wire:click="closeDay" data-testid="jd-card-close">Tutup</button>
                        </div>
                        <div class="card-body jd-card-body">
                            @foreach ($dayCard['items'] as $item)
                                @php
                                    $sh = $item['shift'];
                                    $cl = $item['cell'];
                                    $isEditing = $item['live'] && $editingId === $sh['id'];
                                    $isExpandedClosed = ! $item['live'] && $dayCard['open_shift_id'] === $sh['id'];
                                    $dirty = $item['live'] && $this->isDirty($sh['id']);
                                    $namesList = implode(', ', array_map(fn ($a) => $a['name'].($a['is_active'] ? '' : ' (nonaktif)'), $cl['assignees']));
                                @endphp
                                <section class="jd-shift-sec {{ $isEditing || $isExpandedClosed ? 'jd-shift-open' : '' }}" wire:key="sec-{{ $sh['id'] }}" data-testid="jd-card-shift-{{ $sh['id'] }}">
                                    <button
                                        type="button"
                                        class="jd-shift-toggle"
                                        wire:click="selectShift('{{ $sh['id'] }}')"
                                        aria-expanded="{{ $isEditing || $isExpandedClosed ? 'true' : 'false' }}"
                                        data-testid="jd-card-shift-toggle-{{ $sh['id'] }}"
                                    >
                                        <strong>{{ $sh['name'] }}</strong>
                                        <span class="jd-col-time">{{ $sh['start_time'] }} &mdash; {{ $sh['end_time'] }}{{ $sh['crosses_midnight'] ? ' +1' : '' }}</span>
                                        <span class="badge badge-neutral">{{ count($cl['assignees']) }} petugas</span>
                                        @unless ($item['live'])
                                            <span class="badge badge-neutral" data-testid="jd-card-closed-{{ $sh['id'] }}">shift sudah dihapus</span>
                                        @endunless
                                        @if ($dirty)
                                            <span class="badge badge-warning" data-testid="jd-card-dirty-{{ $sh['id'] }}">belum disimpan</span>
                                        @endif
                                    </button>

                                    @if (! $item['live'])
                                        {{-- Riwayat saja: tanpa pengisi, tanpa tombol simpan. --}}
                                        <p class="jd-note" data-testid="jd-card-history-{{ $sh['id'] }}">
                                            @if ($cl['assignees'] === [])
                                                Tidak ada petugas tercatat. Shift sudah dihapus; hanya dapat dibaca.
                                            @else
                                                {{ $namesList }}. Shift sudah dihapus; hanya dapat dibaca.
                                            @endif
                                        </p>
                                    @elseif (! $isEditing)
                                        @if ($cl['assignees'] === [])
                                            <p class="jd-note"><span class="jd-empty-cell" data-testid="jd-card-empty-{{ $sh['id'] }}">belum ada petugas</span></p>
                                        @else
                                            <p class="jd-note">{{ $namesList }}</p>
                                        @endif
                                    @else
                                        @if ($feedbackShiftId === $sh['id'] && $panelNotice)
                                            <div class="alert alert-success" role="status" data-testid="jd-card-notice-{{ $sh['id'] }}">
                                                <div>{{ $panelNotice }}</div>
                                            </div>
                                        @endif
                                        @if ($feedbackShiftId === $sh['id'] && $panelError)
                                            <div class="alert alert-error" role="alert" data-testid="panel-error">
                                                <div>{{ $panelError }}</div>
                                            </div>
                                        @endif

                                        <div class="input-group">
                                            <label class="input-label" for="jd-panel-search">Cari calon peserta</label>
                                            <input
                                                id="jd-panel-search"
                                                class="input"
                                                type="text"
                                                placeholder="Ketik nama petugas"
                                                wire:model.live.debounce.300ms="panelSearch"
                                                data-testid="panel-search"
                                            >
                                        </div>

                                        <div class="jd-panel-list" data-testid="panel-list">
                                            @forelse ($editor['candidates'] as $candidate)
                                                @php
                                                    $others = array_values(array_filter(
                                                        $candidate['already_on_shifts'],
                                                        fn ($s) => $s['shift_id'] !== $sh['id']
                                                    ));
                                                @endphp
                                                <label class="jd-check" wire:key="cand-{{ $sh['id'] }}-{{ $candidate['user_id'] }}">
                                                    <input type="checkbox" value="{{ $candidate['user_id'] }}" wire:model.live="daySelected.{{ $sh['id'] }}" data-testid="candidate-{{ $candidate['user_id'] }}">
                                                    <span>{{ $candidate['name'] }} <span class="jd-role">({{ $candidate['role_label'] }})</span></span>
                                                    @if (in_array($candidate['user_id'], $dayLoaded[$sh['id']] ?? [], true))
                                                        <span class="badge badge-success">sudah di sel ini</span>
                                                    @endif
                                                    @if ($others !== [])
                                                        <span class="badge badge-neutral" data-testid="also-other-{{ $candidate['user_id'] }}">
                                                            juga di shift {{ implode(', ', array_column($others, 'shift_name')) }} hari ini
                                                        </span>
                                                    @endif
                                                </label>
                                            @empty
                                                @if ($editor['empty_reason'] === 'no_candidates')
                                                    <p class="jd-note" data-testid="no-candidates">
                                                        tidak ada calon peserta — mill ini belum punya user aktif berperan Operator atau Supervisor.
                                                    </p>
                                                @else
                                                    <p class="jd-note" data-testid="no-search-result">Tidak ada nama yang cocok dengan pencarian.</p>
                                                @endif
                                            @endforelse

                                            @foreach ($editor['extra'] as $member)
                                                <label class="jd-check" wire:key="extra-{{ $sh['id'] }}-{{ $member['user_id'] }}">
                                                    <input type="checkbox" value="{{ $member['user_id'] }}" wire:model.live="daySelected.{{ $sh['id'] }}" data-testid="candidate-{{ $member['user_id'] }}">
                                                    <span>{{ $member['name'] }}</span>
                                                    <span class="badge badge-neutral">{{ $member['is_active'] ? 'bukan calon lagi' : 'nonaktif' }}</span>
                                                    <span class="badge badge-success">sudah di sel ini</span>
                                                </label>
                                            @endforeach
                                        </div>

                                        @if ($confirmClearShiftId === $sh['id'])
                                            <div class="alert alert-warning" role="alert" data-testid="jd-card-clear-confirm-{{ $sh['id'] }}">
                                                <div>
                                                    Mengosongkan sel ini akan mengeluarkan {{ count($dayLoaded[$sh['id']] ?? []) }} orang. Lanjutkan?
                                                    <div class="jd-actions">
                                                        <button type="button" class="btn btn-destructive btn-sm" wire:click="confirmClear('{{ $sh['id'] }}')" wire:loading.attr="disabled" wire:target="confirmClear" data-testid="jd-card-clear-yes-{{ $sh['id'] }}"><x-busy-label target="confirmClear" busy="Mengosongkan…">Ya, kosongkan</x-busy-label></button>
                                                        <button type="button" class="btn btn-outline btn-sm" wire:click="cancelClear" data-testid="jd-card-clear-no-{{ $sh['id'] }}">Batal</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="jd-actions">
                                            <button type="button" class="btn btn-primary" wire:click="saveShift('{{ $sh['id'] }}')" wire:loading.attr="disabled" wire:target="saveShift,clearShift,confirmClear" data-testid="jd-card-save-{{ $sh['id'] }}">
                                                <x-busy-label target="saveShift" busy="Menyimpan…">Simpan shift {{ $sh['name'] }}</x-busy-label>
                                            </button>
                                            <button type="button" class="btn btn-outline" wire:click="clearShift('{{ $sh['id'] }}')" wire:loading.attr="disabled" wire:target="saveShift,clearShift,confirmClear" data-testid="jd-card-clear-{{ $sh['id'] }}">Kosongkan sel</button>
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                            <p class="jd-note">Petugas shift lintas tengah malam ditaruh pada tanggal mulainya ({{ (int) substr($dayCard['date'], 8, 2) }}).</p>
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-header">Kartu tanggal</div>
                        <div class="card-body">
                            <p class="jd-note" data-testid="jd-card-placeholder">
                                Klik sebuah tanggal (kalender) atau sel (tabel) untuk melihat dan mengatur petugas semua shift pada tanggal itu.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- ============ TERAPKAN KE BEBERAPA TANGGAL ============ --}}
                @if ($applyOpen)
                    <div class="card" data-testid="apply-card">
                        <div class="card-header">Terapkan ke beberapa tanggal</div>
                        <div class="card-body">
                            @if ($applyResult)
                                <div class="alert alert-success" role="status" data-testid="apply-result">
                                    <div>{{ $applyResult }}</div>
                                </div>
                            @endif
                            @if ($applyError)
                                <div class="alert alert-error" role="alert" data-testid="apply-error">
                                    <div>{{ $applyError }}</div>
                                </div>
                            @endif

                            <div class="jd-form">
                                <div class="input-group">
                                    <label class="input-label" for="jd-apply-shift">Shift</label>
                                    <select id="jd-apply-shift" class="input" wire:model.live="applyShiftId" data-testid="apply-shift">
                                        <option value="">-- Pilih Shift --</option>
                                        @foreach ($liveShifts as $shift)
                                            <option value="{{ $shift['id'] }}">{{ $shift['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="input-group">
                                    <span class="input-label">Cara memilih tanggal</span>
                                    <div class="jd-weekdays">
                                        <label class="jd-check"><input type="radio" value="range" wire:model.live="applyMode" data-testid="apply-mode-range"> Rentang tanggal</label>
                                        <label class="jd-check"><input type="radio" value="dates" wire:model.live="applyMode" data-testid="apply-mode-dates"> Tanggal tertentu</label>
                                    </div>
                                </div>

                                @if ($applyMode === 'range')
                                    <div class="form-row">
                                        <div class="input-group">
                                            <label class="input-label" for="jd-apply-from">Dari tanggal</label>
                                            <input id="jd-apply-from" class="input" type="date" wire:model.live="applyFrom" data-testid="apply-from">
                                        </div>
                                        <div class="input-group">
                                            <label class="input-label" for="jd-apply-to">Sampai tanggal</label>
                                            <input id="jd-apply-to" class="input" type="date" wire:model.live="applyTo" data-testid="apply-to">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-label">Hari dalam seminggu</span>
                                        <div class="jd-weekdays">
                                            @foreach (\App\Services\ShiftScheduleService::WEEKDAY_LABELS as $number => $label)
                                                <label class="jd-check" wire:key="wd-{{ $number }}">
                                                    <input type="checkbox" value="{{ $number }}" wire:model.live="applyWeekdays" data-testid="apply-weekday-{{ $number }}"> {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="input-group">
                                        <label class="input-label" for="jd-apply-add">Tambah tanggal</label>
                                        <div class="jd-inline">
                                            <input id="jd-apply-add" class="input" type="date" wire:model.live="applyDateToAdd" data-testid="apply-date-add">
                                            <button type="button" class="btn btn-outline" wire:click="addApplyDate" data-testid="apply-date-add-button">Tambah</button>
                                        </div>
                                        <div class="jd-chips" data-testid="apply-dates">
                                            @forelse ($applyDates as $date)
                                                <span class="jd-chip" wire:key="ad-{{ $date }}">
                                                    {{ $date }}
                                                    <button type="button" class="jd-more" wire:click="removeApplyDate('{{ $date }}')" aria-label="Hapus tanggal {{ $date }}">hapus</button>
                                                </span>
                                            @empty
                                                <span class="jd-empty-cell">belum ada tanggal dipilih</span>
                                            @endforelse
                                        </div>
                                    </div>
                                @endif

                                <div class="input-group">
                                    <label class="input-label" for="jd-apply-search">Orang ({{ count($applyUserIds) }} dipilih)</label>
                                    <input id="jd-apply-search" class="input" type="text" placeholder="Cari dan pilih petugas" wire:model.live.debounce.300ms="applySearch" data-testid="apply-search">
                                    <div class="jd-panel-list" data-testid="apply-people">
                                        @forelse ($applyCandidates['data'] ?? [] as $candidate)
                                            <label class="jd-check" wire:key="ap-{{ $candidate['user_id'] }}">
                                                <input type="checkbox" value="{{ $candidate['user_id'] }}" wire:model.live="applyUserIds" data-testid="apply-candidate-{{ $candidate['user_id'] }}">
                                                <span>{{ $candidate['name'] }} <span class="jd-role">({{ $candidate['role_label'] }})</span></span>
                                            </label>
                                        @empty
                                            <p class="jd-note">
                                                @if (($applyCandidates['empty_reason'] ?? null) === 'no_candidates')
                                                    tidak ada calon peserta pada mill ini.
                                                @else
                                                    Tidak ada nama yang cocok dengan pencarian.
                                                @endif
                                            </p>
                                        @endforelse
                                    </div>
                                    <p class="jd-note">Tanpa orang yang dipilih, sel sasaran dikosongkan.</p>
                                </div>

                                @if ($applyPreview)
                                    <div class="alert {{ $applyPreview['cells_already_filled'] > 0 ? 'alert-warning' : 'alert-info' }}" data-testid="apply-preview">
                                        <div>
                                            <div class="alert-title" data-testid="apply-preview-title">Akan memengaruhi {{ $applyPreview['target_cells'] }} sel</div>
                                            <div data-testid="apply-preview-detail">
                                                {{ $applyPreview['cells_already_filled'] }} di antaranya sudah berisi{{ $applyPreview['cells_already_filled'] > 0 ? ' dan akan ditimpa' : '' }}
                                                ({{ $applyPreview['people_affected'] }} orang tergeser).
                                                Hanya tanggal pada bulan {{ $monthText }} yang terpengaruh{{ $applyPreview['outside_month_ignored'] > 0 ? '; '.$applyPreview['outside_month_ignored'].' tanggal di luar bulan ini diabaikan' : '' }}.
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($applyConfirming)
                                    <div class="alert alert-warning" role="alert" data-testid="apply-confirm">
                                        <div>
                                            <div class="alert-title">Timpa {{ $applyPreview['cells_already_filled'] ?? 0 }} sel yang sudah berisi?</div>
                                            Isi lama sel-sel itu akan diganti dan tidak dapat dikembalikan dari layar ini.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer jd-actions">
                            @if ($applyConfirming)
                                <button type="button" class="btn btn-destructive" wire:click="submitApply" wire:loading.attr="disabled" wire:target="submitApply" data-testid="apply-confirm-button">
                                    <x-busy-label target="submitApply" busy="Menyimpan…">Ya, timpa dan simpan</x-busy-label>
                                </button>
                                <button type="button" class="btn btn-ghost" wire:click="cancelApplyConfirm" data-testid="apply-confirm-cancel">Batal</button>
                            @else
                                <button type="button" class="btn btn-outline" wire:click="previewApply" wire:loading.attr="disabled" wire:target="previewApply" data-testid="apply-preview-button">Lihat dampak</button>
                                <button type="button" class="btn btn-primary" wire:click="submitApply" wire:loading.attr="disabled" wire:target="submitApply" data-testid="apply-submit-button">
                                    <x-busy-label target="submitApply" busy="Menyimpan…">Terapkan</x-busy-label>
                                </button>
                                <button type="button" class="btn btn-ghost" wire:click="closeApply" data-testid="apply-close">Tutup</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <style>
        /* ─────────────────────────────────────────────────────────────────
           screen-171--jadwal-shift — gaya khusus halaman ini.

           Hanya VARIABEL token, tanpa literal warna/radius/shadow (token dari
           partials/design-tokens.blade.php yang dipasang x-layouts.app).
           TIAP KELAS .jd-* DI MARKUP DI ATAS PUNYA DEFINISI DI SINI.

           MINIMUM 14px (uiux_spec.accessibility.text_content.min_font_size):
           aset bersama menetapkan .badge dan .btn-sm pada 12px, jadi seluruh
           isi layar ini diangkat ke 14px; hanya judul halaman yang lebih
           besar.
           ───────────────────────────────────────────────────────────────── */
        .jd-page, .jd-page * { font-size: max(var(--font-size-sm), 14px); }
        .jd-page .page-title { font-size: var(--font-size-h1); }
        .jd-page .btn-sm { padding: var(--space-xs) var(--space-sm); }

        .jd-card { margin-bottom: var(--space-md); }
        .jd-note { color: var(--color-muted-foreground); line-height: var(--line-height-base); }
        .jd-note strong { color: var(--color-foreground); }
        .jd-link { color: var(--color-brand); font-weight: var(--font-weight-sm); text-decoration: underline; }
        .jd-role { color: var(--color-muted-foreground); }
        .jd-actions { display: flex; flex-wrap: wrap; gap: var(--space-sm); margin-top: var(--space-sm); }
        .card-footer.jd-actions { margin-top: 0; }

        .jd-guide { display: flex; flex-direction: column; gap: var(--space-sm); }
        .jd-guide-title { font-size: var(--font-size-base); font-weight: var(--font-weight-h3); color: var(--color-foreground); }

        .jd-filter-bar { display: flex; flex-wrap: wrap; gap: var(--space-md); align-items: flex-end; margin-bottom: var(--space-md); }
        .jd-filter-bar .input-group { min-width: 200px; }
        .jd-fixed-mill { font-weight: var(--font-weight-h3); color: var(--color-foreground); padding: var(--space-xs) 0; }
        .jd-month-nav { display: flex; align-items: center; gap: var(--space-sm); }
        .jd-month-name { font-weight: var(--font-weight-h3); min-width: 140px; text-align: center; }
        .jd-summary { display: flex; flex-wrap: wrap; gap: var(--space-md); align-items: center; }

        .jd-layout { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: var(--space-lg); align-items: start; }
        @media (max-width: 1100px) { .jd-layout { grid-template-columns: 1fr; } }
        .jd-stack { display: flex; flex-direction: column; gap: var(--space-lg); }

        /* Grid menggulir di wrapper-nya sendiri; HALAMAN tidak menggulir
           mendatar. Kolom tanggal sticky di kiri, header sticky di atas. */
        .jd-grid-card { overflow: hidden; }
        .jd-grid-scroll { overflow: auto; max-height: 70vh; }
        .jd-grid { width: 100%; border-collapse: collapse; }
        .jd-caption { text-align: left; padding: var(--space-sm) var(--space-md); color: var(--color-muted-foreground); }
        .jd-grid th, .jd-grid td {
            border: 1px solid var(--color-border);
            padding: var(--space-sm);
            vertical-align: top;
            text-align: left;
            min-width: 170px;
        }
        .jd-grid thead th { background: var(--color-surface); position: sticky; top: 0; z-index: 2; }
        .jd-grid .jd-date-col {
            min-width: 140px;
            position: sticky;
            left: 0;
            z-index: 1;
            background: var(--color-surface);
            white-space: nowrap;
        }
        .jd-grid thead th.jd-head-date { z-index: 3; }
        .jd-grid tr.jd-weekend td { background: var(--color-surface); }
        .jd-grid tr.jd-today .jd-date-col { box-shadow: inset 4px 0 0 var(--color-brand); }
        .jd-col-time { display: block; color: var(--color-muted-foreground); font-weight: var(--font-weight-body); }

        .jd-chips { display: flex; flex-wrap: wrap; gap: var(--space-xs); }
        .jd-chip {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: 2px var(--space-sm);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-chip);
            background: var(--color-background);
        }
        .jd-more {
            background: transparent;
            border: 0;
            padding: 0;
            color: var(--color-brand);
            text-decoration: underline;
            cursor: pointer;
        }
        .jd-empty-cell { color: var(--color-muted-foreground); font-style: italic; }
        .jd-readonly { background: var(--color-surface); }
        .jd-ro-note { color: var(--color-warning); margin-top: var(--space-xs); }
        .jd-closed { color: var(--color-muted-foreground); font-style: italic; background: var(--color-surface); }
        .jd-cell-active { box-shadow: inset 0 0 0 2px var(--color-brand); }


        /* ── Pemilih tampilan ── */
        .jd-view-bar { margin-bottom: var(--space-md); }
        .jd-switch { display: inline-flex; gap: var(--space-xs); flex-wrap: wrap; }

        /* ── Sel tabel = satu target klik ── */
        .jd-grid td.jd-td-click { padding: 0; }
        .jd-cell-btn {
            display: block;
            width: 100%;
            min-height: 64px;
            padding: var(--space-sm);
            background: transparent;
            border: 0;
            text-align: left;
            color: inherit;
            cursor: pointer;
        }
        .jd-cell-btn:hover { background: var(--color-surface); }
        .jd-cell-btn:focus-visible { outline: 2px solid var(--color-brand); outline-offset: -2px; }

        /* ── Kalender: 7 kolom; < 768px menjadi daftar satu baris per tanggal ── */
        .jd-cal-scroll { overflow-x: auto; padding: var(--space-sm); }
        .jd-cal { display: grid; grid-template-columns: repeat(7, minmax(150px, 1fr)); gap: var(--space-xs); min-width: 1050px; }
        .jd-cal-head {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: var(--space-sm);
            font-weight: var(--font-weight-h3);
            text-align: center;
        }
        .jd-cal-box {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-input);
            padding: var(--space-sm);
            min-height: 150px;
            cursor: pointer;
            background: var(--color-background);
        }
        .jd-cal-box:hover { border-color: var(--color-brand); }
        .jd-cal-box:focus-visible { outline: 2px solid var(--color-brand); outline-offset: 2px; }
        .jd-cal-blank { background: var(--color-surface); cursor: default; border-style: dashed; }
        .jd-cal-blank:hover { border-color: var(--color-border); }
        .jd-cal-weekend { background: var(--color-surface); }
        .jd-cal-today { border-color: var(--color-brand); border-width: 2px; }
        .jd-cal-opened { box-shadow: var(--shadow-modal); border-color: var(--color-brand); }
        .jd-cal-date { display: flex; flex-wrap: wrap; gap: var(--space-xs); align-items: center; margin-bottom: var(--space-xs); }
        .jd-cal-num { font-weight: var(--font-weight-h3); }
        .jd-cal-wk { color: var(--color-muted-foreground); }
        .jd-sline { margin-bottom: var(--space-xs); line-height: var(--line-height-base); overflow-wrap: anywhere; }
        .jd-sline-closed { color: var(--color-muted-foreground); }
        @media (max-width: 768px) {
            .jd-cal-scroll { overflow-x: visible; }
            .jd-cal { grid-template-columns: 1fr; min-width: 0; }
            .jd-cal-head, .jd-cal-blank { display: none; }
            .jd-cal-box { min-height: 0; }
        }

        /* ── Kartu tanggal ── */
        .jd-card-head { display: flex; flex-wrap: wrap; gap: var(--space-sm); align-items: center; }
        .jd-card-close { margin-left: auto; }
        .jd-card-body { display: flex; flex-direction: column; gap: var(--space-sm); }
        .jd-shift-sec { border: 1px solid var(--color-border); border-radius: var(--radius-input); padding: var(--space-sm); }
        .jd-shift-open { border-color: var(--color-brand); }
        .jd-shift-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            align-items: center;
            width: 100%;
            background: transparent;
            border: 0;
            padding: 0;
            text-align: left;
            color: inherit;
            cursor: pointer;
        }

        .jd-form { display: flex; flex-direction: column; gap: var(--space-md); }
        .jd-inline { display: flex; gap: var(--space-sm); align-items: center; flex-wrap: wrap; }
        .jd-weekdays { display: flex; flex-wrap: wrap; gap: var(--space-sm); }
        .jd-panel-list { display: flex; flex-direction: column; gap: var(--space-xs); margin: var(--space-sm) 0; max-height: 320px; overflow-y: auto; }
        .jd-check { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-sm); min-height: 44px; cursor: pointer; }
        .jd-check input { width: 18px; height: 18px; }
    </style>
</div>
