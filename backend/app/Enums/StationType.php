<?php

namespace App\Enums;

/**
 * Station.type — entity-catalog: enum(weighbridge, grading, cages-track,
 * threshing, pressing, depricarping, kernel-plant, other)
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
    case Other = 'other';
}
