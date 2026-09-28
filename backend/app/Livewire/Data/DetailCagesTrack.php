<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\CagesTrackRecord;
use App\Services\CagesTrackRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailCagesTrack — screen-021--detail-cages-track-web / "Detail Cages
 * Track (Web)" (Livewire web page, route name `data.cages-track.detail`,
 * /data/cages-track/{id}).
 *
 * Reuses CagesTrackRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\CagesTrackRecordController::show()) calls —
 * mirroring the DetailWeighbridge/DetailGrading pattern used by
 * screen-019/screen-020 so the web and API entry points stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewCagesTrackView.vue's detail mode exactly (per
 * explicit product direction: web station screens should mirror their
 * mobile counterparts, not be designed independently) — Cages Track
 * Number, Tanggal, Tippler Start/Stop Time, Cages Out, Cages Tipped,
 * Inputted By, Checked By, Acknowledged By, Note, grid Cages Tipped Time.
 * UNLIKE screen-020 (Grading), Checked By IS rendered here, consistent
 * with the mobile screen showing both Checked By and Acknowledged By.
 */
#[Layout('data.cages-track-detail')]
class DetailCagesTrack extends Component
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
        return CagesTrackRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(CagesTrackRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-cages-track');
    }
}
