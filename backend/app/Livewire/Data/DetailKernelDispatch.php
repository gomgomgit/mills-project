<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\KernelDispatchRecord;
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
        return KernelDispatchRecord::class;
    }

    protected function reloadRecord(): void
    {
        $this->record = app(KernelDispatchRecordService::class)->getDetail($this->id);
    }

    public function render()
    {
        return view('livewire.data.detail-kernel-dispatch');
    }
}
