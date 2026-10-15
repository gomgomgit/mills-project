<?php

namespace App\Livewire\MasterData;

use App\Enums\UserRole;
use App\Exceptions\ShiftOverlapException;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * KelolaShift — screen-170--kelola-shift / usecase-176--kelola-shift.
 * Layar web Livewire di /master-data/shifts, nama rute
 * 'master-data.shifts', ADMIN SAJA.
 *
 * Memakai ShiftService — service yang SAMA dengan
 * App\Http\Controllers\Api\ShiftController — sehingga halaman dan API tidak
 * mungkin berbeda hasil hitungan cakupannya. CAKUPAN TIDAK DIHITUNG DI
 * BLADE: server menerbitkan coverage[].blocks berisi posisi dan durasi tiap
 * blok dalam menit dari 07:00, dan blade hanya memetakan menit ke persen.
 *
 * GATE PERAN DUA LAPIS DI JALUR WEB. routes/web.php menjaga rutenya dengan
 * 'auth' + 'role:admin' (EnsureRole::forbidden() → halaman 403 sebelum
 * komponen ini mount), dan mount() di bawah memanggil abort_unless() SENDIRI
 * sebagai lapis kedua — mengikuti pola layar laporan (LaporanWeighbridge dan
 * saudaranya) alih-alih mengandalkan middleware saja. ShiftService
 * menegakkannya sekali lagi sebagai lapis ketiga, sehingga peran non-admin
 * tidak dapat menjalankan satu kueri shift pun.
 *
 * MILL WAJIB DIPILIH LEBIH DAHULU. Admin tidak terikat satu mill, jadi
 * selama $businessUnitId kosong TIDAK ADA kueri shift yang berjalan sama
 * sekali (render() tidak memanggil listShifts()) dan layar hanya menampilkan
 * arahan memilih mill. Mengganti mill MENGOSONGKAN filter Production Line:
 * sebuah line milik tepat satu mill, jadi membawanya ke mill lain akan
 * menampilkan galat untuk sesuatu yang tidak dilakukan Admin.
 *
 * TABEL BERPINDAH HALAMAN, TIMELINE TIDAK. `data` berpaginasi; `coverage`
 * memuat seluruh line dalam cakupan filter tanpa dipotong halaman, karena ia
 * merangkum cakupan dan bukan mendaftar baris.
 *
 * PRATINJAU DI DALAM FORM DIHITUNG DI KLIEN (Alpine, lihat blade-nya) dari
 * nilai yang sedang diketik — satu-satunya perhitungan cakupan di sisi
 * klien, karena ia harus bergerak sebelum ada permintaan jaringan. Ia BUKAN
 * sumber kebenaran: penolakan tetap datang dari server saat Simpan, dan
 * pratinjau memakai rumus offset-dari-07:00 yang sama persis (blok shift
 * yang sudah ada diambil dari ShiftService::shiftBlocksByLine(), bukan
 * dihitung ulang di JavaScript).
 *
 * VALIDASI HANYA PUNYA SATU SUMBER. Tidak ada cermin aturan di komponen ini:
 * save() memanggil service dan memetakan ValidationException-nya ke kunci
 * binding form ini. Satu set aturan, tidak ada tempat kedua yang harus
 * dijaga sinkron — pelajaran dari screen-128, yang cerminnya sempat melenceng.
 */
#[Layout('master-data.shifts')]
class KelolaShift extends Component
{
    public int $page = 1;

    public int $perPage = 20;

    /** Pemilih Mill — URL-bound supaya tampilan tersaring dapat ditautkan. */
    #[Url]
    public string $businessUnitId = '';

    /** Filter Production Line; '' = seluruh line mill itu (tindakan audit). */
    #[Url]
    public string $productionLineId = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    public ?string $formErrorMessage = null;

    public ?string $successMessage = null;

    public ?string $confirmingDeleteId = null;

    /** Nama shift + nama line-nya, untuk konfirmasi hapus inline. */
    public ?string $confirmingDeleteLabel = null;

    public ?string $deleteErrorMessage = null;

    /**
     * LAPIS KEDUA gate peran, di atas middleware 'role:admin' rutenya.
     * Master data adalah wewenang Admin: supervisor, mill_management, dan
     * operator semuanya can_access=false pada layar ini.
     */
    public function mount(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->form = $this->emptyForm();
    }

    protected function isAdmin(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return $role === UserRole::Admin->value;
    }

    /**
     * Mengganti mill mengosongkan filter Production Line — sebuah line milik
     * tepat satu mill. Form yang sedang terbuka juga ditutup: pilihan line di
     * dalamnya milik mill yang lama.
     */
    public function updatedBusinessUnitId(): void
    {
        $this->productionLineId = '';
        $this->page = 1;
        $this->successMessage = null;
        $this->deleteErrorMessage = null;
        $this->confirmingDeleteId = null;
        $this->closeForm();
    }

    public function updatedProductionLineId(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyForm(): array
    {
        return [
            'production_line_id' => '',
            'name' => '',
            'start_time' => '',
            'end_time' => '',
        ];
    }

    public function openCreateForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->emptyForm();

        // Line yang sedang difilter dipakai sebagai nilai awal — Admin yang
        // baru saja melihat satu line hampir selalu menambah shift di line itu.
        $this->form['production_line_id'] = $this->productionLineId;

        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->showForm = true;
    }

    public function openEditForm(string $id): void
    {
        try {
            $shift = Shift::findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->deleteErrorMessage = 'Shift tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        $this->resetValidation();
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->editingId = $shift->id;

        $this->form = [
            'production_line_id' => (string) $shift->production_line_id,
            'name' => (string) $shift->name,
            // Field waktu diisi 'HH:MM' — bentuk yang diterima <input
            // type="time">, yang MENERIMA PENGETIKAN MANUAL MAUPUN PICKER.
            // Tidak ada field yang dinonaktifkan maupun readonly di form ini.
            'start_time' => ShiftService::displayTime((string) $shift->start_time),
            'end_time' => ShiftService::displayTime((string) $shift->end_time),
        ];

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->formErrorMessage = null;
        $this->resetValidation();
    }

    /**
     * "Simpan" — create atau update, mengikuti $editingId.
     *
     * Setiap jalur gagal MENINGGALKAN FORM TERBUKA dengan isian Admin utuh:
     * simpan yang ditolak tidak boleh membuat mereka kehilangan form yang
     * baru saja diisi. Pesan SHIFT_OVERLAP ditampilkan sebagai pesan
     * tingkat-form (bukan per-field) karena ia konflik antara dua field jam
     * dan sebuah baris yang sudah ada — dan pesannya menyebut shift mana
     * serta rentang jam tindihannya.
     */
    public function save(): void
    {
        $this->formErrorMessage = null;
        $this->successMessage = null;
        $this->resetValidation();

        $service = app(ShiftService::class);

        $payload = [
            'production_line_id' => $this->form['production_line_id'] ?? '',
            'name' => $this->form['name'] ?? '',
            'start_time' => $this->form['start_time'] ?? '',
            'end_time' => $this->form['end_time'] ?? '',
        ];

        try {
            if ($this->editingId !== null) {
                $service->update($this->editingId, $payload);
            } else {
                $service->create($payload);
            }
        } catch (ModelNotFoundException) {
            $this->formErrorMessage = 'Shift tidak ditemukan, mungkin sudah dihapus oleh pengguna lain.';

            return;
        } catch (ShiftOverlapException $e) {
            $this->formErrorMessage = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("form.$field", $messages[0] ?? 'Validasi gagal.');
            }

            return;
        }

        $this->successMessage = $this->editingId !== null
            ? 'Shift berhasil diperbarui.'
            : 'Shift berhasil dibuat.';

        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->emptyForm();
        $this->resetValidation();
    }

    /**
     * Konfirmasi hapus inline MENYEBUT NAMA SHIFT DAN LINE-NYA — "Hapus
     * 'Pagi'?" tanpa line-nya ambigu pada mill yang punya belasan line
     * dengan nama shift yang sama.
     */
    public function askDelete(string $id): void
    {
        $shift = Shift::with('productionLine')->find($id);

        if ($shift === null) {
            $this->deleteErrorMessage = 'Shift tidak ditemukan, mungkin sudah dihapus.';

            return;
        }

        $this->confirmingDeleteId = $shift->id;
        $this->confirmingDeleteLabel = sprintf(
            '%s pada %s',
            $shift->name,
            $shift->productionLine->name ?? 'Production Line'
        );
        $this->deleteErrorMessage = null;
        $this->successMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteLabel = null;
    }

    /**
     * Menghapus shift SELALU membesarkan celah, dan akibat itu ditampilkan
     * di timeline setelah penghapusan — bukan alasan untuk menolak. Line
     * yang kehilangan shift TERAKHIR-nya kembali ke keadaan 'belum diatur',
     * bukan 'celah 24 jam'.
     */
    public function confirmDelete(): void
    {
        if ($this->confirmingDeleteId === null) {
            return;
        }

        $label = $this->confirmingDeleteLabel;

        try {
            app(ShiftService::class)->delete($this->confirmingDeleteId);
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteLabel = null;
            $this->deleteErrorMessage = null;
            $this->successMessage = sprintf('Shift %s berhasil dihapus.', $label ?? '');
        } catch (ModelNotFoundException) {
            $this->confirmingDeleteId = null;
            $this->confirmingDeleteLabel = null;
            $this->deleteErrorMessage = 'Shift tidak ditemukan, mungkin sudah dihapus.';
        }
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

    public function render()
    {
        $service = app(ShiftService::class);

        $businessUnitOptions = $service->businessUnitOptions();

        $lineOptions = [];
        $shifts = [];
        $coverage = [];
        $meta = ['page' => 1, 'per_page' => $this->perPage, 'total' => 0, 'total_pages' => 1];
        $previewBlocks = [];

        // MILL BELUM DIPILIH → NOL KUERI SHIFT. Bukan daftar kosong yang
        // dihitung dulu lalu disembunyikan: listShifts() tidak dipanggil.
        if ($this->businessUnitId !== '') {
            $lineOptions = $service->productionLineOptions($this->businessUnitId);

            try {
                $result = $service->listShifts(
                    $this->businessUnitId,
                    $this->productionLineId !== '' ? $this->productionLineId : null,
                    $this->page,
                    $this->perPage
                );
            } catch (ValidationException) {
                // Filter line basi (mis. URL yang dibawa dari mill lain).
                // Dikembalikan ke "semua line" TANPA pesan galat: Admin
                // tidak melakukan apa pun yang salah — mereka mengganti mill.
                $this->productionLineId = '';

                $result = $service->listShifts(
                    $this->businessUnitId,
                    null,
                    $this->page,
                    $this->perPage
                );
            }

            $shifts = $result['data'];
            $coverage = $result['coverage'];
            $meta = $result['meta'];

            // Blok shift SELURUH line mill ini, tanpa paginasi — sumber data
            // pratinjau klien di dalam form.
            $previewBlocks = $service->shiftBlocksByLine($this->businessUnitId);
        }

        $selectedMill = collect($businessUnitOptions)->firstWhere('id', $this->businessUnitId);

        return view('livewire.master-data.kelola-shift', [
            'businessUnitOptions' => $businessUnitOptions,
            'lineOptions' => $lineOptions,
            'shifts' => $shifts,
            'coverage' => $coverage,
            'meta' => $meta,
            'previewBlocks' => $previewBlocks,
            'millName' => $selectedMill['name'] ?? null,
        ]);
    }
}
