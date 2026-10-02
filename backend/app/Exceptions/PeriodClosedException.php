<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodClosedException — thrown by every station *RecordService when a write
 * is not admitted by an OPEN Period Pelaporan
 * (usecase-141--kunci-input-periode-tertutup → 422 PERIOD_CLOSED).
 *
 * THE RULE IS A WHITELIST, NOT A BLACKLIST. Writing station data requires that
 * a period exists for that mill, that the period's row for THAT station type is
 * status='open', and that the record's EVENT DATE falls inside that period's
 * range (inclusive at both ends). Anything else refuses. Stated as a whitelist
 * because that is what the user ratified on 2026-10-01: "ketika tidak ada
 * periode yang terbuka tidak bisa input data, dan input data hanya bisa pada
 * rentang waktu periode yang terbuka untuk stasiun tersebut".
 *
 * ONE CODE, FOUR MESSAGES. Every refusal carries `PERIOD_CLOSED`, and the
 * message says which of the four reasons applies: no period at all, the
 * station's row is still Draft, it is already Closed, or there IS an open
 * period but the event date sits outside its range. One code because the client
 * behaviour is identical in all four — refuse the write and show the message —
 * and inventing four codes would invite clients to branch on a distinction the
 * use case never defines. The code name is kept as the one the contract tests
 * pinned before the rule was widened (PERIOD_CLOSED), so those tests did not
 * have to be rewritten around a rename; read it as "no open period admits this
 * write", not strictly "a period was closed".
 *
 * 422 and not 409: the request is well-formed and the actor is allowed: what
 * fails is a precondition on the DATA being written, which is the same shape as
 * a validation failure. 409 is reserved in this codebase for conflicts over the
 * period itself (PERIOD_CLOSED_IMMUTABLE, PERIOD_NOT_CLOSED).
 */
class PeriodClosedException extends HttpException implements HasErrorCode
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_CLOSED';
    }
}
