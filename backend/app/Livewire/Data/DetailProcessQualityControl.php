<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\ProcessQualityControlRecord;
use App\Services\ProcessQualityControlRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailProcessQualityControl — screen-110--detail-process-quality-control-web / "Detail
 * Process Quality Control (Web)" (Livewire web page, route name
 * `data.process-quality-control.detail`, /data/process-quality-control/{id}).
 *
 * Reuses ProcessQualityControlRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\ProcessQualityControlRecordController::show()) calls —
 * mirroring the DetailClarification pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewProcessQualityControlView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Process QC ID, Tanggal, Inputted By, Checked
 * By, Acknowledged By, Note, grid Process Quality Control Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.process-quality-control-detail')]
class DetailProcessQualityControl extends Component
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
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    protected function verificationModelClass(): string
    {
        return ProcessQualityControlRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(ProcessQualityControlRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-process-quality-control');
    }
}
