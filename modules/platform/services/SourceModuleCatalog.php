<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\CanonicalAuditEventType;
final class SourceModuleCatalog
{
    private const MODULES=['AUTH','SESSION','PROFILE','OWNERSHIP','INVITATION','ROLE','SECURITY','ADMIN','SYSTEM'];
    private const EVENT_MAP=['AUTH_REGISTRATION_REQUESTED'=>'AUTH','AUTH_EMAIL_VERIFICATION_SENT'=>'AUTH','AUTH_EMAIL_VERIFIED'=>'AUTH','AUTH_LOGIN_SUCCEEDED'=>'AUTH','AUTH_LOGIN_FAILED'=>'AUTH','AUTH_PASSWORD_RECOVERY_REQUESTED'=>'AUTH','AUTH_PASSWORD_RESET_SUCCEEDED'=>'AUTH','AUTH_PASSWORD_CHANGED'=>'AUTH','AUTH_SESSION_CREATED'=>'SESSION','AUTH_SESSION_ROTATED'=>'SESSION','AUTH_SESSION_REVOKED'=>'SESSION','AUTH_LOGOUT'=>'SESSION','AUTH_LOGOUT_ALL'=>'SESSION','PROFILE_CLAIM_REQUESTED'=>'PROFILE','PROFILE_CLAIM_APPROVED'=>'PROFILE','PROFILE_CLAIM_REJECTED'=>'PROFILE','PROFILE_OWNERSHIP_ASSIGNED'=>'OWNERSHIP','PROFILE_OWNERSHIP_TRANSFERRED'=>'OWNERSHIP','INVITATION_CREATED'=>'INVITATION','INVITATION_ACCEPTED'=>'INVITATION','INVITATION_REVOKED'=>'INVITATION','ROLE_ASSIGNED'=>'ROLE','ROLE_REVOKED'=>'ROLE','STEP_UP_CHALLENGE_SUCCEEDED'=>'SECURITY','STEP_UP_CHALLENGE_FAILED'=>'SECURITY','BREAK_GLASS_STARTED'=>'SECURITY','BREAK_GLASS_ENDED'=>'SECURITY','SENSITIVE_ADMIN_ACTION'=>'ADMIN'];
    public function __construct(){if(count(self::MODULES)!==9||count(self::EVENT_MAP)!==28||array_keys(self::EVENT_MAP)!==CanonicalAuditEventType::all())throw new \LogicException('invalid_source_module_catalog');}
    public function all():array{return self::MODULES;} public function eventMap():array{return self::EVENT_MAP;}
    public function moduleForEvent(string $eventType):string{CanonicalAuditEventType::assertKnown($eventType);return self::EVENT_MAP[$eventType];}
    public function assertKnown(string $module):void{if(!in_array($module,self::MODULES,true))throw new \InvalidArgumentException('unknown_source_module');}
}
