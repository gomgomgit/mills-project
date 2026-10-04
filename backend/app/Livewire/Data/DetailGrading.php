<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\GradingRecord;
use App\Services\GradingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailGrading — screen-020--detail-grading-web / "Detail Grading (Web)"
 * (Livewire web page, route name `data.grading.detail`, /data/grading/{id}).
 *
 * Reuses GradingRecordService::getDetail() — the exact same service method
 * the API controller (App\Http\Controllers\Api\GradingRecordController::show())
 * calls — mirroring the DetailWeighbridge / WeighbridgeRecordService::getDetail()
 * pattern used by screen-019 so the web and API entry points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewGradingView.vue's detail mode exactly (per
 * explicit product direction: web station screens should mirror their
 * mobile counterparts, not be designed independently) — Grading Number,
 * Tanggal, WB Card Number, License Plate No, Vehicle Code, Estate, Divisi,
 * Netto (kg), Quantity (bunch), Note, grid Grading Detail (Quality
 * Parameter/Qty/UOM/Percentage), Acknowledged By. Checked By is
 * intentionally NOT rendered, consistent with the mobile screen.
 */
#[Layout('data.grading-detail')]
class DetailGrading extends Component
{
    use GuardsRecordIdShape;
    use HandlesRecordVerification;

    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        if (! $this->isRecordIdShapeValid($id)) {
            // id bukan UUID tidak boleh sampai ke SQL (PostgreSQL: 22P02),
            // perlakukan sama dengan UUID yang tidak dikenal.
            $this->notFound = true;

            return;
        }

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
        return GradingRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(GradingRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-grading');
    }
}
