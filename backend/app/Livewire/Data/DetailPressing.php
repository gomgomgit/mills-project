<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\PressingOperationalTarget;
use App\Models\PressingRecord;
use App\Services\PressingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailPressing — screen-054--detail-pressing-web / "Detail Pressing
 * (Web)" (Livewire web page, route name `data.pressing.detail`,
 * /data/pressing/{id}).
 *
 * Reuses PressingRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\PressingRecordController::show()) calls —
 * mirroring the DetailThreshing pattern used by screen-053 so the web and
 * API entry points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewPressingView.vue's detail mode exactly (per
 * explicit product direction: web station screens should mirror their
 * mobile counterparts) — Presser ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Pressing Detail (24 rows), Target
 * Operasional reference table.
 *
 * Target Operasional (operationalTargets) is queried DIRECTLY here — NOT
 * through PressingRecordService/the record API — per this screen's tech
 * spec: "sourced from PressingOperationalTarget::orderBy('sort_order')->get()",
 * since it is pure reference data, never part of the record payload.
 */
#[Layout('data.pressing-detail')]
class DetailPressing extends Component
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
        return PressingRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(PressingRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-pressing', [
            'operationalTargets' => PressingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
