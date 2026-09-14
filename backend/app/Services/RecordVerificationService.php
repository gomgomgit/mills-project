<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;

/**
 * RecordVerificationService — single place where "Checked By" (Supervisor)
 * and "Acknowledged By" (Mill Management) are written, for the direct
 * approve/un-approve action on the Detail screens (2026-09-14).
 *
 * Before this existed, verification could only be set by opening the Form
 * in edit mode and re-saving the whole record — which meant a Supervisor
 * had to submit every field again just to attest one checkbox. This service
 * writes ONLY the verification column, so the rest of the record is never
 * touched by an approve action.
 *
 * Deliberately generic over the 18 record models rather than duplicated
 * into each of the 18 *RecordService classes: the rule is identical for
 * every station and the column names are identical too, so the per-station
 * services keep owning form save/normalisation while this owns attestation.
 * Their own applyVerification() (the Form path) stays as-is — both paths
 * write the same two columns with the same role rule.
 *
 * Role rule (product decision 2026-09-14): Supervisor writes checked_by,
 * Mill Management writes acknowledged_by, Admin may write BOTH. Operator
 * never verifies anything. The actor's own id is always what gets stored —
 * nobody can attest on another user's behalf.
 */
class RecordVerificationService
{
    public const LEVEL_CHECKED = 'checked';

    public const LEVEL_ACKNOWLEDGED = 'acknowledged';

    /**
     * Grading is the one station that never collects Checked By — see
     * GradingRecordService::applyVerification(), consistent with Form
     * Grading mobile (screen-011) and Detail Grading Web (screen-020).
     * Listed by model class so the exception lives in exactly one place
     * instead of being re-encoded in the Detail component and the API.
     *
     * @var list<class-string<Model>>
     */
    protected const NO_CHECKED_BY_MODELS = [
        \App\Models\GradingRecord::class,
    ];

    /**
     * True when $actor may write $level on $modelClass — drives both the
     * UI (whether to render the button at all) and the write guard below,
     * so a hidden button and a forged request are refused by the same rule.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function canVerify(string $modelClass, ?User $actor, string $level): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if ($level === self::LEVEL_CHECKED && $this->lacksCheckedBy($modelClass)) {
            return false;
        }

        return match ($level) {
            self::LEVEL_CHECKED => in_array($actor->role, [UserRole::Supervisor, UserRole::Admin], true),
            self::LEVEL_ACKNOWLEDGED => in_array($actor->role, [UserRole::MillManagement, UserRole::Admin], true),
            default => false,
        };
    }

    /**
     * Sets or clears one verification column. $value true stores the
     * actor's id, false clears it back to null (un-approve is allowed, same
     * as un-checking the Form checkbox — product decision 2026-09-14).
     *
     * @param  class-string<Model>  $modelClass
     *
     * @throws UnauthorizedException when the actor's role may not write this level
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the record is gone
     */
    public function setVerification(string $modelClass, string $recordId, User $actor, string $level, bool $value): void
    {
        if (! $this->canVerify($modelClass, $actor, $level)) {
            throw new UnauthorizedException('Role ini tidak berhak melakukan verifikasi tersebut.');
        }

        $record = $modelClass::query()->findOrFail($recordId);

        $record->forceFill([
            $this->column($level) => $value ? $actor->id : null,
        ])->save();
    }

    /**
     * Station type (as stored in stations.type, and as used in the mobile
     * API path segment) → record model. An EXPLICIT map on purpose: the
     * type arrives from a request, so it must never be turned into a class
     * name by string concatenation.
     *
     * @var array<string, class-string<Model>>
     */
    protected const MODEL_BY_STATION_TYPE = [
        'boiler-room' => \App\Models\BoilerRoomRecord::class,
        'cages-track' => \App\Models\CagesTrackRecord::class,
        'clarification' => \App\Models\ClarificationRecord::class,
        'cpo-dispatch' => \App\Models\CpoDispatchRecord::class,
        'depricarping' => \App\Models\DepricarpingRecord::class,
        'effluent-plant' => \App\Models\EffluentPlantRecord::class,
        'engine-room' => \App\Models\EngineRoomRecord::class,
        'grading' => \App\Models\GradingRecord::class,
        'kernel-dispatch' => \App\Models\KernelDispatchRecord::class,
        'kernel-plant' => \App\Models\KernelPlantRecord::class,
        'pressing' => \App\Models\PressingRecord::class,
        'process-quality-control' => \App\Models\ProcessQualityControlRecord::class,
        'process-water' => \App\Models\ProcessWaterRecord::class,
        'solid-waste-disposal' => \App\Models\SolidWasteDisposalRecord::class,
        'sterilizer' => \App\Models\SterilizerRecord::class,
        'storage-tank' => \App\Models\StorageTankRecord::class,
        'threshing' => \App\Models\ThreshingRecord::class,
        'weighbridge' => \App\Models\WeighbridgeRecord::class,
    ];

    /** @return class-string<Model>|null */
    public function modelForStationType(string $stationType): ?string
    {
        return self::MODEL_BY_STATION_TYPE[$stationType] ?? null;
    }

    /** @param  class-string<Model>  $modelClass */
    public function lacksCheckedBy(string $modelClass): bool
    {
        return in_array($modelClass, self::NO_CHECKED_BY_MODELS, true);
    }

    protected function column(string $level): string
    {
        return $level === self::LEVEL_CHECKED ? 'checked_by' : 'acknowledged_by';
    }
}
