<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
final class Input
{
    public const FIELDS=['given_names','first_surname','second_surname','operation_state','operation_city'];
    public const EXTRA=['gender','contact_phone','avatar','presentation'];
    public static function validate(string $action,array $b):array
    {
        if($action==='update'&&array_key_exists('changes',$b)){
            $keys=array_keys($b);sort($keys);if($keys!==['case_id','changes','expected_version','operation_id']||!is_array($b['changes'])||!$b['changes']||count($b['changes'])>9)throw new \InvalidArgumentException('admission_invalid');
            $base=array_fill_keys(self::FIELDS,'');self::validate('update',array_diff_key($b,['changes'=>true])+$base);
            foreach($b['changes'] as $k=>$v){
                if(!in_array($k,[...self::FIELDS,...self::EXTRA],true)||!is_string($v))throw new \InvalidArgumentException('admission_invalid');
                if(in_array($k,['given_names','first_surname'],true)&&$v==='')throw new \InvalidArgumentException('admission_invalid');
                if(in_array($k,self::FIELDS,true))self::validate('update',array_diff_key($b,['changes'=>true])+array_replace($base,[$k=>$v]));
                if($k==='gender'&&!in_array($v,['UNSPECIFIED','FEMALE','MALE','NON_BINARY','SELF_DESCRIBED','PREFER_NOT_TO_SAY'],true))throw new \InvalidArgumentException('admission_invalid');
                if($k==='contact_phone'&&$v!==''&&!preg_match('/^\+[1-9][0-9]{7,14}$/D',$v))throw new \InvalidArgumentException('admission_invalid');
                if($k==='avatar'&&!in_array($v,['NONE','NEUTRAL','FEMININE','MASCULINE'],true))throw new \InvalidArgumentException('admission_invalid');
                if($k==='presentation'&&!in_array($v,['PLACEHOLDER','AVATAR','PORTRAIT'],true))throw new \InvalidArgumentException('admission_invalid');
            }
            return $b;
        }
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
