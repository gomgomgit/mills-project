<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\EngineRoomRecord;
use App\Services\EngineRoomRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailEngineRoom — screen-107--detail-engine-room-web / "Detail Engine
 * Room (Web)" (Livewire web page, route name `data.engine-room.detail`,
 * /data/engine-room/{id}).
 *
 * Reuses EngineRoomRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\EngineRoomRecordController::show()) calls —
 * mirroring the DetailStorageTank pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewEngineRoomView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Engine Room ID, Tanggal, Inputted By, Checked By,
 * Acknowledged By, Note, grid Engine Room Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.engine-room-detail')]
class DetailEngineRoom extends Component
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
        return EngineRoomRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(EngineRoomRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-engine-room');
    }
}
