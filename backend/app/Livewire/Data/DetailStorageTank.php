<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\StorageTankRecord;
use App\Services\StorageTankRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailStorageTank — screen-106--detail-storage-tank-web / "Detail Storage
 * Tank (Web)" (Livewire web page, route name `data.storage-tank.detail`,
 * /data/storage-tank/{id}).
 *
 * Reuses StorageTankRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\StorageTankRecordController::show()) calls —
 * mirroring the DetailEffluentPlant pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewStorageTankView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Storage Tank ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Storage Tank Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.storage-tank-detail')]
class DetailStorageTank extends Component
{
    use HandlesRecordVerification;

    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->reloadRecord();
        } catch (ModelNotFoundException|ValidationException) {
            // ValidationException ikut ditangkap sejak 2026-09-28: aktor
            // terikat mill yang `users.business_unit_id`-nya kosong membuat
            // getDetail() gagal-tertutup 422 lewat
            // ScopesToActorMill::actorReadMillId(). Bagi aktor seperti itu
            // TIDAK ADA record yang terlihat sama sekali, jadi $notFound
            // memang keadaan yang benar — dan itu lebih baik daripada
            // halaman error 422 penuh. Pesan yang bisa ditindaklanjuti
            // ("Hubungi Admin") tetap sampai lewat Data Browser dan lewat
            // save di layar Form.
            $this->notFound = true;
        }
    }

    protected function verificationModelClass(): string
    {
        return StorageTankRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(StorageTankRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-storage-tank');
    }
}
