<?php

namespace App\Livewire\Data;

use App\Models\ThreshingOperationalTarget;
use App\Services\ThreshingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailThreshing — screen-053--detail-threshing-web / "Detail Threshing
 * (Web)" (Livewire web page, route name `data.threshing.detail`,
 * /data/threshing/{id}).
 *
 * Reuses ThreshingRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\ThreshingRecordController::show()) calls —
 * mirroring the DetailCagesTrack pattern used by screen-021 so the web and
 * API entry points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewThreshingView.vue's detail mode exactly (per
 * explicit product direction: web station screens should mirror their
 * mobile counterparts) — Thresher ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Threshing Detail (however many rows exist),
 * Target Operasional reference table.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * through ThreshingRecordService/the record API — per this screen's tech
 * spec: "sourced from ThreshingOperationalTarget::orderBy('sort_order')->get()",
 * since it is pure reference data, never part of the record payload.
 */
#[Layout('data.threshing-detail')]
class DetailThreshing extends Component
{
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(ThreshingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-threshing', [
            'operationalTargets' => ThreshingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
