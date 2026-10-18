<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Contracts\IdentityAccount;
final class InvitationInput
{
    public static function validate(string $action,array $b):array
    {
        $keys=match($action){
            'csrf'=>[], 'result'=>['operation_id'],
            'issue'=>['operation_id','case_id','expected_version','recipient_email'],
            'reissue'=>['operation_id','case_id','expected_version','recipient_email','invitation_id'],
            'revoke'=>['operation_id','case_id','expected_version','invitation_id'],
            'accept'=>['operation_id','case_id','expected_version','invitation_id','token'],
            default=>throw new \InvalidArgumentException('admission_invalid')};
        $actual=array_keys($b);sort($actual);sort($keys);if($actual!==$keys)throw new \InvalidArgumentException('admission_invalid');
        foreach($b as $k=>$v){
            $ok=match($k){
                'operation_id'=>is_string($v)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v),
                'case_id'=>is_string($v)&&preg_match('/^adm_[a-f0-9]{32}$/D',$v),
                'invitation_id'=>is_string($v)&&preg_match('/^sti_[a-f0-9]{32}$/D',$v),
                'expected_version'=>is_int($v)&&$v>=1&&$v<=2147483646,
                'token'=>is_string($v)&&preg_match('/^[A-Za-z0-9_-]{43}$/D',$v),
                'recipient_email'=>is_string($v)&&strlen($v)<=190,
                default=>false};
            if(!$ok)throw new \InvalidArgumentException('admission_invalid');
        }
        if(isset($b['recipient_email'])){
            try{$b['recipient_email']=IdentityAccount::normalizeEmail($b['recipient_email']);}
            catch(\Throwable){throw new \InvalidArgumentException('admission_invalid');}
        }
        return $b;
    }
}
