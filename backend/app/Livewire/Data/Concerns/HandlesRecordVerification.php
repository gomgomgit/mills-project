<?php

namespace App\Livewire\Data\Concerns;

use App\Services\RecordVerificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    /** @return class-string<Model> */
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
        } catch (AuthorizationException|ValidationException|HttpException $e) {
            // Sejak 2026-09-28 setVerification() juga menegakkan CAKUPAN
            // MILL, bukan cuma peran: CrossMillWriteDeniedException (403,
            // record milik mill lain) dan ValidationException (422, aktor
            // terikat mill tanpa `users.business_unit_id`). Ditangkap di
            // sini supaya penolakannya muncul sebagai alert di layar Detail
            // — pola yang sama dipakai ke-18 Form*::save() sejak tahap 1a —
            // bukan halaman 403 yang membuang konteks layar.
            //
            // Sejak 2026-10-03 juga HttpException: PeriodClosedException
            // (422 PERIOD_CLOSED, usecase-141) adalah HttpException, bukan
            // ValidationException, sehingga sebelumnya lolos dan layar Detail
            // menerima respons 422 mentah tanpa alert. Penolakannya sudah
            // benar (nol baris berubah) — yang hilang hanya pesannya.
            //
            // Pesannya diambil dari exception, bukan ditulis ulang di sini,
            // supaya wording penolakan cuma ada satu tempat.
            $this->verificationMessage = $e instanceof ValidationException
                ? ($e->validator->errors()->first() ?: $e->getMessage())
                : $e->getMessage();

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
