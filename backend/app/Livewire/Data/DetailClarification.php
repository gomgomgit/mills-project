<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\ClarificationRecord;
use App\Services\ClarificationRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailClarification — screen-109--detail-clarification-web / "Detail
 * Clarification (Web)" (Livewire web page, route name
 * `data.clarification.detail`, /data/clarification/{id}).
 *
 * Reuses ClarificationRecordService::getDetail() — the exact same service
 * method the API controller
 * (App\Http\Controllers\Api\ClarificationRecordController::show()) calls —
 * mirroring the DetailBoilerRoom pattern so the web and API entry points
 * stay identical.
 *
 * Field set/order in the Blade view mirrors
 * mobile/src/views/DataPreviewClarificationView.vue's detail mode exactly
 * (per explicit product direction: web station screens should mirror their
 * mobile counterparts) — Clarification ID, Tanggal, Inputted By, Checked
 * By, Acknowledged By, Note, grid Clarification Detail (however many rows
 * exist).
 *
 * UNLIKE DetailThreshing: this station has NO operational-target reference
 * table — no `operationalTargets` prop, no Target Operasional section.
 */
#[Layout('data.clarification-detail')]
class DetailClarification extends Component
{
    use GuardsRecordIdShape;
    use HandlesRecordVerification;

    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        if (! $this->isRecordIdShapeValid($id)) {
            // id bukan UUID tidak boleh sampai ke SQL (PostgreSQL: 22P02),
            // perlakukan sama dengan UUID yang tidak dikenal.
            $this->notFound = true;

            return;
        }

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
        return ClarificationRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(ClarificationRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-clarification');
    }
}
