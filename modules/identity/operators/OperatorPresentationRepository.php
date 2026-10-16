<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;

/** Data port of ProfileOperatorService's existing locked transaction; no authority/writer entry point. */
final class OperatorPresentationRepository
{
    public function __construct(private PDO $pdo){}
    public function ready(): bool
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_profile_operator_presentations'")->fetchColumn()===1;
    }
    private function query(string $sql,array $args=[]): \PDOStatement
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('operator_presentation_transaction_required');
        $s=$this->pdo->prepare($sql);$s->execute($args);return $s;
    }
    public function invitation(string $profile,string $invitation): ?array
    {
        return $this->query('SELECT * FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND invitation_id=? FOR UPDATE',[$profile,$invitation])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function membership(string $profile,string $membership): ?array
    {
        return $this->query('SELECT * FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND membership_id=? FOR UPDATE',[$profile,$membership])->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function reserve(string $profile,string $invitation,array $alias,string $now,?int $preferred=null): array
    {
        $this->uniqueAlias($profile,$alias['key']);
        $used=$this->query('SELECT seat_position FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND released_at IS NULL FOR UPDATE',[$profile])->fetchAll(PDO::FETCH_COLUMN);
        $free=array_values(array_diff([1,2],array_map('intval',$used)));
        if(!$free)throw new \RuntimeException('operator_position_unavailable');
        $position=$preferred!==null&&in_array($preferred,$free,true)?$preferred:$free[0];
        $id=\Identity\Services\OneTimeTokenCodec::id('oppresent_');
        $this->query('INSERT INTO auth_profile_operator_presentations(presentation_id,profile_doctor_id,invitation_id,alias_display,alias_key,seat_position,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?)',[$id,$profile,$invitation,$alias['display'],$alias['key'],$position,$now,$now]);
        return $this->invitation($profile,$invitation);
    }
    public function accept(string $profile,string $invitation,string $membership,mixed $proposed,string $now): array
    {
        $p=$this->invitation($profile,$invitation);
        if(!$p||$p['released_at']!==null||$p['membership_id']!==null)throw new \RuntimeException('operator_presentation_unavailable');
        $alias=OperatorAlias::normalize($proposed);
        if($p['alias_display']!==null&&$alias['display']!==null&&$alias['display']!==$p['alias_display'])throw new \RuntimeException('operator_alias_offer_conflict');
        if($p['alias_display']===null)$this->uniqueAlias($profile,$alias['key'],$p['presentation_id']);
        $this->query('UPDATE auth_profile_operator_presentations SET membership_id=?,alias_display=?,alias_key=?,revision=revision+1,updated_at=? WHERE presentation_id=?',[$membership,$p['alias_display']??$alias['display'],$p['alias_key']??$alias['key'],$now,$p['presentation_id']]);
        return $this->membership($profile,$membership);
    }
    public function rename(string $profile,string $membership,int $revision,mixed $value,string $now): array
    {
        $p=$this->membership($profile,$membership);
        if(!$p||$p['released_at']!==null)throw new \RuntimeException('operator_presentation_unavailable');
        if((int)$p['revision']!==$revision)throw new \RuntimeException('operator_presentation_revision_conflict');
        $alias=OperatorAlias::normalize($value);
        if($alias['display']===null)throw new \InvalidArgumentException('operator_alias_required');
        $this->uniqueAlias($profile,$alias['key'],$p['presentation_id']);
        $this->query('UPDATE auth_profile_operator_presentations SET alias_display=?,alias_key=?,revision=revision+1,updated_at=? WHERE presentation_id=?',[$alias['display'],$alias['key'],$now,$p['presentation_id']]);
        return $this->membership($profile,$membership);
    }
    public function release(string $profile,string $invitation,string $now): void
    {
        $this->query('UPDATE auth_profile_operator_presentations SET released_at=?,updated_at=?,revision=revision+1 WHERE profile_doctor_id=? AND invitation_id=? AND released_at IS NULL',[$now,$now,$profile,$invitation]);
    }
    private function uniqueAlias(string $profile,?string $key,?string $except=null): void
    {
        if($key===null)return;
        if($this->query('SELECT presentation_id FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND active_alias_key=? AND presentation_id<>? FOR UPDATE',[$profile,$key,$except??''])->fetchColumn()!==false)
            throw new \RuntimeException('operator_alias_collision');
    }
    public static function projection(?array $row): array
    {
        return ['presentation_version'=>$row?1:null,'presentation_id'=>$row['presentation_id']??null,'personal_alias'=>$row['alias_display']??null,
            'seat_position'=>$row?(int)$row['seat_position']:null,'presentation_revision'=>$row?(int)$row['revision']:null,'presentation_released'=>$row?$row['released_at']!==null:null];
    }
}
