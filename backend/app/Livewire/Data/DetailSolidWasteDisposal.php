<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\SolidWasteDisposalRecord;
use App\Services\SolidWasteDisposalRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailSolidWasteDisposal — screen-101--detail-solid-waste-disposal-web
 * (Livewire web page, route name `data.solid-waste-disposal.detail`,
 * /data/solid-waste-disposal/{id}). Mirrors DetailCagesTrack exactly.
 */
#[Layout('data.solid-waste-disposal-detail')]
class DetailSolidWasteDisposal extends Component
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
        return SolidWasteDisposalRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(SolidWasteDisposalRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-solid-waste-disposal');
    }
}
