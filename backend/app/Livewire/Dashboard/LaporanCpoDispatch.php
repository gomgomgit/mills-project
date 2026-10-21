<?php

namespace App\Livewire\Dashboard;

use App\Enums\UserRole;
use App\Services\CpoDispatchReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LaporanCpoDispatch — screen-166--laporan-cpo-dispatch-web ('Laporan Periode
 * CPO Dispatch'), route name `reports.cpo-dispatch`, /reports/cpo-dispatch.
 *
 * NAMA KELAS DAN NAMA VIEW-NYA MENGIKUTI LAPORAN YANG SUDAH ADA (LaporanWeighbridge,
 * LaporanKernelDispatch, …) dan BUKAN App\Livewire\Reports\CpoDispatchReport.
 *
 * Memakai ulang CpoDispatchReportService — service yang sama persis dengan
 * controller API — sehingga halaman dan API tidak pernah melaporkan angka yang
 * berbeda. BACA SAJA: satu-satunya aksi adalah memilih mill, production line,
 * periode, dan mengekspor. Tidak ada yang menulis ke `cpo_dispatch_records`
 * maupun `cpo_dispatch_details`.
 *
 * APA YANG LAYAR INI TIDAK BOLEH BIARKAN SALAH DIBACA:
 *   - SATU BARIS DETAIL = SATU PENGIRIMAN (satu tanker). Layar ini satu-satunya
 *     laporan pengiriman yang menjawab pertanyaan WAKTU — sebaran per jam MASUK
 *     dan waktu putar tanker — karena hanya tabel ini yang punya time_in/time_out.
 *   - JAM TANPA TANGGAL: jam keluar yang lebih kecil dari jam masuk dibaca hari
 *     berikutnya, dan jumlahnya (`crossed_midnight_count`) selalu dirender, juga
 *     ketika nol. Penafsiran itu tidak menggeser kelompok jam.
 *   - EMPAT PENYEBUT YANG BERBEDA dicetak di kartunya masing-masing dan dinamai
 *     utuh dari payload (`_note_four_denominators`); keempat ukuran mutu
 *     (FFA, kadar air, kadar kotoran, DOBI) punya penyebut dan penjaga '—'
 *     sendiri. DOBI indeks tanpa satuan: tidak pernah diberi '%'.
 *   - TIDAK ADA REKAP BERLABEL (stasiun ini tanpa kolom berpilihan tertutup),
 *     dan ketiadaannya dinyatakan utuh dari payload.
 *   - Kolom `shift` tidak ditampilkan di layar; ia hanya ada di ekspor.
 *
 * Kerangka pemilih (mill / production line / periode), gagal-tertutup akun
 * tanpa mill, dan penjaga lintas mill sama dengan LaporanKernelDispatch:
 *
 * Mill: Supervisor / Mill Management terikat pada mill akunnya (keterangan,
 * bukan pemilih); Admin (business_unit_id NULL) melihat pemilihnya dan wajib
 * memakainya; akun terikat dengan business_unit_id NULL mendapat arahan 'hubungi
 * Admin' tanpa pemilih, dan daftar seluruh mill tidak pernah dibaca.
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
 * CpoDispatchReportService::guardAccess(): SERVICE-NYA MENERIMA OPERATOR
 * SEJAK COMMIT PERTAMA (pasangan mobile screen-167 dibangun dalam seri yang
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
#[Layout('dashboard.laporan-cpo-dispatch')]
class LaporanCpoDispatch extends Component
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
     * Mengalirkan pengiriman periode sebagai CSV/.xlsx lewat service bersama,
     * sehingga berkas yang diunduh dari halaman ini byte-per-byte sama dengan
     * yang dikembalikan endpoint API — satu baris per pengiriman, konteks
     * diulang, DUA PULUH SATU kolom detail (`shift`, jam masuk dan jam keluar APA
     * ADANYA tanpa penafsiran tengah malam, nomor surat jalan, nomor tanker,
     * nama pengemudi, kedua nomor segel dan operator timbang TERMASUK, karena
     * ekspor adalah rincian mentah), dan pengiriman ber-netto kosong tetap
     * menjadi baris dengan sel kosong.
     *
     * 'excel' DITERJEMAHKAN MENJADI 'xlsx' DI SINI, dan ini bukan kemubaziran:
     * toolbar bersama (components/report-filter-bar.blade.php) memanggil aksi
     * ekspor dengan argumen harfiah 'csv' dan 'excel' untuk ketujuh belas
     * laporan yang sudah ada, sementara kontrak API layar ini — dan uji
     * unitnya — memakai daftar tertutup {csv, xlsx}. Menerjemahkannya di sini
     * membuat toolbar tidak perlu disentuh (menyentuhnya berarti menyentuh
     * ketujuh belas laporan lain) DAN daftar format service tetap tertutup
     * persis seperti spec-nya. Nilai tak dikenal apa pun diteruskan apa adanya
     * supaya service yang menolaknya dengan 422, bukan ditelan di sini.
     *
     * Memulangkan null (dan tidak melakukan apa pun) ketika belum ada periode
     * ATAU belum ada production line: belum ada yang dapat diekspor, dan itu
     * bukan galat. LINE dijaga di sini dan tidak hanya di blade, sehingga
     * panggilan langsung ke metode ini tidak dapat mengambil berkas yang
     * mencampur seluruh line mill.
     *
     * Penjaga peran, resolusi mill, resolusi line, pemeriksaan format dan batas
     * 50.000 BARIS PENGIRIMAN diperiksa EAGER di dalam service, sebelum satu
     * byte pun dialirkan — dan batas itu menghitung BARIS DETAIL, bukan record,
     * karena satu record induk dapat membawa puluhan pengiriman.
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

        $service = app(CpoDispatchReportService::class);

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
        $service = app(CpoDispatchReportService::class);

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

        return view('livewire.dashboard.laporan-cpo-dispatch', [
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
            // KEEMPAT UKURAN MUTU, diserahkan sebagai daftar berurut beserta
            // labelnya. DITURUNKAN DARI KONSTANTA SERVICE, bukan ditulis ulang
            // di blade: empat kartu yang ditulis tangan adalah empat kesempatan
            // kehilangan sebuah penyebut atau mewarisi penyebut kartu lain, dan
            // kolom mutu baru dari migrasi kelak harus mendarat di layar tanpa
            // menyunting blade.
            'qualityMeasures' => $this->qualityMeasureMeta(),
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
     * Label manusiawi keempat ukuran mutu, DALAM URUTAN KONSTANTA SERVICE.
     *
     * Daftar kolomnya datang dari CpoDispatchReportService::QUALITY_MEASURES
     * dan TIDAK ditulis ulang di sini — yang ditambahkan hanya labelnya. Kolom
     * yang belum punya label (misalnya kolom baru dari sebuah migrasi) tetap
     * dirender memakai nama kolomnya, bukan hilang dari layar.
     *
     * @return list<array{key: string, label: string, pendek: string, satuan: string|null}>
     */
    protected function qualityMeasureMeta(): array
    {
        // `satuan` null untuk DOBI: ia indeks TANPA SATUAN, bukan persen. Tiga
        // lainnya persen. Blade mencetak satuan hanya bila terisi, sehingga DOBI
        // tidak pernah mendapat '%' secara refleks.
        $labels = [
            'ffa_percent' => ['Kadar Asam Lemak Bebas (FFA)', 'kadar FFA', '%'],
            'moisture_percent' => ['Kadar Air (Moisture)', 'kadar air', '%'],
            'impurities_percent' => ['Kadar Kotoran (Impurities)', 'kadar kotoran', '%'],
            'dobi' => ['DOBI', 'DOBI', null],
        ];

        return array_map(function (string $key) use ($labels) {
            [$label, $pendek, $satuan] = $labels[$key] ?? [$key, $key, null];

            return [
                'key' => $key,
                'label' => $label,
                'pendek' => $pendek,
                'satuan' => $satuan,
            ];
        }, CpoDispatchReportService::QUALITY_MEASURES);
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

        return app(CpoDispatchReportService::class)
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
     * DAFTARNYA SENDIRI, BUKAN CpoDispatchReportService::guardAccess().
     * Service MENERIMA OPERATOR SEJAK COMMIT PERTAMA — screen-167 (laporan
     * CPO Dispatch mobile) dibangun dalam seri yang sama dan merupakan
     * pemanggil sahnya — sehingga meminjamnya di sini akan MEMBUKA LAYAR WEB
     * INI UNTUK OPERATOR SECARA SENYAP, tanpa galat dan tanpa satu tanda pun di
     * layar. Pertahankan duplikasinya: itulah yang membuat kedua keputusan
     * independen.
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
