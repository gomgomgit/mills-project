{{--
    "Struktur Mills" — screen-127--master-data-tree-view (REVAMP 2026-10-06).

    Papan kartu berpusat pada MILL, bukan pohon. Satu kartu per Business
    Unit membawa logo/nama/kode-nya, jejak induknya sebagai breadcrumb
    'corporate > company', dan SELURUH Production Line-nya sebagai baris di
    dalam kartu — jadi satu kartu menjawab pertanyaan yang dulu menuntut
    tiga kali expand. Di bawahnya dua daftar ringkas Corporate dan Company,
    lengkap dengan tambah/ubah/hapus: Corporate tanpa Company dan Company
    tanpa mill tidak muncul di kartu mana pun, sehingga daftar itulah
    satu-satunya tempat keduanya terlihat.

    TIDAK ADA <style> DI BERKAS INI. Seluruh CSS layar ini dimuat lewat slot
    `styles` milik layout (master-data/tree-view.blade.php ->
    master-data/partials/master-data-styles.blade.php). Livewire 3 memasang
    wire:id pada elemen ter-render pertama; <style> di dalam/di atas root
    komponen membuat atribut itu menempel pada <style> dan SELURUH
    wire:model berhenti bekerja. Jangan pindahkan CSS ke sini.

    TIDAK ADA lipat/buka di halaman ini — ia menyembunyikan data yang sudah
    termuat. Paginasi ada tetapi HANYA DIRENDER bila isinya melebihi
    $perPage. Berapa volumenya tergantung basis datanya: di basis data dev
    mill-nya 5 sehingga tidak satu pun kontrol halaman muncul, sementara di
    basis data e2e ia 19 (plus ~15 yang ditanam spec) sehingga paginasinya
    JUSTRU muncul. Jangan menuliskan "tidak pernah muncul" di sini lagi —
    klaim itu pernah membuat tiga skenario browser mustahil lolos.
    Production Line tidak pernah dipaginasi: angka pada kartu berasal dari
    withCount, yaitu jumlah SEBENARNYA.
--}}
@php
    $millTotal = (int) ($millMeta['total'] ?? 0);
    $millPerPage = (int) ($millMeta['per_page'] ?? $perPage);
    $millTotalPages = (int) ($millMeta['total_pages'] ?? 1);
    $millFrom = $millTotal === 0 ? 0 : (($page - 1) * $millPerPage) + 1;
    $millTo = min($page * $millPerPage, $millTotal);

    $corporateTotal = (int) ($corporateMeta['total'] ?? 0);
    $corporatePerPage = (int) ($corporateMeta['per_page'] ?? $perPage);
    $corporateTotalPages = (int) ($corporateMeta['total_pages'] ?? 1);
    $corporateFrom = $corporateTotal === 0 ? 0 : (($corporatePage - 1) * $corporatePerPage) + 1;
    $corporateTo = min($corporatePage * $corporatePerPage, $corporateTotal);

    $companyTotal = (int) ($companyMeta['total'] ?? 0);
    $companyPerPage = (int) ($companyMeta['per_page'] ?? $perPage);
    $companyTotalPages = (int) ($companyMeta['total_pages'] ?? 1);
    $companyFrom = $companyTotal === 0 ? 0 : (($companyPage - 1) * $companyPerPage) + 1;
    $companyTo = min($companyPage * $companyPerPage, $companyTotal);
@endphp

<div
    class="sm-page"
    data-testid="struktur-mills"
    wire:loading.class="sm-page--busy"
    wire:target="save,confirmDelete,nextPage,previousPage,nextCorporatePage,previousCorporatePage,nextCompanyPage,previousCompanyPage"
>
    {{-- Pesan hasil aksi. Penolakan penghapusan TIDAK muncul di sini
         selama modal konfirmasi masih terbuka — di sana ia dirender di
         dalam modal, karena menutup modal berarti membuang satu-satunya
         informasi yang berguna (daftar penghalangnya). --}}
    @if ($successMessage)
        <div
            class="sm-toast sm-toast--success"
            role="status"
            data-testid="success-message"
            wire:key="sm-toast-success-{{ md5($successMessage) }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
        >{{ $successMessage }}</div>
    @endif

    @if ($deleteErrorMessage && $confirmingDelete === [])
        <div class="sm-toast sm-toast--error" role="alert" data-testid="delete-error">{{ $deleteErrorMessage }}</div>
    @endif

    @if ($formErrorMessage && $modalLevel === null)
        <div class="sm-toast sm-toast--error" role="alert" data-testid="form-error-page">{{ $formErrorMessage }}</div>
    @endif

    <header class="sm-page__header">
        <div>
            <h1 class="sm-page__title">Struktur Mills</h1>
            <p class="sm-page__subtitle">
                Seluruh hierarki Corporate &rarr; Company &rarr; Business Unit (mill) &rarr; Production Line
                dalam satu halaman, lengkap dengan tambah, ubah, dan hapus.
            </p>
        </div>
    </header>

    {{-- Baris ringkasan jumlah per tingkat. SELALU total seluruh data —
         bukan jumlah yang terlihat di halaman ini dan bukan jumlah yang
         lolos penyaring. Inilah yang membuktikan halaman ini tidak
         menyembunyikan apa pun; angka yang ikut mengecil saat disaring akan
         membuat Admin mengira datanya hilang. --}}
    <section class="sm-counts" data-testid="counts-bar" aria-label="Jumlah seluruh data master per tingkat">
        <div class="sm-counts__row">
            <div class="sm-counts__item" data-testid="count-corporate">
                <span class="sm-counts__num">{{ $counts['corporate'] }}</span>
                <span class="sm-counts__label">Corporate</span>
            </div>
            <div class="sm-counts__item" data-testid="count-company">
                <span class="sm-counts__num">{{ $counts['company'] }}</span>
                <span class="sm-counts__label">Company</span>
            </div>
            <div class="sm-counts__item" data-testid="count-business-unit">
                <span class="sm-counts__num">{{ $counts['business_unit'] }}</span>
                <span class="sm-counts__label">Mill</span>
            </div>
            <div class="sm-counts__item" data-testid="count-production-line">
                <span class="sm-counts__num">{{ $counts['production_line'] }}</span>
                <span class="sm-counts__label">Production Line</span>
            </div>
        </div>

        @if ($hasSearch)
            <p class="sm-counts__match" data-testid="match-note">
                Cocok dengan penyaring: {{ $millTotal }} Mill, {{ $corporateTotal }} Corporate,
                {{ $companyTotal }} Company. Angka total di atas tidak berubah.
            </p>
        @endif
    </section>

    <div class="sm-toolbar">
        <div class="sm-search">
            <label for="sm-search-input" class="sm-search__label">Saring hierarki</label>
            <input
                type="search"
                id="sm-search-input"
                class="sm-search__input"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama atau kode di keempat tingkat…"
                data-testid="search-input"
                autocomplete="off"
            >
        </div>

        @if ($hasSearch)
            <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="clearSearch" data-testid="clear-search">
                Kosongkan penyaring
            </button>
        @endif
    </div>

    {{-- ============================= PAPAN KARTU MILL ============================= --}}
    <section class="sm-section" aria-labelledby="sm-board-title">
        <div class="sm-section__head">
            <div class="sm-section__heading">
                <h2 class="sm-section__title" id="sm-board-title">Mill</h2>
                <p class="sm-section__hint">Satu kartu per mill (Business Unit), berisi seluruh Production Line miliknya.</p>
            </div>
            <div class="sm-section__actions">
                <button type="button" class="sm-btn sm-btn--primary" wire:click="openCreate('business-unit')" data-testid="add-mill-button">
                    + Mill
                </button>
                <a href="{{ route('master-data.business-units') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-business-unit">Kelola Business Unit</a>
                <a href="{{ route('master-data.production-lines') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-production-line">Kelola Production Line</a>
            </div>
        </div>

        @if (count($millCards) === 0)
            @if ($hasSearch)
                <div class="sm-empty" data-testid="mill-no-match">
                    <p class="sm-empty__title">tidak ada yang cocok dengan "{{ $searchTerm }}"</p>
                    <p class="sm-empty__text">
                        Angka pada baris ringkasan di atas tetap menampilkan total seluruh data —
                        yang kosong adalah hasil pencarian, bukan datanya.
                    </p>
                    <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="clearSearch" data-testid="clear-search-mill">
                        Kosongkan penyaring
                    </button>
                </div>
            @else
                <div class="sm-empty" data-testid="mill-empty">
                    <p class="sm-empty__title">Belum ada mill</p>
                    <p class="sm-empty__text">Tambah Business Unit (mill) untuk mulai menyusun struktur operasional.</p>
                    <button type="button" class="sm-btn sm-btn--primary sm-btn--sm" wire:click="openCreate('business-unit')" data-testid="add-mill-button-empty">
                        + Mill
                    </button>
                </div>
            @endif
        @else
            <div class="sm-board">
                @foreach ($millCards as $mill)
                    @php
                        $crumb = trim(($mill['corporate_name'] ?? '').' › '.($mill['company_name'] ?? ''), ' ›');
                    @endphp
                    <article class="sm-mill" wire:key="sm-mill-{{ $mill['id'] }}" data-testid="mill-card">
                        <header class="sm-mill__head">
                            @if ($mill['logo_url'])
                                <img
                                    class="sm-mill__logo"
                                    src="{{ $mill['logo_url'] }}"
                                    alt="Logo {{ $mill['name'] }}"
                                    data-testid="mill-logo"
                                >
                            @else
                                {{-- Penanda pengganti berisi inisial, BUKAN <img> bersrc
                                     kosong — yang akan digambar browser sebagai ikon
                                     gambar rusak dan membuat kartu terlihat gagal muat. --}}
                                <span class="sm-mill__logo sm-mill__logo--initials" aria-hidden="true" data-testid="mill-logo-fallback">
                                    {{ $mill['initials'] }}
                                </span>
                            @endif

                            <div class="sm-mill__ident">
                                {{-- Nama/kode panjang dipotong CSS (.sm-truncate), bukan PHP:
                                     nilai utuhnya tetap ada di DOM dan di atribut title,
                                     jadi tetap dapat dibaca dan disalin. --}}
                                <h3 class="sm-mill__name sm-truncate" title="{{ $mill['name'] }}" data-testid="mill-name">{{ $mill['name'] }}</h3>
                                <p class="sm-mill__code sm-truncate" title="{{ $mill['code'] }}" data-testid="mill-code">{{ $mill['code'] }}</p>
                                <p class="sm-mill__crumb sm-truncate" title="{{ $crumb }}" data-testid="mill-crumb">{{ $crumb }}</p>
                            </div>

                            <div class="sm-mill__actions">
                                <button
                                    type="button"
                                    class="sm-btn sm-btn--icon"
                                    wire:click="openEdit('business-unit', '{{ $mill['id'] }}')"
                                    aria-label="Ubah Business Unit {{ $mill['name'] }}"
                                    title="Ubah Business Unit {{ $mill['name'] }}"
                                    data-testid="edit-mill-{{ $mill['id'] }}"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                </button>
                                <button
                                    type="button"
                                    class="sm-btn sm-btn--icon sm-btn--icon-danger"
                                    wire:click="askDelete('business-unit', '{{ $mill['id'] }}')"
                                    aria-label="Hapus Business Unit {{ $mill['name'] }}"
                                    title="Hapus Business Unit {{ $mill['name'] }}"
                                    data-testid="delete-mill-{{ $mill['id'] }}"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </button>
                            </div>
                        </header>

                        <div class="sm-mill__lines-head">
                            {{-- Angka ini berasal dari withCount('productionLines'):
                                 jumlah SEBENARNYA, bukan jumlah yang terlihat. --}}
                            <span class="sm-chip" data-testid="mill-line-count">{{ $mill['production_lines_count'] }} Production Line</span>
                            <button
                                type="button"
                                class="sm-btn sm-btn--ghost sm-btn--sm"
                                wire:click="openCreate('production-line', '{{ $mill['id'] }}')"
                                aria-label="Tambah Production Line pada Business Unit {{ $mill['name'] }}"
                                title="Tambah Production Line pada Business Unit {{ $mill['name'] }}"
                                data-testid="add-line-{{ $mill['id'] }}"
                            >
                                + Line
                            </button>
                        </div>

                        {{-- Keadaan "belum ada Production Line" ditentukan dari
                             production_lines_count, BUKAN dari koleksi yang kosong
                             sesudah penyaringan — keduanya berbeda dan salah satunya
                             menyesatkan. --}}
                        @if ($mill['production_lines_count'] === 0)
                            <p class="sm-mill__no-lines" data-testid="mill-no-lines">
                                Belum ada Production Line pada mill ini.
                            </p>
                        @else
                            <ul class="sm-lines">
                                @foreach ($mill['production_lines'] as $line)
                                    <li
                                        class="sm-line{{ $line['matches'] ? ' sm-line--match' : '' }}"
                                        wire:key="sm-line-{{ $line['id'] }}"
                                        data-testid="line-row"
                                    >
                                        <div class="sm-line__main">
                                            <span class="sm-line__name sm-truncate" title="{{ $line['name'] }}" data-testid="line-name">{{ $line['name'] }}</span>
                                            @if ($line['code'])
                                                <span class="sm-line__code" data-testid="line-code">{{ $line['code'] }}</span>
                                            @endif
                                            @if ($line['matches'])
                                                <span class="sm-line__match" data-testid="line-match">cocok penyaring</span>
                                            @endif
                                            @if ($line['description'])
                                                <span class="sm-line__desc sm-truncate" title="{{ $line['description'] }}">{{ $line['description'] }}</span>
                                            @endif
                                        </div>

                                        <div class="sm-line__actions">
                                            <button
                                                type="button"
                                                class="sm-btn sm-btn--icon"
                                                wire:click="openEdit('production-line', '{{ $line['id'] }}')"
                                                aria-label="Ubah Production Line {{ $line['name'] }} pada Business Unit {{ $mill['name'] }}"
                                                title="Ubah Production Line {{ $line['name'] }} pada Business Unit {{ $mill['name'] }}"
                                                data-testid="edit-line-{{ $line['id'] }}"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="sm-btn sm-btn--icon sm-btn--icon-danger"
                                                wire:click="askDelete('production-line', '{{ $line['id'] }}')"
                                                aria-label="Hapus Production Line {{ $line['name'] }} pada Business Unit {{ $mill['name'] }}"
                                                title="Hapus Production Line {{ $line['name'] }} pada Business Unit {{ $mill['name'] }}"
                                                data-testid="delete-line-{{ $line['id'] }}"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                            </button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>

            {{-- Kontrol paginasi HANYA dirender bila papan benar-benar
                 melebihi satu halaman — dan apakah itu terjadi tergantung
                 basis datanya (dev 5 mill: tidak; e2e 19+15: ya). --}}
            @if ($millTotal > $millPerPage)
                <nav class="sm-pager" aria-label="Pagination papan kartu mill" data-testid="pagination">
                    <span class="sm-pager__summary">menampilkan {{ $millFrom }}-{{ $millTo }} dari {{ $millTotal }} mill</span>
                    <span class="sm-pager__controls">
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="previousPage" wire:loading.attr="disabled" wire:target="previousPage" @disabled($page <= 1) data-testid="mill-prev-page">Sebelumnya</button>
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="nextPage" wire:loading.attr="disabled" wire:target="nextPage" @disabled($page >= $millTotalPages) data-testid="mill-next-page">Berikutnya</button>
                    </span>
                </nav>
            @endif
        @endif
    </section>

    {{-- ========================= DAFTAR RINGKAS CORPORATE ========================= --}}
    <section class="sm-section" aria-labelledby="sm-corporate-title">
        <div class="sm-section__head">
            <div class="sm-section__heading">
                <h2 class="sm-section__title" id="sm-corporate-title">Corporate</h2>
                <p class="sm-section__hint">
                    Termasuk Corporate yang belum punya Company — keadaan itu tidak terlihat di kartu mill mana pun,
                    jadi daftar inilah satu-satunya tempatnya.
                </p>
            </div>
            <div class="sm-section__actions">
                <button type="button" class="sm-btn sm-btn--primary sm-btn--sm" wire:click="openCreate('corporate')" data-testid="add-corporate-button">
                    + Corporate
                </button>
                <a href="{{ route('master-data.corporates') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-corporate">Kelola Corporate</a>
            </div>
        </div>

        @if (count($corporateRows) === 0)
            @if ($hasSearch)
                <div class="sm-empty" data-testid="corporate-no-match">
                    <p class="sm-empty__title">tidak ada Corporate yang cocok dengan "{{ $searchTerm }}"</p>
                    <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="clearSearch" data-testid="clear-search-corporate">Kosongkan penyaring</button>
                </div>
            @else
                <div class="sm-empty" data-testid="corporate-empty">
                    <p class="sm-empty__title">Belum ada Corporate</p>
                    <p class="sm-empty__text">Corporate adalah tingkat teratas hierarki ini — mulai dari sini.</p>
                    <button type="button" class="sm-btn sm-btn--primary sm-btn--sm" wire:click="openCreate('corporate')" data-testid="add-corporate-button-empty">+ Corporate</button>
                </div>
            @endif
        @else
            <div class="sm-table-wrap">
                <table class="sm-table" data-testid="corporate-table">
                    <thead class="sm-table__head">
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Kode</th>
                            <th scope="col" class="sm-table__num">Jumlah Company</th>
                            <th scope="col" class="sm-table__actions-head">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($corporateRows as $row)
                            <tr class="sm-table__row" wire:key="sm-corporate-{{ $row['id'] }}" data-testid="corporate-row">
                                <td><span class="sm-truncate sm-truncate--cell" title="{{ $row['name'] }}">{{ $row['name'] }}</span></td>
                                <td><span class="sm-truncate sm-truncate--cell" title="{{ $row['code'] }}">{{ $row['code'] }}</span></td>
                                {{-- Angka nol DICETAK, bukan sel kosong: sel kosong
                                     terbaca sebagai gagal render, bukan sebagai nol. --}}
                                <td class="sm-table__num" data-testid="corporate-company-count">{{ $row['companies_count'] }}</td>
                                <td class="sm-table__actions">
                                    <button
                                        type="button"
                                        class="sm-btn sm-btn--icon"
                                        wire:click="openEdit('corporate', '{{ $row['id'] }}')"
                                        aria-label="Ubah Corporate {{ $row['name'] }}"
                                        title="Ubah Corporate {{ $row['name'] }}"
                                        data-testid="edit-corporate-{{ $row['id'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="sm-btn sm-btn--icon sm-btn--icon-danger"
                                        wire:click="askDelete('corporate', '{{ $row['id'] }}')"
                                        aria-label="Hapus Corporate {{ $row['name'] }}"
                                        title="Hapus Corporate {{ $row['name'] }}"
                                        data-testid="delete-corporate-{{ $row['id'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($corporateTotal > $corporatePerPage)
                <nav class="sm-pager" aria-label="Pagination daftar Corporate" data-testid="pagination-corporate">
                    <span class="sm-pager__summary">menampilkan {{ $corporateFrom }}-{{ $corporateTo }} dari {{ $corporateTotal }} Corporate</span>
                    <span class="sm-pager__controls">
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="previousCorporatePage" wire:loading.attr="disabled" wire:target="previousCorporatePage" @disabled($corporatePage <= 1) data-testid="corporate-prev-page">Sebelumnya</button>
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="nextCorporatePage" wire:loading.attr="disabled" wire:target="nextCorporatePage" @disabled($corporatePage >= $corporateTotalPages) data-testid="corporate-next-page">Berikutnya</button>
                    </span>
                </nav>
            @endif
        @endif
    </section>

    {{-- ========================== DAFTAR RINGKAS COMPANY ========================== --}}
    <section class="sm-section" aria-labelledby="sm-company-title">
        <div class="sm-section__head">
            <div class="sm-section__heading">
                <h2 class="sm-section__title" id="sm-company-title">Company</h2>
                <p class="sm-section__hint">
                    Termasuk Company yang belum punya mill — dengan alasan yang sama seperti daftar Corporate di atas.
                </p>
            </div>
            <div class="sm-section__actions">
                <button type="button" class="sm-btn sm-btn--primary sm-btn--sm" wire:click="openCreate('company')" data-testid="add-company-button">
                    + Company
                </button>
                <a href="{{ route('master-data.companies') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-company">Kelola Company</a>
            </div>
        </div>

        @if (count($companyRows) === 0)
            @if ($hasSearch)
                <div class="sm-empty" data-testid="company-no-match">
                    <p class="sm-empty__title">tidak ada Company yang cocok dengan "{{ $searchTerm }}"</p>
                    <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="clearSearch" data-testid="clear-search-company">Kosongkan penyaring</button>
                </div>
            @else
                <div class="sm-empty" data-testid="company-empty">
                    <p class="sm-empty__title">Belum ada Company</p>
                    <p class="sm-empty__text">Company berada di antara Corporate dan mill.</p>
                    <button type="button" class="sm-btn sm-btn--primary sm-btn--sm" wire:click="openCreate('company')" data-testid="add-company-button-empty">+ Company</button>
                </div>
            @endif
        @else
            <div class="sm-table-wrap">
                <table class="sm-table" data-testid="company-table">
                    <thead class="sm-table__head">
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Kode</th>
                            <th scope="col">Corporate</th>
                            <th scope="col" class="sm-table__num">Jumlah Mill</th>
                            <th scope="col" class="sm-table__actions-head">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($companyRows as $row)
                            <tr class="sm-table__row" wire:key="sm-company-{{ $row['id'] }}" data-testid="company-row">
                                <td><span class="sm-truncate sm-truncate--cell" title="{{ $row['name'] }}">{{ $row['name'] }}</span></td>
                                <td><span class="sm-truncate sm-truncate--cell" title="{{ $row['code'] }}">{{ $row['code'] }}</span></td>
                                <td><span class="sm-truncate sm-truncate--cell" title="{{ $row['corporate_name'] }}">{{ $row['corporate_name'] }}</span></td>
                                <td class="sm-table__num" data-testid="company-mill-count">{{ $row['business_units_count'] }}</td>
                                <td class="sm-table__actions">
                                    <button
                                        type="button"
                                        class="sm-btn sm-btn--icon"
                                        wire:click="openEdit('company', '{{ $row['id'] }}')"
                                        aria-label="Ubah Company {{ $row['name'] }}"
                                        title="Ubah Company {{ $row['name'] }}"
                                        data-testid="edit-company-{{ $row['id'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="sm-btn sm-btn--icon sm-btn--icon-danger"
                                        wire:click="askDelete('company', '{{ $row['id'] }}')"
                                        aria-label="Hapus Company {{ $row['name'] }}"
                                        title="Hapus Company {{ $row['name'] }}"
                                        data-testid="delete-company-{{ $row['id'] }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($companyTotal > $companyPerPage)
                <nav class="sm-pager" aria-label="Pagination daftar Company" data-testid="pagination-company">
                    <span class="sm-pager__summary">menampilkan {{ $companyFrom }}-{{ $companyTo }} dari {{ $companyTotal }} Company</span>
                    <span class="sm-pager__controls">
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="previousCompanyPage" wire:loading.attr="disabled" wire:target="previousCompanyPage" @disabled($companyPage <= 1) data-testid="company-prev-page">Sebelumnya</button>
                        <button type="button" class="sm-btn sm-btn--ghost sm-btn--sm" wire:click="nextCompanyPage" wire:loading.attr="disabled" wire:target="nextCompanyPage" @disabled($companyPage >= $companyTotalPages) data-testid="company-next-page">Berikutnya</button>
                    </span>
                </nav>
            @endif
        @endif
    </section>

    {{-- Keterangan batas hierarki — selalu tampil. Ketiadaan yang tidak
         dijelaskan terbaca sebagai data yang hilang, bukan sebagai batas
         yang disengaja; dan batas itu tidak boleh jadi jalan buntu, jadi
         tautannya ada di sini. --}}
    <section class="sm-note" data-testid="hierarchy-boundary">
        <h2 class="sm-note__title">Batas hierarki halaman ini</h2>
        <p class="sm-note__text">
            Hierarki di halaman ini berhenti di Production Line. Station, Machinery Group, dan Machinery
            sengaja tidak ditampilkan maupun dikelola di sini — ketiganya tetap dikelola di layarnya sendiri.
        </p>
        <div class="sm-note__links">
            <a href="{{ route('master-data.stations') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-station">Kelola Station</a>
            <a href="{{ route('master-data.machinery') }}" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="link-kelola-machinery">Kelola Machinery</a>
        </div>
    </section>

    {{-- ================== SATU MODAL FORM UNTUK KEEMPAT TINGKAT ================== --}}
    @if ($modalLevel !== null)
        <x-modal
            :title="($modalMode === 'edit' ? 'Ubah ' : 'Tambah ').$levelLabel"
            wide
            submit="save"
            backdrop-key="sm-form-modal-{{ $modalLevel }}"
        >
            <x-slot:error>
                @if ($formErrorMessage)
                    <div class="sm-alert sm-alert--error" role="alert" data-testid="form-error">{{ $formErrorMessage }}</div>
                @endif
            </x-slot:error>

            @foreach ($formGroups as $group)
                <div class="sm-form-section">
                    <h4 class="sm-form-section__title">{{ $group['title'] }}</h4>
                    <div class="sm-form-grid">
                        @if ($loop->first && $parentField)
                            {{-- Select induk: TERISI bila modal dibuka dari dalam kartu
                                 induknya, tetapi TIDAK disabled, TIDAK readonly, dan
                                 TIDAK hidden (business rule 8 + uiux-spec
                                 component_patterns[web-form-input]). Mengunci akan
                                 membuat satu-satunya cara memperbaiki salah tempat
                                 adalah membatalkan lalu mengulang dari kartu yang
                                 benar. Nilai induk dibaca dari $form saat save(),
                                 bukan dari parameter openCreate(). --}}
                            <div class="sm-field sm-field--span2">
                                <label for="sm-parent-select" class="sm-field__label">
                                    {{ $parentLabel }} <span class="sm-field__required">*</span>
                                </label>
                                <select
                                    id="sm-parent-select"
                                    wire:model="form.{{ $parentField }}"
                                    class="sm-field__input sm-field__input--select @error('form.'.$parentField) sm-field__input--error @enderror"
                                    data-testid="parent-select"
                                >
                                    <option value="">-- Pilih {{ $parentLabel }} --</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.'.$parentField)
                                    <p class="sm-field__error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @foreach ($group['fields'] as $field)
                            <div class="sm-field{{ $field['wide'] ? ' sm-field--span2' : '' }}">
                                <label for="sm-field-{{ $field['name'] }}" class="sm-field__label">
                                    {{ $field['label'] }}
                                    @if ($field['required'])
                                        <span class="sm-field__required">*</span>
                                    @endif
                                </label>

                                @if ($field['type'] === 'textarea')
                                    <textarea
                                        id="sm-field-{{ $field['name'] }}"
                                        rows="3"
                                        wire:model="form.{{ $field['name'] }}"
                                        class="sm-field__input @error('form.'.$field['name']) sm-field__input--error @enderror"
                                        data-testid="field-{{ $field['name'] }}"
                                    ></textarea>
                                @else
                                    <input
                                        type="{{ $field['type'] === 'email' ? 'email' : 'text' }}"
                                        id="sm-field-{{ $field['name'] }}"
                                        wire:model="form.{{ $field['name'] }}"
                                        class="sm-field__input @error('form.'.$field['name']) sm-field__input--error @enderror"
                                        data-testid="field-{{ $field['name'] }}"
                                    >
                                @endif

                                @error('form.'.$field['name'])
                                    <p class="sm-field__error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($this->hasLogo())
                <div class="sm-form-section">
                    <h4 class="sm-form-section__title">Logo</h4>
                    <div class="sm-form-grid">
                        <div class="sm-field sm-field--span2">
                            <label for="sm-field-logo" class="sm-field__label">Logo (JPG/PNG, maksimal 2MB)</label>
                            <input
                                type="file"
                                id="sm-field-logo"
                                wire:model="logo"
                                accept=".jpg,.jpeg,.png"
                                class="sm-field__input sm-field__input--file @error('logo') sm-field__input--error @enderror"
                                data-testid="field-logo"
                            >
                            @if ($existingLogoName)
                                {{-- Nama berkas logo yang sedang dipakai ditampilkan
                                     sebagai keterangan — bukan diisikan ke input file,
                                     yang secara teknis tidak bisa dan secara makna
                                     salah. $logo null berarti "jangan ubah logo",
                                     bukan "hapus logo". --}}
                                <p class="sm-field__hint" data-testid="existing-logo-name">
                                    Logo saat ini: {{ $existingLogoName }}. Biarkan kosong untuk mempertahankannya.
                                </p>
                            @endif
                            @error('logo')
                                <p class="sm-field__error" data-testid="logo-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <x-slot:actions>
                <button type="button" class="sm-btn sm-btn--ghost" wire:click="closeModal" data-testid="cancel-button">Batal</button>
                <button type="submit" class="sm-btn sm-btn--primary" wire:loading.attr="disabled" wire:target="save" data-testid="save-button">
                    <x-busy-label target="save" busy="Menyimpan…">Simpan</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    {{-- ========================= MODAL KONFIRMASI HAPUS ========================= --}}
    @if ($confirmingDelete !== [])
        @php $confirmLabel = $levelLabels[$confirmingDelete['level']] ?? ''; @endphp
        <x-modal title="Konfirmasi Hapus" backdrop-key="sm-delete-modal">
            <p class="sm-confirm__text" data-testid="delete-confirm-text">
                Hapus {{ $confirmLabel }} <strong>{{ $confirmingDelete['name'] }}</strong>?
                @if ($confirmingDelete['level'] === 'production-line')
                    Seluruh Station milik Production Line ini ikut terhapus bersamanya.
                @endif
            </p>

            {{-- Penolakan dirender DI DALAM modal yang TETAP TERBUKA, apa
                 adanya dari service — termasuk jumlah tiap penghalangnya.
                 Menutup modal lalu menampilkan toast merah akan membuang
                 satu-satunya informasi yang berguna. --}}
            @if ($deleteErrorMessage)
                <div class="sm-alert sm-alert--error" role="alert" data-testid="delete-refusal">{{ $deleteErrorMessage }}</div>
            @endif

            <x-slot:actions>
                <button type="button" class="sm-btn sm-btn--ghost" wire:click="cancelDelete" data-testid="cancel-delete-button">Batal</button>
                <button type="button" class="sm-btn sm-btn--danger" wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete" data-testid="confirm-delete-button">
                    <x-busy-label target="confirmDelete" busy="Menghapus…">Ya, Hapus</x-busy-label>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif
</div>
