<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\GuardsRecordIdShape;
use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\KernelPlantOperationalTarget;
use App\Models\KernelPlantRecord;
use App\Services\KernelPlantRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
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
        return KernelPlantRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(KernelPlantRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-kernel-plant', [
            'operationalTargets' => KernelPlantOperationalTarget::orderBy('sort_order')->get(),
        ]);
    }
}
