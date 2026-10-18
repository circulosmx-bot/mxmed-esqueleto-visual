<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Http\CanonicalHttpSessionResolver;
use PDO;
/** Shared authoritative transaction for case commands and invitation binding. */
final class UnitOfWork
{
    public function __construct(private PDO $pdo, private CanonicalHttpSessionResolver $sessions) {}
    private function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->pdo->prepare($sql);$q->execute($args);return $q;}
    private function row(string $sql,array $args=[]):array
    {return $this->query($sql,$args)->fetch(PDO::FETCH_ASSOC)?:throw new \RuntimeException('admission_denied');}
    public function run(array $cookies,callable $fn):array
    {
        $actor=$this->sessions->resolve($cookies,true);
        if(!$actor)throw new \RuntimeException('admission_authentication_required');
        if($this->pdo->inTransaction())throw new \RuntimeException('admission_transaction_required');
        try{
            $this->pdo->beginTransaction();
            // Same account -> staff -> credential order as canonical governance. Actor lock also serializes operation IDs.
            $a=$this->row('SELECT status,email_verified_at FROM auth_accounts WHERE account_id=? FOR UPDATE',[$actor->accountId()]);
            $this->query('SELECT account_id FROM internal_staff WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetchAll();
            $c=$this->row('SELECT credential_version FROM auth_account_credentials WHERE account_id=? FOR UPDATE',[$actor->accountId()]);
            if($a['status']!=='active'||$a['email_verified_at']===null||(int)$c['credential_version']!==$actor->principal()->credentialVersion())throw new \RuntimeException('admission_denied');
            $result=$fn($actor);
            $live=$this->sessions->resolve($cookies,true);
            if(!$live||$live->accountId()!==$actor->accountId()||(string)$live->session()->sessionId()!==(string)$actor->session()->sessionId())throw new \RuntimeException('admission_denied');
            if(!$this->pdo->commit())throw new \RuntimeException('admission_commit_uncertain');
            return $result;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();
            if($e instanceof \PDOException&&in_array((int)($e->errorInfo[1]??0),[1062,1213,1205],true))throw new \RuntimeException('admission_conflict');throw $e;}
    }
}
