<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\CpoDispatchRecord;
use App\Services\CpoDispatchRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        } catch (ModelNotFoundException) {
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
