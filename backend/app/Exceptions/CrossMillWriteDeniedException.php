<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * CrossMillWriteDeniedException — thrown by the 18 *RecordService
 * create()/update() methods (via App\Support\Concerns\ScopesToActorMill)
 * when a mill-bound actor (Operator / Supervisor / Mill Management) tries
 * to write a station log sheet that belongs to ANOTHER mill:
 *
 *   - create(): the chosen `production_line_id` resolves to a Station whose
 *     `business_unit_id` is not the actor's `users.business_unit_id`.
 *   - update(): the record being PATCHed hangs off such a Station.
 *
 * 403 FORBIDDEN, not 422: the payload is well-formed and the target row
 * genuinely exists — the actor simply has no access to it. That is an
 * authorization refusal, and this project already spells that exact
 * decision the same way in BusinessAreaMismatchException (login, mill
 * mismatch → 403). ApiExceptionHandler's AuthorizationException branch
 * renders it as `{ "message": ..., "code": "FORBIDDEN" }` with no handler
 * change needed.
 *
 * Deliberately NOT implementing HasErrorCode: ApiExceptionHandler's
 * AuthorizationException branch short-circuits before any errorCode() is
 * consulted, so declaring a narrower code here would be a lie about what
 * clients actually receive. The distinguishing signal on the wire is the
 * message.
 *
 * The companion condition — a mill-bound actor with NO business_unit_id at
 * all — is deliberately NOT this exception: it is a broken/incomplete
 * account, not a refused access, so ScopesToActorMill::actorMillId()
 * fails closed with 422 VALIDATION_ERROR and an actionable "Hubungi Admin"
 * message, mirroring StationReportService::resolveBusinessUnit().
 */
class CrossMillWriteDeniedException extends AuthorizationException
{
    public function __construct(string $message = 'Data yang dipilih bukan milik mill Anda.')
    {
        parent::__construct($message);
    }
}
