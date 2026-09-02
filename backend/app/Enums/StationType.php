<?php

namespace App\Enums;

/**
 * Station.type — entity-catalog v15: enum(weighbridge, grading, cages-track,
 * threshing, pressing, depricarping, kernel-plant, solid-waste-disposal,
 * process-water, kernel-dispatch, cpo-dispatch, effluent-plant, storage-tank,
 * engine-room, boiler-room, clarification, process-quality-control,
 * sterilizer, other).
 * 2026-08-31: 10 new cases added (see
 * 2026_08_31_000001_widen_station_type_enum_add_10_new_types.php) for the
 * new MVP stations promoted out of the former 'other' placeholder bucket.
 * 2026-09-01: `Sterilizer` case added — the LAST of the 18 canonical
 * stations to be promoted out of `other`. 0 placeholders remain after this.
 */
enum StationType: string
{
    case Weighbridge = 'weighbridge';
    case Grading = 'grading';
    case CagesTrack = 'cages-track';
    case Threshing = 'threshing';
    case Pressing = 'pressing';
    case Depricarping = 'depricarping';
    case KernelPlant = 'kernel-plant';
    case SolidWasteDisposal = 'solid-waste-disposal';
    case ProcessWater = 'process-water';
    case KernelDispatch = 'kernel-dispatch';
    case CpoDispatch = 'cpo-dispatch';
    case EffluentPlant = 'effluent-plant';
    case StorageTank = 'storage-tank';
    case EngineRoom = 'engine-room';
    case BoilerRoom = 'boiler-room';
    case Clarification = 'clarification';
    case ProcessQualityControl = 'process-quality-control';
    case Sterilizer = 'sterilizer';
    case Other = 'other';
}
