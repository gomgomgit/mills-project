<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\WeighbridgeRecord;
use App\Services\WeighbridgeRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailWeighbridge — screen-019--detail-weighbridge-web / "Detail
 * Weighbridge (Web)" (Livewire web page, route name
 * `data.weighbridge.detail`, /data/weighbridge/{id}).
 *
 * Reuses WeighbridgeRecordService::getDetail() — the exact same service
 * method the API controller (App\Http\Controllers\Api\
 * WeighbridgeRecordController::show()) calls — mirroring the
 * DataBrowserWeighbridge / WeighbridgeRecordService::listRecords() pattern
 * so the web and API entry points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewWeighbridgeView.vue's detail section exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts, not be designed independently) — Tipe, WB Card
 * Number, Tanggal & Waktu (label switches receive/dispatch), Kendaraan,
 * Supir, Estate/Supplier, Tujuan Muatan (dispatch-only), Divisi, Blok,
 * Gross/Tare/Net Weight, Kuantitas, Checked By, Acknowledged By.
 */
#[Layout('data.weighbridge-detail')]
class DetailWeighbridge extends Component
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
        return WeighbridgeRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(WeighbridgeRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-weighbridge');
    }
}
