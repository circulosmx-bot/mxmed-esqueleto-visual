<?php
declare(strict_types=1);

namespace Identity\Audit;

final class IdentityAuditReasonResolver
{
    private const LOGIN_FAILURE = [
        'invalid_credentials'=>['FAILURE','INVALID_CREDENTIALS'],
        'account_not_active'=>['DENIED','POLICY_DENIED'],
        'account_locked'=>['FAILURE','ACCOUNT_LOCKED'],
        'rate_limited'=>['FAILURE','RATE_LIMITED'],
        'step_up_required'=>['DENIED','STEP_UP_REQUIRED'],
        'internal_error'=>['FAILURE','INTERNAL_ERROR'],
    ];

    /** @return array{result:string,reason_code:string} */
    public function loginFailure(string $domainReason): array
    {
        $resolved = self::LOGIN_FAILURE[$domainReason] ?? null;
        if ($resolved === null) throw new \InvalidArgumentException('unmapped_login_failure_reason');
        return ['result'=>$resolved[0], 'reason_code'=>$resolved[1]];
    }
}
