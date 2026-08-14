<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\TrustedAuditContext;
final class TrustedAuditContextValidator
{
    public function assertTrusted(TrustedAuditContext $context): void { foreach(['actorIdentityId','actorType','actorRole','actorScope','requestId','correlationId','sourceModule','sourceRoute'] as $field){$value=$context->$field;if($value===''||preg_match('/[\x00-\x1f]/',$value))throw new \InvalidArgumentException('invalid_trusted_context:'.$field);} }
}
