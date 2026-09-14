<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\ProcessWaterRecord;
use App\Services\ProcessWaterRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailProcessWater — screen-102--detail-process-water-web / "Detail
 * Process Water (Web)" (Livewire web page, route name
 * `data.process-water.detail`, /data/process-water/{id}).
 *
 * Reuses ProcessWaterRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\ProcessWaterRecordController::show()) calls —
 * mirroring the DetailThreshing pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewProcessWaterView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Process Water ID, Tanggal, Inputted By, Checked
 * By, Acknowledged By, Note, grid Process Water Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.process-water-detail')]
class DetailProcessWater extends Component
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
        return ProcessWaterRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(ProcessWaterRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-process-water');
    }
}
