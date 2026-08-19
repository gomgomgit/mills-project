<?php

namespace App\Enums;

/**
 * Shared status enum for log-sheet records (weighbridge-record, grading-record,
 * cages-track-record) — entity-catalog: enum(draft_ongoing, draft_paused, saved, synced)
 */
enum RecordStatus: string
{
    case DraftOngoing = 'draft_ongoing';
    case DraftPaused = 'draft_paused';
    case Saved = 'saved';
    case Synced = 'synced';
}
