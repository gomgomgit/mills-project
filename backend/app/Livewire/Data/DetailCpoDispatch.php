<?php

namespace App\Livewire\Data;

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
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(CpoDispatchRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-cpo-dispatch');
    }
}
