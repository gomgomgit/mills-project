<?php

namespace App\Enums;

/**
 * Station.type — entity-catalog: enum(weighbridge, grading, cages-track, other)
 */
enum StationType: string
{
    case Weighbridge = 'weighbridge';
    case Grading = 'grading';
    case CagesTrack = 'cages-track';
    case Other = 'other';
}
