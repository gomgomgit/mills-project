<?php

namespace App\Livewire\Data;

use App\Exceptions\InvalidDateRangeException;
use App\Livewire\Concerns\HasFilterReset;
use App\Services\SterilizerRecordService;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DataBrowserSterilizer — screen-124--data-browser-sterilizer-web
 * (Livewire web page, route name `data.sterilizer`,
 * /data/sterilizer). Mirrors DataBrowserCpoDispatch exactly.
 */
#[Layout('data.sterilizer')]
class DataBrowserSterilizer extends Component
{
    use HasFilterReset;
    use ScopesToActorMill;

    public string $date_from = '';

    public string $date_to = '';

    public string $business_unit_id = '';

    /**
     * Production line adalah KONTEKS YANG DIPILIH, bukan ikatan akun —
     * tidak ada `users.production_line_id` dan tidak boleh ada. Karena itu
     * default-nya '' = "Semua Line", bukan line tertentu milik aktor.
     *
     * Daftar ini memang berguna dilihat lintas-line: ia daftar BARIS, bukan
     * angka gabungan seperti laporan periode — asal setiap baris menunjukkan
     * line-nya sendiri, yang dijamin kolom Production Line di tabel.
     */
    public string $production_line_id = '';

    public int $page = 1;

    public int $perPage = 20;

    public ?string $errorMessage = null;

    public function updatedDateFrom(): void
    {
        $this->resetToFirstPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetToFirstPage();
    }

    public function updatedBusinessUnitId(): void
    {
        // Mill berganti (hanya mungkin untuk Admin — peran terikat dipaku
        // di render()) => pilihan line ikut direset: line mill lama tidak
        // ada di mill baru, dan membiarkannya akan menghasilkan daftar
        // kosong tanpa sebab yang terlihat.
        $this->production_line_id = '';
        $this->resetToFirstPage();
    }

    public function updatedProductionLineId(): void
    {
        $this->resetToFirstPage();
    }

    /**
     * Bawaan filter untuk "Reset filter" (x-filter.bar) — sama dengan
     * deklarasi properti di atas. Mill akun terikat dipaku ulang oleh
     * render(), jadi mengosongkannya di sini tidak melebarkan cakupan.
     *
     * @return array<string, string>
     */
    protected function filterDefaults(): array
    {
        return [
            'date_from' => '',
            'date_to' => '',
            'business_unit_id' => '',
            'production_line_id' => '',
        ];
    }

    protected function resetToFirstPage(): void
    {
        $this->page = 1;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max($page, 1);
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, business_unit_id: ?string, production_line_id: ?string}
     */
    protected function activeFilters(): array
    {
        return [
            'date_from' => $this->date_from !== '' ? $this->date_from : null,
            'date_to' => $this->date_to !== '' ? $this->date_to : null,
            'business_unit_id' => $this->business_unit_id !== '' ? $this->business_unit_id : null,
            'production_line_id' => $this->production_line_id !== '' ? $this->production_line_id : null,
        ];
    }

    protected function exportUrl(string $format): string
    {
        $query = array_filter($this->activeFilters(), fn ($value) => $value !== null);
        $query['format'] = $format;

        return url('/api/sterilizer-records/export').'?'.http_build_query($query);
    }

    public function render()
    {
        // Peran terikat mill (Operator / Supervisor / Mill Management)
        // dipaku ke mill-nya sendiri SEBELUM filter dibaca: nilai apa pun
        // yang disuntikkan lewat properti Livewire — atau lewat query
        // string yang sudah di-bookmark — ditimpa di sini, sehingga
        // <select> mill dan tautan ekspor menampilkan kenyataan, bukan
        // pilihan yang sudah dibuang diam-diam. Admin tetap memakai apa
        // yang ia pilih (kosong = semua mill).
        //
        // Ini LAPISAN TAMPILAN, bukan penjaganya. Penegakan sesungguhnya
        // ada di service: buildFilteredQuery() memanggil
        // scopeFiltersToActorMill() dan membuang nilai kiriman klien, jadi
        // melewatkan baris ini pun tidak membocorkan apa pun.
        $actor = auth()->user();
        $this->business_unit_id = $this->forcedMillFilterValue($actor, $this->business_unit_id);
        // Line yang tidak berada di dalam mill yang sedang berlaku dibuang
        // DIAM-DIAM ke '' (= semua line di dalam mill aktor) — tidak ada
        // error, dan datanya tidak pernah ditampilkan. Perlakuan yang sama
        // persis dengan `business_unit_id` di baris sebelumnya, dan sekali
        // lagi ini LAPISAN TAMPILAN: penegakannya ada di
        // scopeFiltersToActorMill() di dalam service.
        $this->production_line_id = $this->forcedProductionLineFilterValue(
            $actor,
            $this->business_unit_id,
            $this->production_line_id,
        );

        $service = app(SterilizerRecordService::class);

        try {
            $result = $service->listRecords($this->activeFilters(), $this->page, $this->perPage);
            $this->errorMessage = null;
        } catch (InvalidDateRangeException $e) {
            $this->errorMessage = $e->getMessage();
            $result = [
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => $this->perPage, 'total' => 0, 'total_pages' => 1],
            ];
        } catch (ValidationException $e) {
            // Aktor terikat mill yang `users.business_unit_id`-nya kosong.
            // ScopesToActorMill::actorReadMillId() gagal-tertutup dengan 422
            // ketimbang melebar ke semua mill; di layar ini itu muncul
            // sebagai pesan yang bisa ditindaklanjuti + daftar kosong,
            // bukan halaman error dan bukan daftar kosong tanpa sebab.
            $this->errorMessage = $e->validator->errors()->first() ?: $e->getMessage();
            $result = [
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => $this->perPage, 'total' => 0, 'total_pages' => 1],
            ];
        }

        return view('livewire.data.data-browser-sterilizer', [
            'records' => $result['data'],
            'meta' => $result['meta'],
            'businessUnits' => $this->businessUnitsForActor($actor),
            'productionLines' => $this->productionLineOptionsForReadActor($actor, $this->business_unit_id),
            'exportCsvUrl' => $this->exportUrl('csv'),
            'exportExcelUrl' => $this->exportUrl('excel'),
        ]);
    }
}
