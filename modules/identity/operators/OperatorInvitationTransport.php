<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\NotificationMessage;
interface OperatorInvitationTransport { public function deliver(NotificationMessage $message):string; }
