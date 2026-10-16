<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\NotificationMessage;
use Identity\Adapters\SesIdentityNotificationAdapter;
final class SesOperatorInvitationTransport implements OperatorInvitationTransport
{
    public function __construct(private SesIdentityNotificationAdapter $adapter){}
    public function deliver(NotificationMessage $message):string
    {
        // The shared adapter deliberately hides provider details. Unknown outcomes
        // cannot be retried safely (SES SendEmail has no idempotency token).
        try{return $this->adapter->sendWithReceipt($message);}catch(OperatorDeliveryFailure $e){throw $e;}catch(\Throwable){throw new OperatorDeliveryFailure('uncertain');}
    }
}
