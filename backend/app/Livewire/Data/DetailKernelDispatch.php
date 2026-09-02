<?php

namespace App\Livewire\Data;

use App\Services\KernelDispatchRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DetailKernelDispatch — screen-103--detail-kernel-dispatch-web
 * (Livewire web page, route name `data.kernel-dispatch.detail`,
 * /data/kernel-dispatch/{id}). Mirrors DetailSolidWasteDisposal exactly.
 */
#[Layout('data.kernel-dispatch-detail')]
class DetailKernelDispatch extends Component
{
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(KernelDispatchRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-kernel-dispatch');
    }
}
