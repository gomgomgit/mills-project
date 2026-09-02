<?php

namespace App\Livewire\Data;

use App\Services\EffluentPlantRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailEffluentPlant — screen-105--detail-effluent-plant-web / "Detail
 * Effluent Plant (Web)" (Livewire web page, route name
 * `data.effluent-plant.detail`, /data/effluent-plant/{id}).
 *
 * Reuses EffluentPlantRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\EffluentPlantRecordController::show()) calls —
 * mirroring the DetailThreshing pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewEffluentPlantView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Effluent Plant ID, Tanggal, Inputted By, Checked
 * By, Acknowledged By, Note, grid Effluent Plant Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.effluent-plant-detail')]
class DetailEffluentPlant extends Component
{
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(EffluentPlantRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-effluent-plant');
    }
}
