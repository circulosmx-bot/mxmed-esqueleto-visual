<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
/** Purpose-bound future transport. Success means transport acknowledged, never staff authority.
 * Raw token/email are transient only. Implementations must never log them or retain them in receipts.
 * No production adapter is authorized/installed in IMP02. */
interface InvitationDeliveryPort
{
    public function deliver(string $invitationId,string $caseId,string $normalizedRecipient,string $rawToken,string $expiresAt):void;
}
