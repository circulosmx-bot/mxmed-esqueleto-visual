<?php
declare(strict_types=1);
namespace Identity\Accreditation;
use Identity\Contracts\{IdentityNotificationPort,NotificationMessage,OneTimeTokenPurpose};

/** Adds only a validated navigation destination, never origin proof or authentication. */
final class EnrollmentNotificationAdapter implements IdentityNotificationPort
{
    public function __construct(private IdentityNotificationPort $inner,private PublicEnrollmentHttp $http){}
    public function send(NotificationMessage $message):void
    {
        if($message->purpose()===OneTimeTokenPurpose::EMAIL_VERIFICATION){
            $input=json_decode((string)file_get_contents('php://input'),true);
            $path=is_array($input)&&is_string($input['enrollment_return']??null)?$this->http->returnPath(['next'=>$input['enrollment_return']],$_COOKIE):null;
            if($path!==null)$message=new NotificationMessage($message->purpose(),$message->recipient(),$message->token(),$message->expiresAt(),$path);
        }
        $this->inner->send($message);
    }
}
