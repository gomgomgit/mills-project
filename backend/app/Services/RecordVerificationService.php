<?php

namespace App\Services;

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\CrossMillWriteDeniedException;
use App\Models\BoilerRoomRecord;
use App\Models\CagesTrackRecord;
use App\Models\ClarificationRecord;
use App\Models\CpoDispatchRecord;
use App\Models\DepricarpingRecord;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomRecord;
use App\Models\GradingRecord;
use App\Models\KernelDispatchRecord;
use App\Models\KernelPlantRecord;
use App\Models\PressingRecord;
use App\Models\ProcessQualityControlRecord;
use App\Models\ProcessWaterRecord;
use App\Models\SolidWasteDisposalRecord;
use App\Models\SterilizerRecord;
use App\Models\StorageTankRecord;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Support\Concerns\EnforcesPeriodLock;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;

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
    use EnforcesPeriodLock, ScopesToActorMill;

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
        GradingRecord::class,
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
     * @throws CrossMillWriteDeniedException 403 — record belongs to another mill
     * @throws ValidationException 422 — mill-bound actor with no mill
     * @throws ModelNotFoundException when the record is gone
     */
    public function setVerification(string $modelClass, string $recordId, User $actor, string $level, bool $value): void
    {
        if (! $this->canVerify($modelClass, $actor, $level)) {
            throw new UnauthorizedException('Role ini tidak berhak melakukan verifikasi tersebut.');
        }

        $record = $modelClass::query()->findOrFail($recordId);

        // JALUR TULIS KEEMPAT. canVerify() di atas hanya memeriksa PERAN;
        // sampai 2026-09-28 tidak ada satu pun pemeriksaan mill di sini,
        // sehingga Supervisor Mill A yang tahu UUID sebuah record Mill B
        // bisa menyetujuinya — atau MEMBATALKAN persetujuan orang lain,
        // yang sama merusaknya karena arah `false` menghapus atestasi yang
        // sah.
        //
        // Penjaganya persis sama dengan create()/update() di 18
        // *RecordService (ScopesToActorMill, tahap 1a): Admin dinilai dari
        // PERAN dan bebas; Operator/Supervisor/Mill Management dibatasi
        // `users.business_unit_id`; aktor non-Admin tanpa mill
        // gagal-tertutup 422. Bukan mekanisme kedua — trait yang sama.
        //
        // Letaknya TEPAT SETELAH findOrFail() dan SEBELUM forceFill(), jadi
        // penolakan terjadi sebelum satu kolom pun berubah: tidak ada efek
        // separuh untuk di-rollback.
        $this->assertRecordWritableByActor($record, $actor);

        // KUNCI PERIODE (usecase-141) — JALUR TULIS KEEMPAT, dan satu-satunya yang
        // TIDAK lewat salah satu dari 18 *RecordService, jadi guard di sana tidak
        // menutupinya. Spec menyebut verifikasi secara eksplisit: menyetujui atau
        // membatalkan persetujuan mengubah angka yang dibaca laporan periode sama
        // nyatanya dengan mengubah nilainya, jadi ia ikut terkunci bersama
        // penutupan.
        //
        // Jenis stasiun dan mill dibaca dari STASIUN MILIK RECORD, bukan dari
        // segmen {stationType} pada URL: yang terakhir datang dari request, dan
        // record-nya sendiri adalah sumber yang tidak bisa dipalsukan pemanggil.
        //
        // Tanggal kejadian: 17 stasiun memakai `date`, Weighbridge memakai
        // `record_datetime`. Keduanya dibaca apa adanya — atribut yang tidak ada
        // mengembalikan null, dan guard-nya menolak tanggal kosong dengan pesannya
        // sendiri alih-alih menebak hari ini.
        $record->loadMissing('station');
        $station = $record->station;
        $stationType = $station->type instanceof StationTypeEnum
            ? $station->type->value
            : (string) $station->type;
        $eventDate = $record->date ?? $record->record_datetime ?? null;

        $this->assertPeriodOpenForWrite(
            $stationType,
            $station->business_unit_id,
            $eventDate instanceof Carbon ? $eventDate->toDateString() : ($eventDate === null ? null : (string) $eventDate),
        );

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
        'boiler-room' => BoilerRoomRecord::class,
        'cages-track' => CagesTrackRecord::class,
        'clarification' => ClarificationRecord::class,
        'cpo-dispatch' => CpoDispatchRecord::class,
        'depricarping' => DepricarpingRecord::class,
        'effluent-plant' => EffluentPlantRecord::class,
        'engine-room' => EngineRoomRecord::class,
        'grading' => GradingRecord::class,
        'kernel-dispatch' => KernelDispatchRecord::class,
        'kernel-plant' => KernelPlantRecord::class,
        'pressing' => PressingRecord::class,
        'process-quality-control' => ProcessQualityControlRecord::class,
        'process-water' => ProcessWaterRecord::class,
        'solid-waste-disposal' => SolidWasteDisposalRecord::class,
        'sterilizer' => SterilizerRecord::class,
        'storage-tank' => StorageTankRecord::class,
        'threshing' => ThreshingRecord::class,
        'weighbridge' => WeighbridgeRecord::class,
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
