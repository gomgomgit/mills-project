@php
    /**
     * screen-170--kelola-shift — Kelola Shift per Production Line.
     *
     * CACAT PADA DATA SHIFT ITU BERBENTUK, BUKAN BERANGKA. Tabel berisi
     * '15:00' dan '19:00' menuntut pembacanya menghitung selisihnya di
     * kepala; sebuah batang 24 jam membuat celah empat jam itu langsung
     * terlihat sebagai ruang kosong. Karena itu TIMELINE ada di ATAS tabel —
     * pola panel-di-atas-tabel yang sama dengan Kelola Periode Pelaporan.
     *
     * BLADE TIDAK MENGHITUNG JAM SAMA SEKALI. Seluruh cakupan datang dari
     * ShiftService (coverage[].blocks, dalam MENIT DARI 07:00); satu-satunya
     * aritmetika di berkas ini adalah $pct() — menit menjadi persen. Jangan
     * menurunkan ulang durasi, celah, maupun urutan di sini: perhitungan yang
     * sama akan dibutuhkan gerbang sinkronisasi dan penandaan shift pada
     * record, dan tiga penghitung yang berdiri sendiri akan berselisih.
     *
     * URUTAN DIBACA DARI RESPONS, TIDAK DIURUTKAN DI SINI. $shifts sudah
     * terurut (nama line, lalu `position` yang diturunkan dari jam mulai
     * relatif 07:00) dan coverage[].blocks sudah terurut offset — keduanya
     * dari satu nilai yang sama, sehingga URUTAN BARIS TABEL dan URUTAN BLOK
     * TIMELINE tidak dapat berselisih. Sebuah spec browser mengukur invarian
     * itu dari DOM.
     *
     * TIMELINE DIMULAI 07:00. Hari produksi pabrik ini berjalan 07:00 sampai
     * 06:00 keesokan hari, jadi shift malam (23:00–07:00) tergambar sebagai
     * SATU blok utuh yang menempel ke ujung kanan batang — bukan dua potongan
     * di kedua ujungnya, yang akan terjadi bila batangnya dimulai tengah malam.
     *
     * SELURUH TEKS MINIMAL 14px, termasuk label jam pada sumbunya. Bila ruang
     * tidak cukup, label DIJARANGKAN (.ks-axis-tick--secondary disembunyikan),
     * tidak diperkecil. Keterangan kecil yang tidak terbaca sama saja dengan
     * keterangan yang tidak ada.
     */

    // SATU-SATUNYA aritmetika di blade ini: menit → persen lebar batang.
    // 1440 menit = 100%, jadi satu persen = 14,4 menit.
    $pct = fn ($minutes) => round(((int) $minutes) / 14.4, 4);

    // Status cakupan sebagai KATA, bukan hanya warna — dan jumlah jam celah
    // DISEBUT ANGKANYA. Warna saja tidak terbaca oleh semua orang dan tidak
    // terbaca sama sekali saat dicetak.
    $coverageBadgeText = fn (array $line) => match ($line['state']) {
        'covered' => 'tercakup 24 jam',
        'gap' => 'ada celah '.$line['uncovered_hours_text'].' jam',
        default => 'belum diatur',
    };

    $coverageBadgeClass = fn (array $line) => match ($line['state']) {
        'covered' => 'badge-success',
        'gap' => 'badge-warning',
        default => 'badge-neutral',
    };
@endphp

<div class="ks-page">
    <div class="page-header">
        <h1 class="page-title">Kelola Shift</h1>
        <div class="page-actions">
            <button
                type="button"
                class="btn btn-primary"
                wire:click="openCreateForm"
                data-testid="add-shift-button"
            >+ Tambah Shift</button>
        </div>
    </div>

    @if ($successMessage)
        <div class="alert alert-success" role="status" data-testid="success-message">
            <div>{{ $successMessage }}</div>
        </div>
    @endif

    @if ($deleteErrorMessage)
        <div class="alert alert-error" role="alert" data-testid="delete-error">
            <div>{{ $deleteErrorMessage }}</div>
        </div>
    @endif

    {{-- ============ PEMILIH MILL + FILTER PRODUCTION LINE ============ --}}
    <div class="ks-filter-bar">
        <div class="input-group">
            <label class="input-label" for="ks-mill">Mill <span class="required">*</span></label>
            <select
                class="input"
                id="ks-mill"
                wire:model.live="businessUnitId"
                data-testid="mill-select"
            >
                <option value="">-- Pilih Mill --</option>
                @foreach ($businessUnitOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="input-group">
            <label class="input-label" for="ks-line">Production Line</label>
            <select
                class="input"
                id="ks-line"
                wire:model.live="productionLineId"
                data-testid="line-filter"
            >
                <option value="">Semua line</option>
                @foreach ($lineOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            <p class="ks-note">
                @if ($productionLineId === '')
                    Semua line ditampilkan — satu batang per line, untuk mengaudit cakupan seluruh mill sekaligus.
                @else
                    Satu line ditampilkan. Pilih “Semua line” untuk mengaudit cakupan seluruh mill sekaligus.
                @endif
            </p>
        </div>
    </div>

    @if ($businessUnitId === '')
        {{-- MILL BELUM DIPILIH: tidak ada timeline maupun tabel, hanya arahan.
             Nol kueri shift berjalan (lihat KelolaShift::render()). --}}
        <div class="card ks-card">
            <div class="card-body">
                <div class="ks-guide" data-testid="pick-mill-guide">
                    <p class="ks-guide-title">Pilih mill terlebih dahulu</p>
                    <p class="ks-note">
                        Admin tidak terikat satu mill, jadi jadwal shift baru ditampilkan setelah Mill dipilih
                        di atas. Shift melekat pada <strong>Production Line</strong>, dan setiap line punya
                        jadwal kerjanya sendiri.
                    </p>
                </div>
            </div>
        </div>
    @elseif (empty($coverage))
        {{-- MILL TANPA SATU PUN PRODUCTION LINE: dinyatakan dan diarahkan,
             bukan timeline kosong tanpa penjelasan. --}}
        <div class="card ks-card">
            <div class="card-body">
                <div class="ks-guide" data-testid="no-production-line-guide">
                    <p class="ks-guide-title">Mill ini belum punya Production Line</p>
                    <p class="ks-note">
                        Shift melekat pada Production Line, jadi mill tanpa line sama sekali belum dapat punya
                        shift. Tambahkan line-nya lebih dahulu di
                        <a
                            class="ks-link"
                            href="{{ route('master-data.production-lines') }}"
                            wire:navigate
                            data-testid="production-line-link"
                        >Kelola Production Line</a>, lalu kembali ke layar ini.
                    </p>
                </div>
            </div>
        </div>
    @else
        {{-- ============ TIMELINE: cacat pada data shift itu berbentuk ============ --}}
        <div class="card ks-card">
            <div class="card-header">
                Cakupan Shift per Production Line{{ $millName ? ' — '.$millName : '' }}
            </div>
            <div class="card-body">
                <p class="ks-note">
                    <strong>Hari produksi berjalan 07:00 sampai 06:00 keesokan hari</strong>, mengikuti kisi slot
                    waktu yang dipakai seluruh stasiun bergrid — karena itu batang di bawah dimulai 07:00, bukan
                    tengah malam. Shift yang melewati tengah malam (ditulis <strong>23:00 — 07:00 +1</strong>)
                    tergambar sebagai satu blok utuh di ujung kanan batang, bukan dua potongan di kedua ujungnya.
                </p>

                <div class="ks-scroll">
                    <div class="ks-timeline-inner">

                        {{-- Sumbu jam. Tetap 07:00 → 06:00+1 karena batangnya memang
                             selalu satu hari produksi; label bertanda --secondary
                             DIJARANGKAN di lebar sempit, tidak diperkecil. --}}
                        <div class="ks-axis">
                            <span class="ks-axis-tick ks-axis-tick--start" style="left:0">07:00</span>
                            <span class="ks-axis-tick ks-axis-tick--secondary" style="left:16.6667%">11:00</span>
                            <span class="ks-axis-tick" style="left:33.3333%">15:00</span>
                            <span class="ks-axis-tick" style="left:50%">19:00</span>
                            <span class="ks-axis-tick" style="left:66.6667%">23:00</span>
                            <span class="ks-axis-tick ks-axis-tick--secondary" style="left:83.3333%">03:00 +1</span>
                            <span class="ks-axis-tick ks-axis-tick--end" style="left:95.8333%">06:00 +1</span>
                        </div>

                        @foreach ($coverage as $line)
                            <div
                                class="ks-line"
                                wire:key="coverage-{{ $line['production_line_id'] }}"
                                data-testid="timeline-line-{{ $line['production_line_id'] }}"
                            >
                                <div class="ks-line-head">
                                    <span class="ks-line-name">{{ $line['production_line_name'] }}</span>
                                    <span class="ks-line-status">
                                        <span class="ks-line-meta">
                                            {{ $line['shift_count'] }} shift
                                            @if ($line['state'] === 'gap')
                                                · {{ $line['uncovered_hours_text'] }} jam tidak tercakup
                                            @endif
                                        </span>
                                        {{-- Badge-nya elemen sendiri dengan selector stabil: asersi
                                             menyasar TEKS badge ini, bukan memindai teks kontainer —
                                             'belum diatur' muncul juga di kalimat penjelas di bawah. --}}
                                        <span
                                            class="badge {{ $coverageBadgeClass($line) }} ks-coverage-badge"
                                            data-testid="coverage-badge-{{ $line['production_line_id'] }}"
                                        >{{ $coverageBadgeText($line) }}</span>
                                    </span>
                                </div>

                                <div class="ks-track" data-testid="timeline-track-{{ $line['production_line_id'] }}">
                                    @if ($line['state'] === 'unconfigured')
                                        {{-- BELUM DIATUR, dan sengaja TANPA tekstur: keadaan
                                             'belum dikerjakan' tidak boleh tertukar dengan celah,
                                             yang justru sebuah keputusan yang mungkin salah. --}}
                                        <div
                                            class="ks-block ks-block-empty"
                                            style="left:0;width:100%"
                                            data-testid="timeline-empty-{{ $line['production_line_id'] }}"
                                        >
                                            <span class="ks-block-time">Belum diatur — belum ada satu pun shift pada line ini</span>
                                        </div>
                                    @else
                                        @foreach ($line['blocks'] as $block)
                                            @if ($block['kind'] === 'shift')
                                                <div
                                                    class="ks-block ks-block-shift"
                                                    style="left:{{ $pct($block['start_offset_minutes']) }}%;width:{{ $pct($block['duration_minutes']) }}%"
                                                    wire:key="block-{{ $block['shift_id'] }}"
                                                    data-testid="timeline-block-{{ $block['shift_id'] }}"
                                                >
                                                    <span class="ks-block-name">{{ $block['label'] }}</span>
                                                    <span class="ks-block-time">{{ $block['time_label'] }}</span>
                                                </div>
                                            @else
                                                <div
                                                    class="ks-block ks-block-gap"
                                                    style="left:{{ $pct($block['start_offset_minutes']) }}%;width:{{ $pct($block['duration_minutes']) }}%"
                                                    wire:key="gap-{{ $line['production_line_id'] }}-{{ $block['start_offset_minutes'] }}"
                                                    data-testid="timeline-gap-{{ $line['production_line_id'] }}-{{ $block['start_offset_minutes'] }}"
                                                >
                                                    <span class="ks-block-name">{{ $block['label'] }}</span>
                                                    <span class="ks-block-time">{{ $block['time_label'] }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>

                                @if ($line['state'] === 'gap')
                                    <p class="ks-note" data-testid="gap-note-{{ $line['production_line_id'] }}">
                                        Celah tidak ditolak — pabrik yang tidak beroperasi dua puluh empat jam itu sah.
                                        Tetapi record yang nanti jatuh pada rentang bertekstur di atas tidak akan punya
                                        shift, jadi celahnya dinyatakan beserta jumlah jamnya:
                                        <strong>{{ $line['uncovered_hours_text'] }} jam</strong> tidak tercakup.
                                    </p>
                                @elseif ($line['state'] === 'unconfigured')
                                    <p class="ks-note" data-testid="unconfigured-note-{{ $line['production_line_id'] }}">
                                        Dinyatakan <strong>belum diatur</strong>, bukan <strong>celah 24 jam</strong>: yang
                                        pertama belum dikerjakan, yang kedua keputusan yang mungkin salah.
                                    </p>
                                @endif

                                @if ($line['shift_count'] > 0 && ! $line['starts_at_production_day'])
                                    <p class="ks-note" data-testid="misaligned-note-{{ $line['production_line_id'] }}">
                                        <strong>Jadwal line ini tidak dimulai 07:00.</strong> Hari produksi dimulai jam itu,
                                        jadi ketidakselarasannya ditandai — tetapi ini <strong>peringatan, bukan
                                        penolakan</strong>: jadwal yang memang tidak selaras tetap tersimpan apa adanya.
                                    </p>
                                @endif
                            </div>
                        @endforeach

                    </div>
                </div>

                <div class="ks-legend">
                    <span class="ks-legend-item"><span class="ks-swatch ks-swatch-shift"></span>Shift</span>
                    <span class="ks-legend-item"><span class="ks-swatch ks-swatch-gap"></span>Celah — tidak dimiliki shift mana pun</span>
                    <span class="ks-legend-item"><span class="ks-swatch ks-swatch-empty"></span>Belum diatur</span>
                </div>
            </div>
        </div>

        {{-- ============ TABEL SHIFT — urutannya sama dengan timeline ============ --}}
        <div class="card ks-card">
            <div class="card-header">Daftar Shift</div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Production Line</th>
                                <th>Nama Shift</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Durasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shifts as $shift)
                                <tr
                                    class="ks-shift-row"
                                    wire:key="shift-{{ $shift['id'] }}"
                                    data-testid="shift-row-{{ $shift['id'] }}"
                                >
                                    <td>{{ $shift['production_line_name'] }}</td>
                                    <td>{{ $shift['name'] }}</td>
                                    <td>{{ $shift['start_time'] }}</td>
                                    {{-- Jam selesai yang lebih kecil ditulis '+1' supaya tidak
                                         terbaca sebagai salah input. --}}
                                    <td>{{ $shift['end_time'] }}{{ $shift['crosses_midnight'] ? ' +1' : '' }}</td>
                                    <td>{{ $shift['duration_hours_text'] }} jam</td>
                                    <td>
                                        @if ($confirmingDeleteId === $shift['id'])
                                            <span class="ks-confirm">
                                                <span class="ks-confirm-label" data-testid="delete-confirm-label">
                                                    Hapus shift {{ $confirmingDeleteLabel }}?
                                                </span>
                                                <button
                                                    type="button"
                                                    class="btn btn-destructive btn-sm"
                                                    wire:click="confirmDelete"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDelete"
                                                    data-testid="confirm-delete-button"
                                                >
                                                    <x-busy-label target="confirmDelete" busy="Menghapus…">Ya, Hapus</x-busy-label>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline btn-sm"
                                                    wire:click="cancelDelete"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDelete"
                                                >Batal</button>
                                            </span>
                                        @else
                                            <div class="ks-row-actions">
                                                <button
                                                    type="button"
                                                    class="btn btn-outline btn-sm"
                                                    wire:click="openEditForm('{{ $shift['id'] }}')"
                                                    data-testid="edit-button-{{ $shift['id'] }}"
                                                >Ubah</button>
                                                <button
                                                    type="button"
                                                    class="btn btn-destructive btn-sm"
                                                    wire:click="askDelete('{{ $shift['id'] }}')"
                                                    data-testid="delete-button-{{ $shift['id'] }}"
                                                >Hapus</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="ks-row-note" colspan="6" data-testid="shift-table-empty">
                                        Belum ada satu pun shift pada cakupan ini. Tambahkan shift lewat tombol
                                        “+ Tambah Shift” di atas.
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Line yang belum punya satu pun shift tidak punya baris data,
                                 jadi ia dinyatakan sebagai CATATAN — bukan sebagai baris kosong
                                 yang terbaca seperti shift tanpa jam. --}}
                            @foreach ($coverage as $line)
                                @if ($line['state'] === 'unconfigured')
                                    <tr wire:key="unconfigured-row-{{ $line['production_line_id'] }}">
                                        <td
                                            class="ks-row-note"
                                            colspan="6"
                                            data-testid="unconfigured-row-{{ $line['production_line_id'] }}"
                                        >
                                            {{ $line['production_line_name'] }} — belum diatur, belum punya satu pun shift.
                                            Tambahkan shift lewat tombol “+ Tambah Shift” di atas.
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="ks-pagination">
                    <span class="ks-note" data-testid="pagination-summary">
                        Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} — {{ $meta['total'] }} shift pada
                        {{ count($coverage) }} Production Line. Urutannya mengikuti jam mulai relatif awal hari
                        produksi, sama dengan urutan blok pada timeline. Timeline tidak ikut berpindah halaman.
                    </span>
                    <span class="ks-pagination-pages">
                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage"
                            @disabled($meta['page'] <= 1)
                            data-testid="previous-page"
                        >&larr; Sebelumnya</button>
                        <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage"
                            @disabled($meta['page'] >= $meta['total_pages'])
                            data-testid="next-page"
                        >Berikutnya &rarr;</button>
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- Yang TIDAK diatur di layar ini — supaya Admin yang mencarinya di sini
         tidak menyimpulkan fiturnya belum ada. --}}
    <div class="alert alert-info" data-testid="sync-window-note">
        <div>
            <div class="alert-title">Jarak waktu sinkronisasi tidak diatur di layar ini</div>
            <div>
                Jarak itu ditetapkan <strong>per mill</strong>, bukan per shift — tempatnya di
                <strong>Master Data &rarr; Mills Setting</strong> (pengaturan mill), bersama gerbang
                sinkronisasinya. Layar ini hanya mengatur nama dan jam shift per Production Line.
            </div>
        </div>
    </div>

    {{-- ============ FORM TAMBAH / UBAH + PRATINJAU HIDUP ============ --}}
    @if ($showForm)
        <x-modal
            :title="$editingId !== null ? 'Ubah Shift' : 'Tambah Shift'"
            wide
            submit="save"
            backdrop-key="shift-form-backdrop"
        >
            <x-slot:error>
                @if ($formErrorMessage)
                    <div class="alert alert-error" role="alert" data-testid="form-error">
                        <div>{{ $formErrorMessage }}</div>
                    </div>
                @endif
            </x-slot:error>

            <div
                class="ks-form"
                wire:key="shift-form-{{ $editingId ?? 'new' }}"
                {{--
                    PRATINJAU HIDUP — SATU-SATUNYA perhitungan cakupan di sisi
                    klien, dan ia BUKAN sumber kebenaran: penolakan tetap datang
                    dari server saat Simpan. Rumusnya SAMA PERSIS dengan
                    ShiftService (offset = (menit - 420 + 1440) % 1440, durasi =
                    (selesai - mulai + 1440) % 1440 dengan 0 dibaca 1440, tindihan
                    = irisan > 0 menit), dan blok shift yang sudah ada datang dari
                    ShiftService::shiftBlocksByLine() — tidak dihitung ulang di
                    JavaScript. Nol permintaan jaringan terkirim selama mengetik:
                    field-nya wire:model biasa (deferred), jadi tidak ada round-trip
                    sampai Simpan ditekan.
                --}}
                x-data="{
                    lines: @js($previewBlocks),
                    lineId: @js($form['production_line_id'] ?? ''),
                    startTime: @js($form['start_time'] ?? ''),
                    endTime: @js($form['end_time'] ?? ''),
                    editingId: @js($editingId),
                    isValidTime(value) {
                        return /^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/.test(value || '');
                    },
                    minuteOfDay(value) {
                        const parts = (value || '').split(':');
                        return ((parseInt(parts[0], 10) % 24) * 60) + parseInt(parts[1], 10);
                    },
                    offsetOf(value) {
                        return (this.minuteOfDay(value) - 420 + 1440) % 1440;
                    },
                    durationOf(start, end) {
                        const diff = (this.minuteOfDay(end) - this.minuteOfDay(start) + 1440) % 1440;
                        return diff === 0 ? 1440 : diff;
                    },
                    pct(minutes) {
                        return (minutes / 14.4).toFixed(4);
                    },
                    timeFromOffset(offset) {
                        const minute = (420 + offset) % 1440;
                        return String(Math.floor(minute / 60)).padStart(2, '0') + ':' + String(minute % 60).padStart(2, '0');
                    },
                    segmentsFor(start, duration) {
                        const end = start + duration;
                        return end <= 1440 ? [[start, end]] : [[start, 1440], [0, end - 1440]];
                    },
                    get existingBlocks() {
                        return (this.lines[this.lineId] || []).filter((block) => block.shift_id !== this.editingId);
                    },
                    get candidate() {
                        if (! this.lineId) { return null; }
                        if (! this.isValidTime(this.startTime) || ! this.isValidTime(this.endTime)) { return null; }
                        if (this.minuteOfDay(this.startTime) === this.minuteOfDay(this.endTime)) { return null; }
                        const start = this.offsetOf(this.startTime);
                        const duration = this.durationOf(this.startTime, this.endTime);
                        const crosses = this.minuteOfDay(this.endTime) < this.minuteOfDay(this.startTime);
                        return {
                            start: start,
                            duration: duration,
                            label: this.startTime.slice(0, 5) + ' \u2014 ' + this.endTime.slice(0, 5) + (crosses ? ' +1' : '')
                        };
                    },
                    get conflicts() {
                        const candidate = this.candidate;
                        if (! candidate) { return []; }
                        const found = [];
                        const mine = this.segmentsFor(candidate.start, candidate.duration);
                        this.existingBlocks.forEach((block) => {
                            this.segmentsFor(block.start_offset_minutes, block.duration_minutes).forEach((theirs) => {
                                mine.forEach((ours) => {
                                    const start = Math.max(theirs[0], ours[0]);
                                    const end = Math.min(theirs[1], ours[1]);
                                    if (end - start > 0) {
                                        found.push({
                                            label: block.label,
                                            start: start,
                                            duration: end - start,
                                            from: this.timeFromOffset(start),
                                            to: this.timeFromOffset(end)
                                        });
                                    }
                                });
                            });
                        });
                        return found;
                    }
                }"
            >
                <div class="ks-form-grid">
                    <div class="input-group ks-form-field-wide">
                        <label class="input-label" for="ks-form-line">
                            Production Line <span class="required">*</span>
                        </label>
                        <select
                            class="input @error('form.production_line_id') error @enderror"
                            id="ks-form-line"
                            wire:model="form.production_line_id"
                            x-on:change="lineId = $event.target.value"
                            data-testid="form-line-select"
                        >
                            <option value="">-- Pilih Production Line --</option>
                            @foreach ($lineOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                        <p class="ks-note">
                            Shift melekat pada line. Memindahkan shift ke line lain memeriksa ulang tumpang
                            tindih terhadap shift di line tujuan.
                        </p>
                        @error('form.production_line_id')
                            <p class="ks-field-error" data-testid="error-production-line">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="input-group ks-form-field-wide">
                        <label class="input-label" for="ks-form-name">
                            Nama Shift <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            class="input @error('form.name') error @enderror"
                            id="ks-form-name"
                            wire:model="form.name"
                            autofocus
                            data-testid="form-name-input"
                        >
                        <p class="ks-note">
                            Unik dalam satu Production Line. Dua line berbeda boleh punya shift bernama sama.
                        </p>
                        @error('form.name')
                            <p class="ks-field-error" data-testid="error-name">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- FIELD WAKTU MENERIMA PENGETIKAN MANUAL MAUPUN PICKER,
                         keduanya, dan tidak ada input yang dinonaktifkan maupun
                         readonly di form ini — aturan form web proyek ini. --}}
                    <div class="input-group">
                        <label class="input-label" for="ks-form-start">
                            Jam Mulai <span class="required">*</span>
                        </label>
                        <input
                            type="time"
                            class="input @error('form.start_time') error @enderror"
                            id="ks-form-start"
                            wire:model="form.start_time"
                            x-on:input="startTime = $event.target.value"
                            data-testid="form-start-time-input"
                        >
                        @error('form.start_time')
                            <p class="ks-field-error" data-testid="error-start-time">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="ks-form-end">
                            Jam Selesai <span class="required">*</span>
                        </label>
                        <input
                            type="time"
                            class="input @error('form.end_time') error @enderror"
                            id="ks-form-end"
                            wire:model="form.end_time"
                            x-on:input="endTime = $event.target.value"
                            data-testid="form-end-time-input"
                        >
                        <p class="ks-note">
                            Boleh lebih awal daripada Jam Mulai — itu berarti shift melewati tengah malam
                            (23:00 — 07:00 +1). Yang tidak boleh adalah jam yang berimpit: shift berdurasi nol
                            tidak dapat menampung record.
                        </p>
                        @error('form.end_time')
                            <p class="ks-field-error" data-testid="error-end-time">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="ks-preview" data-testid="shift-preview">
                    <p class="ks-note">
                        <strong>Pratinjau</strong> — batang di bawah bergerak saat jam diketik, sehingga tumpang
                        tindih dan celah terlihat sebelum Simpan ditekan. Penolakan tetap datang dari server.
                    </p>

                    <div class="ks-scroll">
                        <div class="ks-timeline-inner">
                            <div class="ks-axis">
                                <span class="ks-axis-tick ks-axis-tick--start" style="left:0">07:00</span>
                                <span class="ks-axis-tick ks-axis-tick--secondary" style="left:16.6667%">11:00</span>
                                <span class="ks-axis-tick" style="left:33.3333%">15:00</span>
                                <span class="ks-axis-tick" style="left:50%">19:00</span>
                                <span class="ks-axis-tick" style="left:66.6667%">23:00</span>
                                <span class="ks-axis-tick ks-axis-tick--secondary" style="left:83.3333%">03:00 +1</span>
                                <span class="ks-axis-tick ks-axis-tick--end" style="left:95.8333%">06:00 +1</span>
                            </div>

                            <div class="ks-track" data-testid="preview-track">
                                <template x-for="block in existingBlocks" x-bind:key="block.shift_id">
                                    <div
                                        class="ks-block ks-block-shift"
                                        x-bind:style="'left:' + pct(block.start_offset_minutes) + '%;width:' + pct(block.duration_minutes) + '%'"
                                    >
                                        <span class="ks-block-name" x-text="block.label"></span>
                                        <span class="ks-block-time" x-text="block.time_label"></span>
                                    </div>
                                </template>

                                {{-- Jam yang baru terisi separuh: pratinjau MENAHAN DIRI
                                     alih-alih menggambar blok yang salah, dan tidak
                                     menampilkan galat sampai Admin selesai mengetik. --}}
                                <template x-if="candidate">
                                    <div
                                        class="ks-block ks-block-candidate"
                                        x-bind:style="'left:' + pct(candidate.start) + '%;width:' + pct(candidate.duration) + '%'"
                                        data-testid="preview-candidate"
                                    >
                                        <span class="ks-block-name">Shift ini</span>
                                        <span class="ks-block-time" x-text="candidate.label"></span>
                                    </div>
                                </template>

                                <template x-for="(conflict, index) in conflicts" x-bind:key="index">
                                    <div
                                        class="ks-block ks-block-conflict"
                                        x-bind:style="'left:' + pct(conflict.start) + '%;width:' + pct(conflict.duration) + '%'"
                                        data-testid="preview-conflict"
                                    ></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <template x-if="conflicts.length > 0">
                        <p class="ks-preview-warning" data-testid="preview-conflict-note">
                            <template x-for="(conflict, index) in conflicts" x-bind:key="index">
                                <span
                                    x-text="'Bertindih dengan shift ' + conflict.label + ' pada ' + conflict.from + '–' + conflict.to + '. '"
                                ></span>
                            </template>
                        </p>
                    </template>
                </div>
            </div>

            <x-slot:actions>
                <button type="button" class="btn btn-outline" wire:click="closeForm">Batal</button>
                <button
                    type="submit"
                    class="btn btn-primary"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    data-testid="save-button"
                >
                    <x-busy-label target="save" busy="Menyimpan…">Simpan</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    <style>
        /* ─────────────────────────────────────────────────────────────────
           screen-170--kelola-shift — gaya khusus halaman ini.

           Hanya VARIABEL token, tanpa satu pun literal warna/radius/shadow:
           token-nya datang dari partials/design-tokens.blade.php yang sudah
           dipasang layout x-layouts.app, sama seperti mock yang disetujui
           (.asdlc/generated/2-business-spec/screens/html/screen-170--kelola-shift.html).

           TIAP KELAS DI MARKUP DI ATAS PUNYA DEFINISI DI SINI atau di
           partials/components-styles.blade.php. Kelas tanpa definisi lolos
           test perilaku tanpa suara — kelola-machinery.blade.php pernah
           membawa lima di antaranya melewati 25 test Livewire dan 19 spec
           browser, karena yang rusak adalah tampilan sementara test menguji
           perilaku.
           ───────────────────────────────────────────────────────────────── */

        /* MINIMUM 14px (uiux_spec.accessibility.text_content.min_font_size).
           Aset bersama menetapkan .badge dan .btn-sm pada --font-size-xs
           (12px); layar ini mengangkat keduanya ke --font-size-sm (14px).
           Tanpa ukuran literal. */
        .ks-page .badge,
        .ks-page .btn-sm { font-size: var(--font-size-sm); }
        .ks-page .btn-sm { padding: var(--space-xs) var(--space-sm); }

        .ks-filter-bar {
            display: flex;
            /* flex-start, BUKAN flex-end: grup Production Line lebih tinggi
               karena memuat keterangan di bawah select-nya, dan bottom-align
               memaksa label serta field Mill turun relatif terhadap
               tetangganya sehingga kedua label tidak sebaris. */
            align-items: flex-start;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
        }
        .ks-filter-bar .input-group { min-width: 220px; flex: 1 1 220px; }

        .ks-note {
            font-size: var(--font-size-sm);
            color: var(--color-muted-foreground);
            line-height: var(--line-height-base);
        }
        .ks-note strong { color: var(--color-foreground); }
        .ks-card { margin-bottom: var(--space-xl); }

        .ks-link { color: var(--color-brand); font-weight: var(--font-weight-sm); }

        .ks-guide { display: flex; flex-direction: column; gap: var(--space-sm); }
        .ks-guide-title {
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-h3);
            color: var(--color-foreground);
        }

        /* ---- Timeline ------------------------------------------------- */
        /* Kartu menggulir track-nya sendiri secara mendatar; HALAMAN tidak
           pernah menggulir mendatar. */
        .ks-scroll { overflow-x: auto; }
        /* Lebar minimum track: cukup lebar agar (a) label '03:00 +1' dan
           '06:00 +1' tidak pernah bersentuhan dan (b) blok celah 4 jam masih
           memuat keterangannya. Di bawah ini kartunya menggulir — halamannya
           tidak pernah. */
        .ks-timeline-inner { min-width: 800px; }

        .ks-axis {
            position: relative;
            height: calc(var(--space-lg) + var(--space-xs));
            margin-bottom: var(--space-sm);
        }
        .ks-axis-tick {
            position: absolute;
            top: 0;
            transform: translateX(-50%);
            font-size: var(--font-size-sm);
            color: var(--color-muted-foreground);
            white-space: nowrap;
        }
        .ks-axis-tick--start { transform: none; }
        .ks-axis-tick--end   { transform: translateX(-100%); }

        .ks-line { padding: var(--space-md) 0; }
        .ks-line + .ks-line { border-top: 1px solid var(--color-border); }
        .ks-line-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: var(--space-md);
            flex-wrap: wrap;
            margin-bottom: var(--space-sm);
        }
        .ks-line-name {
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-h3);
            color: var(--color-foreground);
        }
        .ks-line-meta { font-size: var(--font-size-sm); color: var(--color-muted-foreground); }
        .ks-line-status { display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap; }
        /* Penanda semantik untuk asersi badge status — ukuran dan warnanya
           sudah datang dari .badge + .badge-* aset bersama, jadi aturan ini
           hanya memastikan ia tidak pernah ikut terpotong teksnya. */
        .ks-coverage-badge { white-space: nowrap; }

        .ks-track {
            position: relative;
            height: calc(var(--space-2xl) + var(--space-md));
            border: 1px solid var(--color-border);
            border-radius: var(--radius-input);
            background-color: var(--color-background);
            /* Garis kisi tiap 4 jam, selaras dengan sumbu jam di atasnya */
            background-image: repeating-linear-gradient(
                to right,
                var(--color-border) 0 1px,
                transparent 1px 16.6667%
            );
            overflow: hidden;
        }

        .ks-block {
            position: absolute;
            top: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            overflow: hidden;
        }
        .ks-block-name {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-h3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ks-block-time {
            font-size: var(--font-size-sm);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ks-block-shift {
            background: var(--color-brand);
            color: var(--color-background);
            border-right: 1px solid var(--color-background);
        }
        /* Celah: BERTEKSTUR, bukan ruang putih — ruang putih terbaca sebagai
           latar kartu dan bukan sebagai temuan. */
        .ks-block-gap {
            background-color: var(--color-surface);
            background-image: repeating-linear-gradient(
                45deg,
                color-mix(in srgb, var(--color-warning) 38%, transparent) 0 var(--space-xs),
                transparent var(--space-xs) var(--space-sm)
            );
            border: 1px dashed var(--color-warning);
            color: var(--color-foreground);
        }
        /* "Belum diatur" sengaja TIDAK digambar sebagai celah — belum
           dikerjakan bukan temuan yang sama dengan keputusan yang mungkin
           salah, jadi blok ini POLOS tanpa arsiran. */
        .ks-block-empty {
            background: var(--color-surface);
            color: var(--color-muted-foreground);
            flex-direction: row;
            align-items: center;
            justify-content: center;
        }

        .ks-legend {
            display: flex;
            gap: var(--space-lg);
            flex-wrap: wrap;
            margin-top: var(--space-md);
        }
        .ks-legend-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: var(--font-size-sm);
            color: var(--color-muted-foreground);
        }
        .ks-swatch {
            width: var(--space-lg);
            height: var(--space-md);
            border-radius: var(--radius-input);
            flex-shrink: 0;
        }
        .ks-swatch-shift { background: var(--color-brand); }
        .ks-swatch-gap {
            background-color: var(--color-surface);
            background-image: repeating-linear-gradient(
                45deg,
                color-mix(in srgb, var(--color-warning) 38%, transparent) 0 var(--space-xs),
                transparent var(--space-xs) var(--space-sm)
            );
            border: 1px dashed var(--color-warning);
        }
        .ks-swatch-empty { background: var(--color-surface); border: 1px solid var(--color-border); }

        /* ---- Tabel ---------------------------------------------------- */
        .ks-row-actions { display: flex; gap: var(--space-xs); flex-wrap: wrap; }
        .ks-row-note { color: var(--color-muted-foreground); font-size: var(--font-size-sm); }
        /* Penanda semantik baris shift (dibedakan dari baris catatan
           .ks-row-note oleh test yang mengukur urutan baris); tampilannya
           sudah sepenuhnya datang dari aturan `td` aset bersama. */
        .ks-shift-row { vertical-align: middle; }
        .ks-confirm {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }
        .ks-confirm-label { font-size: var(--font-size-sm); color: var(--color-foreground); }
        .ks-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-md);
            flex-wrap: wrap;
            width: 100%;
        }
        .ks-pagination-pages { display: flex; gap: var(--space-xs); }

        /* ---- Form + pratinjau ----------------------------------------- */
        .ks-form { display: flex; flex-direction: column; gap: var(--space-lg); }
        .ks-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-md);
        }
        .ks-form-field-wide { grid-column: 1 / -1; }
        .ks-field-error { font-size: var(--font-size-sm); color: var(--color-destructive); }

        .ks-preview {
            border-top: 1px solid var(--color-border);
            padding-top: var(--space-md);
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }
        /* Blok kandidat: shift yang sedang diketik, dibedakan dari shift yang
           sudah tersimpan dengan garis putus-putus — bukan hanya warna. */
        .ks-block-candidate {
            background: color-mix(in srgb, var(--color-brand) 25%, transparent);
            border: 2px dashed var(--color-brand);
            color: var(--color-foreground);
        }
        /* Jendela tindihan: digambar DI ATAS kedua blok, sehingga rentang yang
           bertabrakan terlihat sebagai bentuk dan bukan hanya sebagai pesan. */
        .ks-block-conflict {
            background-color: color-mix(in srgb, var(--color-destructive) 30%, transparent);
            background-image: repeating-linear-gradient(
                45deg,
                var(--color-destructive) 0 2px,
                transparent 2px var(--space-sm)
            );
            border: 1px solid var(--color-destructive);
        }
        .ks-preview-warning {
            font-size: var(--font-size-sm);
            color: var(--color-destructive);
            line-height: var(--line-height-base);
        }

        @media (max-width: 767px) {
            /* Lebar sempit: label jam DIJARANGKAN, tidak pernah diperkecil di
               bawah 14px. */
            .ks-timeline-inner { min-width: 700px; }
            .ks-axis-tick--secondary { display: none; }
            .ks-form-grid { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
</div>
