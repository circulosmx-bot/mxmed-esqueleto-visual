<?php
declare(strict_types=1);
// Synthetic SMS sink shared by the owning disposable CLI routers only.
require_once dirname(__DIR__,3).'/agenda/contracts/OtpProviderPort.php';
final class C4UiSyntheticSms implements Agenda\Contracts\OtpProviderPort {
    public function providerId():string{return 'disposable-c4-ui-only';}
    public function configured():bool{return true;}
    public function deliver(string $channel,string $destination,string $secret,array $context=[]):Agenda\Contracts\OtpDeliveryResult {
        if($channel!=='sms'||!preg_match('/^\+1555[0-9]{7}$/D',$destination))return new Agenda\Contracts\OtpDeliveryResult(false,'synthetic',null);
        $path=(string)getenv('C4_UI_QA_SMS');$rows=is_file($path)?json_decode(file_get_contents($path),true):[];
        $rows[$context['challenge_id']]=$secret;file_put_contents($path,json_encode($rows,JSON_THROW_ON_ERROR),LOCK_EX);chmod($path,0600);
        return new Agenda\Contracts\OtpDeliveryResult(true,'synthetic',null);
    }
}
