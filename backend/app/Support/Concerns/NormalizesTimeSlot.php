<?php

namespace App\Support\Concerns;

/**
 * NormalizesTimeSlot — brings a detail row's `time_slot` back to the 'H:i'
 * spelling the 24 canonical slots are written in, before it is validated.
 *
 * ── THE DEFECT THIS EXISTS FOR (found 2026-10-02) ───────────────────────
 *
 * `*_details.time_slot` is a real `time` column. PostgreSQL therefore returns
 * it as '07:00:00', while canonicalTimeSlots() builds its list with
 * sprintf('%02d:00', ...) — '07:00'. The two never matched, so:
 *
 *   - CREATING a record worked (the form posts '07:00', which is canonical),
 *   - EDITING that same record and saving again was ALWAYS refused, with
 *     "Time-Slot ... harus salah satu dari 24 slot kanonis (07:00-06:00)",
 *     because the value loaded back from the database was '07:00:00'.
 *
 * Eleven station types share the time-slot grid and all eleven were affected:
 * Boiler Room, Clarification, Depricarping, Effluent Plant, Engine Room,
 * Kernel Plant, Pressing, Process Quality Control, Process Water, Storage
 * Tank and Threshing. Not a fixture problem — reproduced on records created
 * through the form itself.
 *
 * ── WHY 4080 PASSING TESTS SAID NOTHING ─────────────────────────────────
 *
 * The suite runs on SQLite, which has no time type: the column keeps whatever
 * string it was handed, so a round trip returns '07:00' and the comparison
 * succeeds. Production runs PostgreSQL. Same divergence that has bitten this
 * project before (ILIKE, a bare where() on a date column, '' into double
 * precision) — see the SQLite-vs-PostgreSQL note in the project memory. The
 * regression test for this therefore feeds '07:00:00' EXPLICITLY rather than
 * relying on a round trip, so it fails on SQLite too when this normalisation
 * is removed.
 *
 * ── WHY THE WRITE PATH AND NOT THE MODEL ────────────────────────────────
 *
 * An accessor on the 11 Detail models would fix every reader at once, but it
 * would also change what the period reports and their CSV exports print. This
 * trait touches only what is about to be validated and persisted, so no screen
 * and no export changes shape. It also covers the mobile/API path, which goes
 * through the same services.
 */
trait NormalizesTimeSlot
{
    /**
     * '07:00:00' → '07:00'. Anything already canonical, empty, or not a
     * recognisable clock string is returned untouched — this normalises a
     * known spelling difference, it does not guess at unparseable input, which
     * must still reach the validator and be refused there.
     */
    protected function canonicalTimeSlot(mixed $timeSlot): mixed
    {
        if (! is_string($timeSlot)) {
            return $timeSlot;
        }

        // Accepts '07:00', '07:00:00' and '07:00:00.000000' (PostgreSQL may
        // return fractional seconds); keeps only hours and minutes. Hours and
        // minutes must be REAL ones — '25:00:00' is not a clock reading a time
        // column could ever hold, so it is left alone for the validator to
        // refuse rather than quietly turned into the plausible-looking '25:00'.
        if (preg_match('/^((?:[01]\d|2[0-3]):[0-5]\d)(?::[0-5]\d(?:\.\d+)?)?$/', trim($timeSlot), $matches) === 1) {
            return $matches[1];
        }

        return $timeSlot;
    }
}
