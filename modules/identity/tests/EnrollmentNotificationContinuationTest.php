<?php
declare(strict_types=1);
// Existing fake SES contract remains intact; no external client/provider call.
require_once __DIR__.'/SesIdentityNotificationAdapterTest.php';
$client=new Eotp02FakeSesClient();
$adapter=new Identity\Adapters\SesIdentityNotificationAdapter($client,'us-east-1','no-reply@mexicomedico.com','México Médico','https://mexicomedico.com');
$path='/public/identity/perfil-medico.php?application=00000000-0000-4000-8000-000000000001';
$token=Identity\Services\OneTimeTokenCodec::issue();
$adapter->send(new Identity\Contracts\NotificationMessage(Identity\Contracts\OneTimeTokenPurpose::EMAIL_VERIFICATION,'synthetic@example.invalid',$token,'2099-01-01 00:00:00',$path));
$body=$client->requests[0]['Content']['Simple']['Body'];
eotp02Assert(str_contains($body['Text']['Data'],'&next='.rawurlencode($path)),'exact bounded navigation continuation in text');
eotp02Assert(str_contains($body['Html']['Data'],'&amp;next='.rawurlencode($path)),'escaped exact continuation in HTML');
foreach(['https://untrusted.invalid/','//untrusted.invalid/','/private-app.php',$path.'&owner=true',$path.'#proof'] as $invalid){
    try{new Identity\Contracts\NotificationMessage(Identity\Contracts\OneTimeTokenPurpose::EMAIL_VERIFICATION,'synthetic@example.invalid',$token,'2099-01-01 00:00:00',$invalid);throw new RuntimeException('invalid_continuation_accepted');}
    catch(InvalidArgumentException $e){eotp02Assert($e->getMessage()==='invalid_notification_continuation','open redirect or authority payload rejected');}
}
try{new Identity\Contracts\NotificationMessage(Identity\Contracts\OneTimeTokenPurpose::PASSWORD_RECOVERY,'synthetic@example.invalid',$token,'2099-01-01 00:00:00',$path);throw new RuntimeException('recovery_continuation_accepted');}
catch(InvalidArgumentException $e){eotp02Assert($e->getMessage()==='invalid_notification_continuation','password reset contract unchanged');}
echo "EnrollmentNotificationContinuationTest PASS\n";
