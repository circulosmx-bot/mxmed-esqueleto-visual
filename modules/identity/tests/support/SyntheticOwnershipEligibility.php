<?php
declare(strict_types=1);
/** Trusted positive evidence ONLY for disposable contract tests. Not autoloaded by runtime. */
final class SyntheticOwnershipEligibility implements \Identity\Contracts\InitialOwnerEligibilityPort
{
    public function assertEligible(PDO $transaction,string $accountId,string $doctorId):void
    {
        $db=(string)$transaction->query('SELECT DATABASE()')->fetchColumn();
        if(PHP_SAPI!=='cli'||!preg_match('/^mxmed_(?:identity_|op01_)[A-Za-z0-9_]+$/D',$db))throw new RuntimeException('synthetic_eligibility_isolation');
        $s=$transaction->prepare("SELECT 1 FROM auth_accounts WHERE account_id=? AND email_normalized LIKE '%@example.invalid'");$s->execute([$accountId]);
        if($s->fetchColumn()===false)throw new RuntimeException('synthetic_account_required');
    }
}
