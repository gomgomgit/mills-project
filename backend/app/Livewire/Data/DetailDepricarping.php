<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\DepricarpingOperationalTarget;
use App\Models\DepricarpingRecord;
use App\Services\DepricarpingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
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
        return DepricarpingRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(DepricarpingRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-depricarping', [
            'operationalTargets' => DepricarpingOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
