<?php

namespace App\Livewire\Data;

use App\Models\DepricarpingOperationalTarget;
use App\Services\DepricarpingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailDepricarping — screen-055--detail-depricarping-web / "Detail
 * Depricarping (Web)" (Livewire web page, route name
 * `data.depricarping.detail`, /data/depricarping/{id}).
 *
 * Reuses DepricarpingRecordService::getDetail() — the exact same service
 * method the API controller (App\Http\Controllers\Api\
 * DepricarpingRecordController::show()) calls — mirroring the
 * DetailPressing pattern used by screen-054 so the web and API entry
 * points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewDepricarpingView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Presser ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Depricarping Detail (24 rows), Target
 * Operasional reference table.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * through DepricarpingRecordService/the record API — per this screen's
 * tech spec: "sourced from
 * DepricarpingOperationalTarget::orderBy('sort_order')->get()", since it
 * is pure reference data, never part of the record payload.
 */
#[Layout('data.depricarping-detail')]
class DetailDepricarping extends Component
{
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(DepricarpingRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-depricarping', [
            'operationalTargets' => DepricarpingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
