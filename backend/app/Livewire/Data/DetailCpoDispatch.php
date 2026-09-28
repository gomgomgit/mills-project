<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\CpoDispatchRecord;
use App\Services\CpoDispatchRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailCpoDispatch — screen-104--detail-cpo-dispatch-web
 * (Livewire web page, route name `data.cpo-dispatch.detail`,
 * /data/cpo-dispatch/{id}). Mirrors DetailKernelDispatch exactly.
 */
#[Layout('data.cpo-dispatch-detail')]
class DetailCpoDispatch extends Component
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
        return CpoDispatchRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(CpoDispatchRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-cpo-dispatch');
    }
}
