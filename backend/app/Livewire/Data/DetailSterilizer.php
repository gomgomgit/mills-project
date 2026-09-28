<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\SterilizerRecord;
use App\Services\SterilizerRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailSterilizer — screen-125--detail-sterilizer-web
 * (Livewire web page, route name `data.sterilizer.detail`,
 * /data/sterilizer/{id}). Mirrors DetailCpoDispatch exactly.
 */
#[Layout('data.sterilizer-detail')]
class DetailSterilizer extends Component
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
        return SterilizerRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(SterilizerRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-sterilizer');
    }
}
