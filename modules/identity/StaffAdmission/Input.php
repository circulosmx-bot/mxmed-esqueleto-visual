<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
final class Input
{
    public const FIELDS=['given_names','first_surname','second_surname','operation_state','operation_city'];
    public static function validate(string $action,array $b):array
    {
        $keys=match($action){
            'prepare'=>['operation_id','recipient_reference','channel','proposed_category',...self::FIELDS],
            'update','correct'=>['operation_id','case_id','expected_version',...self::FIELDS],
            'confirm','revoke'=>['operation_id','case_id','expected_version'],
            'read'=>['case_id'],'result'=>['operation_id'],'csrf'=>[],
            default=>throw new \InvalidArgumentException('admission_invalid'),
        };
        $actual=array_keys($b);sort($actual);sort($keys);if($actual!==$keys)throw new \InvalidArgumentException('admission_invalid');
        foreach($b as $k=>$v){
            if($k==='expected_version'){if(!is_int($v)||$v<1||$v>2147483646)throw new \InvalidArgumentException('admission_invalid');continue;}
            if(!is_string($v))throw new \InvalidArgumentException('admission_invalid');
            if(in_array($k,self::FIELDS,true)){
                if(strlen($v)>120||!preg_match('//u',$v)||preg_match('/[\x00-\x1f\x7f<>]/u',$v)||trim($v)!==$v)throw new \InvalidArgumentException('admission_invalid');
            }elseif(in_array($k,['operation_id','recipient_reference'],true)){
                if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v))throw new \InvalidArgumentException('admission_invalid');
            }elseif($k==='case_id'&&!preg_match('/^adm_[a-f0-9]{32}$/D',$v))throw new \InvalidArgumentException('admission_invalid');
        }
        if($action==='prepare'&&(!in_array($b['channel'],['ASSISTED','REMOTE_PREAUTHORIZED'],true)||!in_array($b['proposed_category'],['ADVISOR','MASTER_ADMIN'],true)))throw new \InvalidArgumentException('admission_invalid');
        return $b;
    }
}
