<?php

namespace App\Livewire\Data;

use App\Services\GradingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(GradingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-grading');
    }
}
