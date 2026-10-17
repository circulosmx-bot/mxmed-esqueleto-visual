<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\{Clock,SystemClock};
use PDO;

/** Independent operation/policy; transport peer is trusted, forwarded client IP is not. */
final class ReportRatePolicy
{
    public const OPERATION='PUBLIC_PROFILE_CORRECTION_SUBMIT';
    public function __construct(private PDO $pdo,private ReportCipher $cipher,private Clock $clock=new SystemClock(),
        private int $browserLimit=3,private int $peerLimit=10,private int $profileLimit=20)
    {foreach([$browserLimit,$peerLimit,$profileLimit] as $limit)if($limit<1||$limit>1000)throw new \InvalidArgumentException('correction_rate_policy_invalid');}
    public function usesConnection(PDO $pdo):bool{return $this->pdo===$pdo;}
    public function consume(string $browser,string $peer,string $profile):string
    {
        if($this->pdo->inTransaction()||!filter_var($peer,FILTER_VALIDATE_IP))throw new ReportDenied(503);
        $dimensions=[];
        foreach(['browser'=>[$browser,$this->browserLimit],'peer'=>[$peer,$this->peerLimit],'profile'=>[$profile,$this->profileLimit]] as $kind=>[$value,$limit])
            $dimensions[$this->cipher->digest('rate:'.$kind,$value)]=$limit;
        ksort($dimensions);$window=intdiv($this->clock->now()->getTimestamp(),3600)*3600;
        try {
            $this->pdo->beginTransaction();
            foreach($dimensions as $digest=>$limit){
                $q=$this->pdo->prepare('INSERT INTO profiles_correction_rate_buckets(operation,dimension_digest,window_start,hits) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE hits=hits');
                $q->execute([self::OPERATION,$digest,$window]);
                // LAST_INSERT_ID/rowCount are deliberately not used with native MySQL upserts.
                $q=$this->pdo->prepare('SELECT hits FROM profiles_correction_rate_buckets WHERE operation=? AND dimension_digest=? AND window_start=? FOR UPDATE');$q->execute([self::OPERATION,$digest,$window]);
                $hits=(int)$q->fetchColumn();
                if($hits>=$limit)throw new ReportDenied(429);
            }
            foreach($dimensions as $digest=>$limit){
                $q=$this->pdo->prepare('UPDATE profiles_correction_rate_buckets SET hits=hits+1 WHERE operation=? AND dimension_digest=? AND window_start=?');
                $q->execute([self::OPERATION,$digest,$window]);
            }
            $this->pdo->commit();
            return $this->cipher->digest('abuse-reference',implode(':',array_keys($dimensions)).':'.$window);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
