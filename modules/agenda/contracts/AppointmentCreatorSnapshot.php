<?php
declare(strict_types=1);
namespace Agenda\Contracts;
use Identity\Contracts\AuthenticatedAccessContext;

/** Server-created value object; never deserialized from HTTP/body metadata. */
final class AppointmentCreatorSnapshot
{
    private function __construct(public readonly array $values){}
    public static function fromCanonical(AuthenticatedAccessContext $actor,array $membership,?array $presentation,string $utc): self
    {
        if($membership['account_id']!==$actor->accountId()||$membership['status']!=='active')throw new \RuntimeException('appointment_creator_binding_invalid');
        $owner=$membership['role_code']==='owner';
        if($presentation!==null&&($presentation['membership_id']!==$membership['membership_id']||$presentation['profile_doctor_id']!==$membership['profile_doctor_id']||$presentation['released_at']!==null))throw new \RuntimeException('appointment_assignment_binding_invalid');
        $position=$owner||$presentation===null?null:(int)$presentation['seat_position'];
        return new self(['snapshot_version'=>1,'profile_doctor_id'=>$membership['profile_doctor_id'],'creator_account_id'=>$actor->accountId(),
            'creator_membership_id'=>$membership['membership_id'],'creator_presentation_id'=>$owner?null:($presentation['presentation_id']??null),
            'creator_alias'=>$owner?null:($presentation['alias_display']??null),'creator_position'=>$position,
            'origin_visual_key'=>$owner?'user':($position===null?'operator':'operator-0'.$position),'created_at_utc'=>$utc]);
    }
}
