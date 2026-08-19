<?php

namespace App\Enums;

/**
 * Shared unit-of-measure enum for grading-parameter.uom and
 * grading-detail.uom — entity-catalog: enum(kg, bunch).
 *
 * grading-detail.uom is a snapshot copy of the grading-parameter's uom at
 * selection time (stored independently, not derived live via the
 * relationship) — see GradingDetail::gradingParameter().
 */
enum Uom: string
{
    case Kg = 'kg';
    case Bunch = 'bunch';
}
