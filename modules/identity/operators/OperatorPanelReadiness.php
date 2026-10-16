<?php
declare(strict_types=1);
namespace Identity\Operators;

use PDO;

/** Presentation decision from a validated server context. Never grants access or migrates data. */
final class OperatorPanelReadiness
{
    public const REQUIRED_SCHEMA = [
        'auth_profile_operator_invitations' => ['invitation_id','profile_doctor_id','owner_membership_id','email_normalized','role_code','offer_revision','offered_capabilities','offer_hash','token_hash','status','created_at','expires_at','closed_at','accepted_membership_id','pending_contact'],
        'auth_profile_operator_grant_revisions' => ['revision_id','membership_id','invitation_id','owner_membership_id','revision','status','valid_from','valid_until','created_at','current_membership'],
        'auth_profile_operator_grants' => ['revision_id','capability'],
        'auth_profile_operator_commands' => ['profile_doctor_id','actor_account_id','command_id','request_hash','result_json','created_at'],
    ];

    /** The caller has already composed/revalidated the canonical service and its snapshot. */
    public static function available(array $snapshot): array
    {
        return ['service_ready'=>true,'schema_ready'=>true,'can_manage'=>$snapshot['owner']===true,'can_configure_agenda'=>false,
            'status'=>'available','message'=>'','access_message'=>'','seat_limit'=>2,'legacy'=>null];
    }

    /** Only called after PrivateApplicationAccessResolver authorizes the medical context. */
    public static function normalReadOnly(PDO $pdo, array $context): array
    {
        $result=['service_ready'=>false,'schema_ready'=>false,'can_manage'=>false,'can_configure_agenda'=>true,
            'status'=>'schema_not_ready','seat_limit'=>2,
            'message'=>'Las invitaciones y los permisos de Identity todavía no están habilitados. Falta preparar el esquema canónico y autorizar su activación. Los registros anteriores se conservan para consulta.',
            'access_message'=>'La administración requiere autoridad canónica de titular. Tu membresía actual no se modifica.',
            'legacy'=>['read_status'=>'unavailable','records'=>[],'audit'=>[],'counts'=>null,'reconciliation_required'=>true]];
        try {
            // Inspect metadata only: never SELECT from an absent canonical table.
            $columns=[];
            foreach($pdo->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $row)
                $columns[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
            $ready=true;
            foreach(self::REQUIRED_SCHEMA as $table=>$required)
                if(array_diff($required,$columns[$table]??[])!==[])$ready=false;
            $result['schema_ready']=$ready;
            if($ready){
                // DDL presence is not permission to compose a real writer or send invitations.
                $result['status']='activation_pending';
                $result['message']='El esquema está disponible, pero el servicio de invitaciones y permisos sigue pendiente de una activación autorizada y de la conciliación de registros anteriores.';
            }
            $doctor=(string)($context['professional']['doctor_id']??'');
            if($doctor==='' || ($context['account']['id']??'')==='')return $result;
            $needed=['operator_id','doctor_id','alias','status','created_at'];
            if(array_diff($needed,$columns['agenda_operators']??[])!==[])return $result;
            $s=$pdo->prepare('SELECT operator_id,alias,status FROM agenda_operators WHERE doctor_id=? ORDER BY created_at,operator_id LIMIT 501');
            $s->execute([$doctor]);$records=$s->fetchAll(PDO::FETCH_ASSOC);
            // Never present a truncated inventory as a complete reconciliation.
            if(count($records)>500)return $result;
            $counts=['active'=>0,'paused'=>0,'pending'=>0,'archived'=>0];
            foreach($records as &$record){
                $record['source']='legacy_server';
                if(isset($counts[$record['status']]))$counts[$record['status']]++;
            }unset($record);
            $audit=[];
            if(array_diff(['event_id','doctor_id','operator_id','event_type','action_label','at'],$columns['agenda_operator_audit_events']??[])===[]){
                $s=$pdo->prepare('SELECT operator_id,event_type,action_label,at FROM agenda_operator_audit_events WHERE doctor_id=? ORDER BY at DESC,event_id DESC LIMIT 120');
                $s->execute([$doctor]);$audit=$s->fetchAll(PDO::FETCH_ASSOC);
            }
            $result['legacy']=['read_status'=>'ready','records'=>$records,'audit'=>$audit,'counts'=>$counts,
                'reconciliation_required'=>count($records)>0,'over_two_seats'=>array_sum(array_intersect_key($counts,array_flip(['active','paused','pending'])))>2];
        } catch(\Throwable) {
            // Do not break unrelated physician surfaces or imply an empty legacy inventory.
            $result['legacy']['read_status']='unavailable';
        }
        return $result;
    }
}
