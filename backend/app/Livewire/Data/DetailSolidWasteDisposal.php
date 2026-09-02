<?php

namespace App\Livewire\Data;

use App\Services\SolidWasteDisposalRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    public string $id;

    public ?array $record = null;

    public bool $notFound = false;

    public function mount(string $id): void
    {
        $this->id = $id;

        try {
            $this->record = app(SolidWasteDisposalRecordService::class)->getDetail($id);
        } catch (ModelNotFoundException) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.data.detail-solid-waste-disposal');
    }
}
