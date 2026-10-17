<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\SolidWasteDisposalReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanSolidWasteDisposal — screen-162--laporan-solid-waste-disposal-web
 * ('Laporan Periode Solid Waste Disposal'), route name
 * `reports.solid-waste-disposal`, /reports/solid-waste-disposal.
 *
 * NAMA KELAS DAN NAMA VIEW-NYA MENGIKUTI KEENAM BELAS LAPORAN YANG SUDAH ADA
 * (LaporanWeighbridge, LaporanProcessWater, …) dan BUKAN
 * App\Livewire\Reports\SolidWasteDisposalReport. Dicatat karena nama itu tidak
 * disebut di satu artefak pun dan karenanya akan ditebak — dan tebakan yang
 * wajar pun salah di sini.
 *
 * Memakai ulang SolidWasteDisposalReportService — service yang sama persis yang
 * dipakai controller API — sehingga halaman dan API tidak pernah dapat
 * melaporkan angka yang berbeda.
 *
 * BACA SAJA: satu-satunya aksi adalah memilih mill, memilih production line,
 * memilih periode, dan mengekspor. Tidak ada apa pun di sini yang menulis ke
 * `solid_waste_disposal_records` maupun `solid_waste_disposal_details`.
 *
 * APA YANG LAYAR INI TIDAK BOLEH BIARKAN SALAH DIBACA — alasan beberapa hal di
 * halaman ini terlihat berlebihan:
 *   - TIDAK ADA SATU ANGKA 'LIMBAH KELUAR' YANG BERDIRI SENDIRI. Angka total
 *     dirender DI DALAM satu kotak bersama rekap `purpose_end_use`, karena
 *     perbedaan antara limbah yang dibuang dan limbah yang dimanfaatkan kembali
 *     adalah justru informasi yang paling dicari — baik secara lingkungan
 *     maupun komersial — dan satu angka gabungan menghapusnya. Keduanya dibuat
 *     satu benda yang tidak dapat dipisah mata, dan hubungan DOM itu
 *     (rekap berada di dalam container yang sama dengan kartu total) adalah
 *     bagian dari kontraknya, bukan kebetulan tata letak.
 *   - SETIAP RATA-RATA MENCETAK PENYEBUTNYA DI DALAM KARTUNYA SENDIRI, dan
 *     begitu pula setiap baris rekap dan setiap baris rekap harian. Rata-rata
 *     dari 99 muatan dan rata-rata dari tiga muatan tidak boleh terlihat sama
 *     meyakinkan.
 *   - 'PENIMBANGAN BELUM SELESAI' ADALAH KARTUNYA SENDIRI, bukan catatan kaki.
 *     `net_weight_mt` adalah kolom TURUNAN service (bruto − tara), jadi
 *     null-nya tidak berarti 'lupa diisi' melainkan penimbangan yang
 *     menggantung — kendaraan sudah ditimbang kotor tetapi belum kembali
 *     ditimbang kosong, atau sebaliknya. Kalimat di layar mengatakan ITU, bukan
 *     'data tidak lengkap'.
 *   - KEEMPAT REKAP KATEGORIS DITERBITKAN PER NILAI APA ADANYA, tanpa satu
 *     join pun dan tanpa pelabelan. Keempat kolomnya teks bebas; khususnya
 *     `purpose_end_use` TIDAK dilipat menjadi dua ember 'dibuang' dan
 *     'dimanfaatkan', karena nilai nyatanya belum pernah terlihat di basis data
 *     mana pun dan menebak pemetaannya sekarang berarti menanam aturan yang
 *     terlihat benar dan diam-diam salah.
 *   - TIDAK ADA SEBARAN PER JAM, DAN SEBABNYA DICETAK UTUH DI LAYAR
 *     (`_note_no_hourly`). Baris detail stasiun ini hanya membawa `event_date`;
 *     tidak ada satu pun kolom waktu padanya. Prosa itu ada justru untuk
 *     mencegah seseorang menambahkannya kemudian dengan menebak jam dari
 *     `created_at` — yang berarti menebak dari waktu INPUT, bukan waktu
 *     kejadian.
 *   - KOLOM `shift` TIDAK DITAMPILKAN SAMA SEKALI, bahkan sebagai tempat
 *     kosong, dan layar menyatakan sebabnya UTUH
 *     (`_note_shift_not_published`). Ia tetap ada di ekspor, karena ekspor
 *     adalah rincian mentah.
 *   - TIDAK ADA BLOK DOWNTIME DAN TIDAK ADA PENANDAAN NILAI DI LUAR BATAS, dan
 *     kedua ketiadaan itu dinyatakan UTUH (`_note_absences`). Kotak keterangan
 *     memakai kelas `.md-explain`, BUKAN `.md-threshold` — keduanya tampil
 *     identik, dan ketiadaan penandaan diasersi MENURUT NAMA KELAS pada HTML
 *     ter-render, sehingga memakai kelas bernama 'threshold' untuk menjelaskan
 *     bahwa tidak ada ambang akan memerahkan asersi itu justru karena namanya.
 *     Konvensi ini lahir di screen-133 dan dipakai ulang sejak screen-156.
 *   - MUATAN DRAFT IKUT TERHITUNG di seluruh angka, dan jumlahnya dinyatakan
 *     berdampingan dengan totalnya. Pembaca diberi tahu, bukan dilindungi.
 *   - DAN `undated_load_count` DICETAK BERDAMPINGAN DENGAN TOTALNYA beserta
 *     kalimat yang menyatakan hubungan keduanya. Tanpa itu, penjumlahan rekap
 *     harian yang lebih kecil daripada jumlah muatan akan selalu dicurigai
 *     sebagai bug — dan kecurigaan itu wajar, karena selisih tanpa penjelasan
 *     tidak dapat dibedakan dari cacat. (Mock layar ini tidak menampilkannya;
 *     TECH-SPEC yang otoritatif, dan ia menuntut angka itu dicetak.)
 *
 * PERAN MEMBENTUK BARIS FILTER, bukan hanya datanya:
 *   - Supervisor / Mill Management TIDAK melihat pemilih mill sama sekali —
 *     mill mereka tetap, dan menawarkan pemilih yang tidak dapat mereka pakai
 *     adalah kebohongan. Mereka mendapat keterangan nama mill.
 *   - Admin (users.business_unit_id NULL) melihat pemilihnya dan wajib
 *     memakainya.
 *   - Akun terikat yang business_unit_id-nya NULL mendapat arahan 'hubungi
 *     Admin' dan TIDAK mendapat pemilih — daftar seluruh mill tidak pernah
 *     dibaca. Aturan gagal-tertutup itulah sebab render() menyelesaikan
 *     millnya sendiri alih-alih memanggil resolveBusinessUnit() milik service,
 *     yang akan melempar.
 *
 * MEMILIH PRODUCTION LINE WAJIB, dan sampai satu line dipilih halaman ini
 * merender NOL ANGKA sekaligus TIDAK merender tombol ekspor sama sekali —
 * bukan total seluruh mill sebagai penggantinya. Satu-satunya opsi pada mill
 * ber-satu-line pun TIDAK dipilih otomatis: memilih adalah keputusan pembaca,
 * dan memilihkannya akan membuat ia menyangka sedang melihat seluruh mill.
 *
 * MENGGANTI MILL MENGOSONGKAN PERIODE SEKALIGUS PRODUCTION LINE (temuan QA
 * LP-W-09): sebuah periode DAN sebuah line masing-masing milik tepat satu mill,
 * sehingga membawa salah satunya menyeberang akan merender penolakan akses atas
 * sesuatu yang tidak dilakukan pengguna. Penjaganya ada di FUNGSI PEMUATAN
 * RINGKASAN, bukan hanya di template.
 *
 * OPERATOR TIDAK PUNYA AKSES KE LAYAR INI SAMA SEKALI, dan canAccess() di bawah
 * punya DAFTAR PERANNYA SENDIRI DENGAN SENGAJA alih-alih memanggil
 * SolidWasteDisposalReportService::guardAccess(): SERVICE-NYA MENERIMA OPERATOR
 * SEJAK COMMIT PERTAMA (pasangan mobile screen-163 dibangun dalam seri yang
 * sama dan merupakan pemanggil sahnya), sehingga bila canAccess() pernah
 * mendelegasi ke service, layar web ini terbuka untuk Operator secara senyap
 * dalam satu tarikan napas yang sama. Pertahankan duplikasinya — itulah yang
 * membuat kedua keputusan tetap independen.
 *
 * Pemilih periode memilih periode terbaru secara otomatis supaya halaman
 * berguna pada paint pertama alih-alih menuntut sebuah pilihan sebelum
 * menampilkan apa pun — kecuali persis sesudah mill berganti, tempat ia sengaja
 * tetap kosong.
 */
#[Layout('dashboard.laporan-solid-waste-disposal')]
class LaporanSolidWasteDisposal extends Component
{
    /**
     * DIBACA DARI QUERY STRING.
     *
     * Laporan Stasiun (screen-140) membawa mill di tautan tiap tile:
     * StationReportService membangun report_path sebagai
     * route($routeName, ['business_unit_id' => ..., 'production_line_id' => ...]).
     * Tanpa #[Url] di sini, Livewire tidak pernah menghidrasinya, sehingga Admin
     * yang baru memilih mill lalu menekan tile MENDARAT DI LAYAR YANG
     * MEMINTANYA MEMILIH MILL LAGI — tanpa satu angka pun termuat.
     *
     * `as: 'business_unit_id'` WAJIB — tanpa itu #[Url] memakai NAMA PROPERTI
     * sebagai kunci query ('businessUnitId'), sementara tautan yang dibangun
     * StationReportService memakai 'business_unit_id'. Keduanya tidak pernah
     * bertemu, dan layarnya tetap meminta pilih mill tanpa tanda apa pun bahwa
     * ada yang salah.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: untuk peran terikat mill,
     * resolvedBusinessUnitId() mengabaikan properti ini sepenuhnya.
     */
    #[Url(as: 'business_unit_id')]
    public string $businessUnitId = '';

    /**
     * PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN.
     *
     * Memilihnya WAJIB, dan itu berbeda dari Data Browser yang punya opsi
     * 'Semua Line': laporan menghasilkan ANGKA GABUNGAN, dan total yang
     * mencampur belasan line bukan angka yang bisa ditindaklanjuti siapa pun.
     * Selama belum dipilih, layar ini tidak menampilkan satu angka pun dan
     * tombol ekspor belum dirender.
     *
     * TIDAK MEMBUKA KEBOCORAN LINTAS MILL: keepProductionLineValid() hanya
     * menerima line yang ada di dalam daftar opsi mill yang berlaku, dan
     * resolvedProductionLineId() memakai resolveProductionLineOrNull() yang
     * memulangkan null untuk line mill lain — di layar, 'belum memilih line'
     * adalah keadaan awal yang normal dan harus merender ajakan memilih, bukan
     * halaman galat, dan nama line mill lain tidak pernah terbit di sini. Jalur
     * API yang menolak 422/403 ada di resolveProductionLine().
     */
    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

    /** Periode Pelaporan terpilih; terisi otomatis dengan yang terbaru. */
    public string $periodId = '';

    /**
     * Mill DIGANTI — bukan dipilih pertama kali — dan sesudahnya belum ada
     * periode yang dipilih manusia.
     *
     * Dipakai keepSelectionValid() untuk TIDAK mengisi periode secara otomatis
     * sesudah mill berganti. Tanpa penanda ini, pengosongan di
     * updatedBusinessUnitId() tidak pernah terlihat: render yang sama langsung
     * memilih periode terbaru mill baru, sehingga pemilih periode tampak berisi
     * sementara layar belum menampilkan satu angka pun — pembaca lalu menyangka
     * ia sedang melihat periode yang ia pilih (temuan QA LP-W-09).
     *
     * DITETAPKAN DI HOOK `updating`, BUKAN `updated`: nilai LAMA masih ada di
     * sana, dan hanya perpindahan dari satu mill ke mill LAIN yang berarti
     * 'berganti'.
     */
    public bool $millJustSwitched = false;

    /**
     * Operator tidak punya UI web di layar ini sama sekali, jadi komponen ini
     * menolak mount untuk mereka — bukan sekadar render kosong. Middleware rute
     * ('role:supervisor,mill_management,admin') menghentikannya lebih dulu di
     * peramban; penjaga ini juga menutup jalur komponen yang di-mount langsung,
     * yang justru cara skenario uji melatihnya.
     *
     * abort_unless() atas canAccess() — DAFTAR PERAN MILIK KOMPONEN INI
     * SENDIRI, bukan guardAccess() milik service. Lihat docblock kelas: service
     * MENERIMA OPERATOR sejak commit pertama, dan meminjamnya di sini akan
     * membuka halaman ini dalam satu tarikan napas yang sama.
     */
    public function mount(): void
    {
        abort_unless($this->canAccess(), 403);
    }

    public function updatingBusinessUnitId(mixed $value): void
    {
        $this->millJustSwitched = $this->businessUnitId !== ''
            && (string) $value !== $this->businessUnitId;
    }

    /**
     * Mengganti mill menjatuhkan BAIK periode MAUPUN production line:
     * masing-masing milik tepat satu mill, sehingga mempertahankan salah
     * satunya akan membocorkan atau (lebih buruk) merender penolakan akses atas
     * sesuatu yang tidak dilakukan pengguna (QA LP-W-09).
     *
     * Line dikosongkan DI SINI selain disaring keepProductionLineValid() di
     * render(): hook membuat pengosongannya dapat dibuktikan dari component test
     * yang hanya menyetel mill, sementara penyaring saat render juga menangkap
     * line yang datang lewat query string.
     */
    public function updatedBusinessUnitId(): void
    {
        $this->periodId = '';
        $this->productionLineId = '';
    }

    /**
     * Periode dipilih manusia => penanda mill-berganti dicabut, sehingga
     * perilaku normal (termasuk pemeriksaan keabsahan) berjalan seperti biasa.
     */
    public function updatedPeriodId(): void
    {
        $this->millJustSwitched = false;
    }

    /**
     * Mengalirkan muatan periode sebagai CSV/.xlsx lewat service bersama,
     * sehingga berkas yang diunduh dari halaman ini byte-per-byte sama dengan
     * yang dikembalikan endpoint API — satu baris per muatan, konteks diulang,
     * KETUJUH BELAS kolom detail (`shift`, nomor gate pass, nomor segel dan id
     * operator TERMASUK, karena ekspor adalah rincian mentah), dan muatan
     * ber-netto kosong tetap menjadi baris dengan sel kosong.
     *
     * 'excel' DITERJEMAHKAN MENJADI 'xlsx' DI SINI, dan ini bukan kemubaziran:
     * toolbar bersama (components/report-filter-bar.blade.php) memanggil aksi
     * ekspor dengan argumen harfiah 'csv' dan 'excel' untuk keenam belas laporan
     * yang sudah ada, sementara kontrak API layar ini — dan uji unitnya —
     * memakai daftar tertutup {csv, xlsx}. Menerjemahkannya di sini membuat
     * toolbar tidak perlu disentuh (menyentuhnya berarti menyentuh keenam belas
     * laporan lain) DAN daftar format service tetap tertutup persis seperti
     * spec-nya. Nilai tak dikenal apa pun diteruskan apa adanya supaya service
     * yang menolaknya dengan 422, bukan ditelan di sini.
     *
     * Memulangkan null (dan tidak melakukan apa pun) ketika belum ada periode
     * ATAU belum ada production line: belum ada yang dapat diekspor, dan itu
     * bukan galat. LINE dijaga di sini dan tidak hanya di blade, sehingga
     * panggilan langsung ke metode ini tidak dapat mengambil berkas yang
     * mencampur seluruh line mill.
     *
     * Penjaga peran, resolusi mill, resolusi line, pemeriksaan format dan batas
     * 50.000 BARIS MUATAN diperiksa EAGER di dalam service, sebelum satu byte
     * pun dialirkan — dan batas itu menghitung BARIS DETAIL, bukan record,
     * karena satu record induk dapat membawa puluhan muatan.
     *
     * Mill yang terpilih diteruskan PERSIS seperti render() meneruskannya ke
     * buildSummary(): resolvedBusinessUnitId(), bukan properti $businessUnitId
     * mentah. Tanpa itu ekspor Admin akan mencapai resolveBusinessUnit(null)
     * dan ditolak 422 meski sebuah mill JELAS terpilih di layar, sehingga
     * tombolnya akan tampak mati.
     *
     * Periode TERTUTUP terekspor persis seperti periode terbuka.
     */
    public function exportCsv(string $format = 'csv')
    {
        $productionLineId = $this->resolvedProductionLineId();

        if ($this->periodId === '' || $productionLineId === null) {
            return null;
        }

        $service = app(SolidWasteDisposalReportService::class);

        return $service->export(
            $service->authorizePeriod($this->periodId),
            $format === 'excel' ? 'xlsx' : $format,
            $this->resolvedBusinessUnitId(),
            $productionLineId,
        );
    }

    /**
     * DATA DIMUAT DI SINI, BUKAN DI mount(). Mill, line dan periode semuanya
     * dapat berubah setelah paint pertama (dan dua di antaranya datang dari
     * query string), jadi memuat di mount() berarti angkanya tertinggal satu
     * langkah di belakang pilihan yang terlihat di pemilih.
     */
    public function render()
    {
        $service = app(SolidWasteDisposalReportService::class);

        $isAdmin = $this->isAdmin();
        $businessUnitId = $this->resolvedBusinessUnitId();

        // GAGAL TERTUTUP: akun terikat tanpa mill tidak pernah mencapai
        // businessUnitOptions(), sehingga daftar seluruh mill tidak pernah
        // dibangun untuk peran yang semestinya terikat tepat satu mill.
        $hasNoMillForAccount = ! $isAdmin && $businessUnitId === null;

        $businessUnitOptions = $isAdmin ? $service->businessUnitOptions() : [];
        $productionLineOptions = [];
        $productionLineId = null;
        $periods = [];
        $summary = null;
        $forbidden = false;

        if ($businessUnitId !== null) {
            // Opsi line SELALU dibatasi mill yang berlaku, jadi line mill lain
            // tidak pernah menjadi opsi.
            $productionLineOptions = $service->productionLineOptions($businessUnitId);

            $this->keepProductionLineValid($productionLineOptions);

            $productionLineId = $this->resolvedProductionLineId();

            $periods = $service->listPeriods($businessUnitId);

            $forbidden = ! $this->keepSelectionValid($periods);

            // PERIODE TETAP PER MILL — daftar periode di atas tidak bertambah
            // dimensi line sama sekali. Yang tersaring adalah DATANYA, dan hanya
            // ketika sebuah line sudah dipilih. Penjaganya ada DI SINI, di
            // fungsi pemuatan ringkasan, bukan hanya di template.
            if (! $forbidden && $productionLineId !== null && $this->periodId !== '') {
                $summary = $service->buildSummary(
                    $service->authorizePeriod($this->periodId),
                    $businessUnitId,
                    $productionLineId,
                );
            }
        }

        return view('livewire.dashboard.laporan-solid-waste-disposal', [
            'isAdmin' => $isAdmin,
            'businessUnitOptions' => $businessUnitOptions,
            'businessUnitName' => $this->boundBusinessUnitName(),
            'periods' => $periods,
            'selectedPeriod' => collect($periods)->firstWhere('id', $this->periodId),
            'summary' => $summary,
            'productionLineOptions' => $productionLineOptions,
            // Line yang sedang dibaca, dinamai. Angka laporan tidak ada artinya
            // tanpa keterangan line mana yang menghasilkannya — itu justru
            // alasan memilih line dijadikan wajib.
            'selectedProductionLine' => collect($productionLineOptions)->firstWhere('id', $productionLineId),
            // Admin yang belum memilih mill: halaman memintanya memilih alih-alih
            // menampilkan laporan kosong.
            'needsMillSelection' => $isAdmin && $businessUnitId === null,
            // Mill sudah pasti, line belum: layar meminta memilih line dan TIDAK
            // menampilkan satu angka pun.
            'needsProductionLineSelection' => $businessUnitId !== null && $productionLineId === null,
            'hasNoMillForAccount' => $hasNoMillForAccount,
            // Id periode yang bukan milik mill ini — ditolak SECARA TERLIHAT,
            // tanpa satu angka pun dari mill lain dirender.
            'forbidden' => $forbidden,
            // HARI TERAKHIR YANG MUNGKIN TERHITUNG untuk periode yang masih
            // berjalan. Diserahkan dari sini, bukan dibaca dari jam di dalam
            // blade: satu-satunya tempat halaman ini menyentuh waktu sekarang,
            // sehingga keterangan 'dihitung sampai ...' dapat diuji tanpa
            // menyentuh DOM. Keterangan itu WAJIB menyebut tanggal ini dan BUKAN
            // end_date periode — end_date yang belum tiba akan terbaca sebagai
            // hari yang sudah ikut dihitung.
            'countedUntil' => now()->toDateString(),
        ]);
    }

    /**
     * Mill yang laporannya sedang dibaca: mill akun sendiri untuk Supervisor /
     * Mill Management (tidak pernah dapat dinegosiasikan dari UI), mill yang
     * dipilih untuk Admin, null ketika Admin belum memilih atau ketika akun
     * terikat tidak punya mill sama sekali.
     */
    protected function resolvedBusinessUnitId(): ?string
    {
        if ($this->isAdmin()) {
            return $this->businessUnitId !== '' ? $this->businessUnitId : null;
        }

        // $this->businessUnitId sengaja TIDAK dibaca di sini.
        $businessUnitId = (string) (auth()->user()?->business_unit_id ?? '');

        return $businessUnitId !== '' ? $businessUnitId : null;
    }

    /**
     * Nama mill yang ditampilkan sebagai keterangan bagi peran terikat. Dibaca
     * dari relasi akunnya sendiri, bukan dari periode terpilih — keterangan itu
     * harus tetap benar bahkan sebelum sebuah periode dipilih, dan tidak boleh
     * pernah menggemakan mill yang hanya diminta pengguna.
     *
     * Tidak berbiaya kueri untuk akun yang business_unit_id-nya null (belongsTo
     * berkunci null selesai menjadi null tanpa menyentuh basis data), dan itulah
     * yang menjaga keadaan gagal-tertutup tetap nol kueri.
     */
    protected function boundBusinessUnitName(): string
    {
        if ($this->isAdmin()) {
            return '';
        }

        return (string) (auth()->user()?->businessUnit?->name ?? '');
    }

    /**
     * Line yang benar-benar dipakai untuk menyaring angka, atau null bila belum
     * ada pilihan yang sah.
     *
     * Memakai resolveProductionLineOrNull(), BUKAN resolveProductionLine(): di
     * layar, 'belum memilih line' adalah keadaan awal yang normal dan harus
     * merender ajakan memilih, bukan halaman galat 422 — sementara sebuah line
     * milik mill LAIN jatuh ke keadaan yang sama persis, yang juga berarti nama
     * line mill lain tidak pernah terbit di sini.
     */
    protected function resolvedProductionLineId(): ?string
    {
        $businessUnitId = $this->resolvedBusinessUnitId();

        if ($businessUnitId === null || $this->productionLineId === '') {
            return null;
        }

        return app(SolidWasteDisposalReportService::class)
            ->resolveProductionLineOrNull($businessUnitId, $this->productionLineId);
    }

    /**
     * Membuang line yang tidak ada di dalam mill yang berlaku — line mill lain
     * lewat query string, atau sisa pilihan setelah Admin berganti mill. Jatuh
     * ke 'belum memilih' (yang merender arahan memilih), bukan ke line pertama:
     * memilih line adalah keputusan pembaca laporan, dan menebaknya akan
     * menghasilkan angka yang tidak ia minta — termasuk pada mill yang hanya
     * punya SATU line, yang sengaja tetap tidak dipilih otomatis.
     *
     * @param  list<array{id: string, name: string}>  $options
     */
    protected function keepProductionLineValid(array $options): void
    {
        if ($this->productionLineId === '') {
            return;
        }

        if (! in_array($this->productionLineId, array_column($options, 'id'), true)) {
            $this->productionLineId = '';
        }
    }

    protected function isAdmin(): bool
    {
        return $this->role() === UserRole::Admin->value;
    }

    /**
     * Supervisor, Mill Management dan Admin saja — tidak pernah Operator.
     *
     * DAFTARNYA SENDIRI, BUKAN SolidWasteDisposalReportService::guardAccess().
     * Service MENERIMA OPERATOR SEJAK COMMIT PERTAMA — screen-163 (laporan SWD
     * mobile) dibangun dalam seri yang sama dan merupakan pemanggil sahnya —
     * sehingga meminjamnya di sini akan MEMBUKA LAYAR WEB INI UNTUK OPERATOR
     * SECARA SENYAP, tanpa galat dan tanpa satu tanda pun di layar.
     * Pertahankan duplikasinya: itulah yang membuat kedua keputusan independen.
     */
    protected function canAccess(): bool
    {
        return in_array($this->role(), [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
        ], true);
    }

    protected function role(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '';
        }

        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Memilih periode terbaru secara otomatis ketika belum ada yang dipilih,
     * dan melaporkan apakah pilihan yang berlaku sah.
     *
     * Memulangkan FALSE ketika sebuah id periode diberikan yang tidak ada di
     * antara periode mill ini — yang, karena updatedBusinessUnitId() sudah
     * mengosongkan pilihan pada setiap penggantian mill, hanya terjadi ketika
     * seseorang menyerahkan id periode mill lain. Itu ditolak secara TERLIHAT
     * alih-alih ditukar diam-diam: penukaran senyap akan menjawab probe lintas
     * mill dengan angka mill lain di bawah id yang ditanyakan.
     *
     * @param  list<array{id: string}>  $periods
     */
    protected function keepSelectionValid(array $periods): bool
    {
        if ($periods === []) {
            $this->periodId = '';

            return true;
        }

        if ($this->periodId === '') {
            // Sesudah mill diganti: DIBIARKAN KOSONG. Pemilih menampilkan
            // 'Pilih Periode' dan layar menunggu pilihan — bukan menyuguhkan
            // periode yang tidak pernah dipilih siapa pun (temuan QA LP-W-09).
            if ($this->millJustSwitched) {
                return true;
            }

            $this->periodId = (string) $periods[0]['id'];

            return true;
        }

        return in_array($this->periodId, array_column($periods, 'id'), true);
    }
}
