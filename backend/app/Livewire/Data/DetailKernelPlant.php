<?php

namespace App\Livewire\Data;

use App\Models\KernelPlantOperationalTarget;
use App\Services\KernelPlantRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailKernelPlant — screen-056--detail-kernel-plant-web / "Detail
 * Kernel Plant (Web)" (Livewire web page, route name
 * `data.kernel-plant.detail`, /data/kernel-plant/{id}).
 *
 * Reuses KernelPlantRecordService::getDetail() — the exact same service
 * method the API controller (App\Http\Controllers\Api\
 * KernelPlantRecordController::show()) calls — mirroring the
 * DetailDepricarping pattern used by screen-055 so the web and API entry
 * points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewKernelPlantView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Kernel Plant ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Kernel Plant Detail (24 rows), Target
 * Operasional reference table.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * through KernelPlantRecordService/the record API — per this screen's
 * tech spec: "sourced from
 * KernelPlantOperationalTarget::orderBy('sort_order')->get()", since it
 * is pure reference data, never part of the record payload.
 */
#[Layout('data.kernel-plant-detail')]
class DetailKernelPlant extends Component
{
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(KernelPlantRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-kernel-plant', [
            'operationalTargets' => KernelPlantOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
