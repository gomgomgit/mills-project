{{--
    report-filter-bar — toolbar filter bersama untuk Laporan Stasiun (pemilih,
    screen-140) dan seluruh laporan periode per stasiun (screen-129 s/d
    screen-139).

    Dibuat 2026-10-05 ("filter laporan stasiun agar lebih rapih"): sebelumnya
    tiap blade laporan menulis sendiri dua <select> selebar 50% + teks bantu
    panjang, dengan opsi periode berbentuk satu string panjang
    ("fdsf (01 Okt 2026 – 09 Okt 2026) — Draft · Sterilizer"). Sekarang satu
    komponen: field ringkas berlabel + ikon, lebar wajar dalam satu baris di
    desktop, bertumpuk rapi di ponsel, dan satu catatan pendek di bawahnya.

    PERILAKU TIDAK BERUBAH: wire:model.live, id, dan data-testid yang dipakai
    test (mill-select/mill-selector, production-line-select, period-select/
    period-selector, export-*) diteruskan apa adanya lewat props, karena tiap
    layar lama memakai nama testid yang sedikit berbeda.

    Konvensi web: TIDAK ADA input disabled/readonly. Mill akun terikat
    (Supervisor / Mill Management) dirender sebagai keterangan statis, bukan
    <select> yang dikunci.

    Kelas CSS-nya (md-filters, md-field*, md-badge*, md-sr) didefinisikan di
    dashboard/partials/report-styles.blade.php.
--}}
@props([
    'isAdmin' => false,
    // Mill — <select> untuk Admin, keterangan statis untuk akun terikat.
    'businessUnitOptions' => [],
    'businessUnitId' => null,
    'millTestid' => 'mill-select',
    'millName' => null,
    'millNameTestid' => 'mill-current',
    // Layar pemilih (/reports) menandai mill terpilih juga bagi Admin.
    'showMillForAdmin' => false,
    // Production Line
    'showLine' => false,
    'productionLineOptions' => [],
    'selectedLineId' => null,
    'selectedLineName' => null,
    // Periode Pelaporan
    'showPeriod' => false,
    'periods' => [],
    'periodId' => null,
    'periodTestid' => 'period-select',
    // Status periode TERPILIH, tampil sebagai badge kecil di kepala field.
    'selectedPeriodStatus' => null,
    // Sterilizer & Cages Track memakai option semu "Belum ada periode";
    // laporan lain sengaja merender pemilih TANPA satu pun <option>.
    'periodPlaceholderWhenEmpty' => false,
    // Ekspor — null berarti tombol ekspor tidak dirender.
    'exportAction' => null,
    'exportCsvTestid' => 'export-csv',
    'exportExcelTestid' => 'export-excel',
])
@php
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    // Rentang ringkas: "01–09 Okt 2026", "28 Sep – 09 Okt 2026",
    // "28 Des 2025 – 03 Jan 2026" — tahun/bulan tidak diulang bila sama.
    $rentang = function (?string $start, ?string $end) use ($bulan): string {
        [$y1, $m1, $d1] = array_pad(explode('-', substr((string) $start, 0, 10)), 3, '');
        [$y2, $m2, $d2] = array_pad(explode('-', substr((string) $end, 0, 10)), 3, '');
        $b1 = $bulan[$m1] ?? $m1;
        $b2 = $bulan[$m2] ?? $m2;

        if ($y1 === $y2 && $m1 === $m2) {
            return "{$d1}–{$d2} {$b2} {$y2}";
        }

        if ($y1 === $y2) {
            return "{$d1} {$b1} – {$d2} {$b2} {$y2}";
        }

        return "{$d1} {$b1} {$y1} – {$d2} {$b2} {$y2}";
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    $iconMill = '<path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-5h6v5"/>';
    $iconLine = '<path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/>';
    $iconPeriod = '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>';
    $iconCheck = '<path d="M5 12.5l4.5 4.5L19 7.5"/>';
@endphp

<section class="md-filters" data-testid="report-filters" aria-label="Filter laporan">
    <div class="md-filters__fields">

        {{-- ============ Mill ============ --}}
        @if ($isAdmin)
            {{-- Pemilih Mill HANYA untuk Admin: Supervisor dan Mill Management
                 terkunci pada millnya sendiri, sehingga pemilih ini tidak
                 dirender sama sekali bagi mereka. --}}
            <div class="md-field md-field--mill">
                <div class="md-field__head">
                    <label class="md-field__label" for="business-unit-select">Mill</label>
                    @if ($showMillForAdmin && $millName !== null)
                        <span class="md-badge md-badge--ok" data-testid="{{ $millNameTestid }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconCheck !!}</svg>
                            Aktif<span class="md-sr">: {{ $millName }}</span>
                        </span>
                    @endif
                </div>
                <div class="md-field__box">
                    <svg class="md-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconMill !!}</svg>
                    <select id="business-unit-select" class="md-field__control md-field__control--select"
                            wire:model.live="businessUnitId" data-testid="{{ $millTestid }}">
                        <option value="">Pilih Mill</option>
                        @foreach ($businessUnitOptions as $option)
                            <option value="{{ $option['id'] }}" @selected($option['id'] === $businessUnitId)>{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @elseif ($millName !== null)
            {{-- Keterangan, bukan pemilih: mill akun tidak dapat diubah dari
                 layar ini, dan memaksa properti/query string ke mill lain
                 tidak mengubah satu angka pun. --}}
            <div class="md-field md-field--mill">
                <div class="md-field__head">
                    <span class="md-field__label">Mill</span>
                </div>
                <p class="md-field__static" data-testid="{{ $millNameTestid }}" title="Mill mengikuti akun Anda">
                    <svg class="md-field__static-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconMill !!}</svg>
                    <strong>{{ $millName }}</strong>
                </p>
            </div>
        @endif

        {{-- ============ Production Line ============
             TANPA opsi "semua": laporan menghasilkan ANGKA GABUNGAN, dan total
             yang mencampur belasan line bukan angka yang bisa ditindaklanjuti.
             Opsinya hanya line di dalam mill yang berlaku. --}}
        @if ($showLine)
            <div class="md-field md-field--line">
                <div class="md-field__head">
                    <label class="md-field__label" for="production-line-select">Production Line</label>
                    @if ($selectedLineName !== null)
                        {{-- Line yang sedang dibaca. Seluruh angka di bawah
                             milik line ini saja. --}}
                        <span class="md-badge md-badge--ok" data-testid="production-line-current">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconCheck !!}</svg>
                            Aktif<span class="md-sr">: Line aktif {{ $selectedLineName }}</span>
                        </span>
                    @endif
                </div>
                <div class="md-field__box">
                    <svg class="md-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconLine !!}</svg>
                    <select id="production-line-select" class="md-field__control md-field__control--select"
                            wire:model.live="productionLineId" data-testid="production-line-select">
                        <option value="">Pilih Line</option>
                        @foreach ($productionLineOptions as $option)
                            {{-- @selected WAJIB dirender di server. Livewire 3
                                 tidak menulis balik nilai <select> dari state
                                 komponen pada paint pertama: DOM yang dikirim
                                 server-lah sumber kebenarannya. --}}
                            <option value="{{ $option['id'] }}"
                                    @selected($selectedLineId === $option['id'])>{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        {{-- ============ Periode Pelaporan ============
             Opsi ringkas "Nama · rentang · status" — status tetap di tiap opsi
             agar periode Tertutup terbaca saat memilih; status periode
             terpilih juga tampil sebagai badge di kepala field. Jenis stasiun tidak lagi ditulis
             di tiap opsi: daftar ini sudah hanya memuat periode yang mencakup
             stasiun laporan ini. --}}
        @if ($showPeriod)
            <div class="md-field md-field--period">
                <div class="md-field__head">
                    <label class="md-field__label" for="period-select">Periode Pelaporan</label>
                    @if ($selectedPeriodStatus !== null)
                        <span class="md-badge md-badge--{{ $selectedPeriodStatus }}">{{ $statusLabel($selectedPeriodStatus) }}</span>
                    @endif
                </div>
                <div class="md-field__box">
                    <svg class="md-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconPeriod !!}</svg>
                    <select id="period-select" class="md-field__control md-field__control--select"
                            wire:model.live="periodId" data-testid="{{ $periodTestid }}">
                        @if ($periods === [] && $periodPlaceholderWhenEmpty)
                            <option value="">Belum ada periode</option>
                        @endif
                        @foreach ($periods as $period)
                            <option value="{{ $period['id'] }}" @selected($period['id'] === $periodId)>{{ $period['name'] }} &middot; {{ $rentang($period['start_date'], $period['end_date']) }} &middot; {{ $statusLabel($period['status']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    </div>

    @if ($exportAction !== null)
        {{-- Status periode (Draft/Terbuka/Tertutup) TIDAK membatasi ekspor —
             kunci periode mengatur penulisan data, bukan pembacaan laporan,
             jadi tombol tidak pernah dinonaktifkan. --}}
        <div class="md-filters__actions">
            {{-- Loading (2026-10-05): ekspor adalah request Livewire yang
                 berakhir dengan respons unduhan, jadi wire:loading selesai
                 tepat saat file tiba. Selama itu KEDUA tombol ekspor
                 dinonaktifkan sesaat (tidak ada unduhan ganda) dan tombol
                 yang diklik menampilkan spinner + "Mengekspor…". --}}
            <button type="button" class="md-btn md-btn--primary"
                    wire:click="{{ $exportAction }}('csv')" data-testid="{{ $exportCsvTestid }}"
                    wire:loading.attr="disabled" wire:target="{{ $exportAction }}">
                <x-busy-label target="{{ $exportAction }}('csv')" busy="Mengekspor…"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>Ekspor CSV</x-busy-label>
            </button>
            <button type="button" class="md-btn"
                    wire:click="{{ $exportAction }}('excel')" data-testid="{{ $exportExcelTestid }}"
                    wire:loading.attr="disabled" wire:target="{{ $exportAction }}">
                <x-busy-label target="{{ $exportAction }}('excel')" busy="Mengekspor…"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8l6 8M15 8l-6 8"/></svg>Ekspor Excel</x-busy-label>
            </button>
        </div>
    @endif

    @if (trim((string) $slot) !== '')
        <p class="md-filters__note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            <span>{{ $slot }}</span>
        </p>
    @endif
</section>
