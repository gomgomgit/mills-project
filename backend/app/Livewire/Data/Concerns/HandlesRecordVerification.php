<?php

namespace App\Livewire\Data\Concerns;

use App\Services\RecordVerificationService;
use Illuminate\Validation\UnauthorizedException;

/**
 * HandlesRecordVerification — the approve/un-approve action shared by all
 * 18 Detail screens (2026-09-14).
 *
 * A Detail component opts in by using this trait and implementing
 * verificationModelClass() + reloadRecord(). Everything else — the role
 * rule, the write, the refresh, the flash message — lives here so the
 * behaviour cannot drift between the 18 stations.
 *
 * The Detail screens stay read-only for record DATA: the only thing these
 * actions write is checked_by/acknowledged_by, via
 * RecordVerificationService. Editing actual field values still goes
 * through the Form screen.
 */
trait HandlesRecordVerification
{
    public ?string $verificationMessage = null;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    abstract protected function verificationModelClass(): string;

    /** Re-reads $this->record through the screen's own service after a write. */
    abstract protected function reloadRecord(): void;

    public function toggleChecked(): void
    {
        $this->toggleVerification(RecordVerificationService::LEVEL_CHECKED);
    }

    public function toggleAcknowledged(): void
    {
        $this->toggleVerification(RecordVerificationService::LEVEL_ACKNOWLEDGED);
    }

    public function canCheck(): bool
    {
        return $this->verificationService()->canVerify(
            $this->verificationModelClass(),
            auth()->user(),
            RecordVerificationService::LEVEL_CHECKED,
        );
    }

    public function canAcknowledge(): bool
    {
        return $this->verificationService()->canVerify(
            $this->verificationModelClass(),
            auth()->user(),
            RecordVerificationService::LEVEL_ACKNOWLEDGED,
        );
    }

    public function isChecked(): bool
    {
        return filled($this->record['checked_by_name'] ?? null);
    }

    public function isAcknowledged(): bool
    {
        return filled($this->record['acknowledged_by_name'] ?? null);
    }

    protected function toggleVerification(string $level): void
    {
        if ($this->record === null) {
            return;
        }

        $checked = $level === RecordVerificationService::LEVEL_CHECKED;
        $turningOn = ! ($checked ? $this->isChecked() : $this->isAcknowledged());

        try {
            $this->verificationService()->setVerification(
                $this->verificationModelClass(),
                $this->id,
                auth()->user(),
                $level,
                $turningOn,
            );
        } catch (UnauthorizedException) {
            $this->verificationMessage = 'Anda tidak berhak melakukan verifikasi ini.';

            return;
        }

        $this->reloadRecord();

        $this->verificationMessage = match (true) {
            $checked && $turningOn => 'Data ditandai sudah diperiksa.',
            $checked => 'Tanda diperiksa dibatalkan.',
            $turningOn => 'Data ditandai sudah dikonfirmasi.',
            default => 'Tanda dikonfirmasi dibatalkan.',
        };
    }

    protected function verificationService(): RecordVerificationService
    {
        return app(RecordVerificationService::class);
    }
}
