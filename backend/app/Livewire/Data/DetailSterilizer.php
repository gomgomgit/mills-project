<?php

namespace App\Livewire\Data;

use App\Livewire\Data\Concerns\HandlesRecordVerification;
use App\Models\SterilizerRecord;
use App\Services\SterilizerRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        } catch (ModelNotFoundException) {
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
