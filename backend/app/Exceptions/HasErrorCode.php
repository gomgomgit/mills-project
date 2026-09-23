<?php

namespace App\Exceptions;

/**
 * HasErrorCode — opt-in contract that lets an exception carry the
 * machine-readable `code` field of shared_decisions.error_format v5:
 *
 *   { "message": "...", "code": "ERROR_CODE", "errors": { ... } }
 *
 * Added 2026-09-22 together with screen-128--kelola-periode-pelaporan,
 * whose PERIOD_OVERLAP / PERIOD_CLOSED_IMMUTABLE / PERIOD_ALREADY_CLOSED /
 * PERIOD_NOT_CLOSED errors must be told apart by the client
 * programmatically, not by matching the Indonesian message text.
 *
 * Deliberately an OPT-IN interface rather than a new base class: every
 * pre-existing exception in this namespace (ExportFailedException,
 * ProductionLineHasStationsException, ...) keeps rendering exactly as
 * before — ApiExceptionHandler only emits `code` for exceptions that
 * implement this, plus the four framework-level codes it derives itself
 * (VALIDATION_ERROR / UNAUTHENTICATED / FORBIDDEN / NOT_FOUND). The
 * addition is purely additive: no existing key changes shape or value.
 *
 * Codes are UPPER_SNAKE_CASE per shared_decisions.naming_conventions.
 */
interface HasErrorCode
{
    /** Machine-readable error code, UPPER_SNAKE_CASE. */
    public function errorCode(): string;
}
