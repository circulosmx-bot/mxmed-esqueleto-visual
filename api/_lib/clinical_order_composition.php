<?php
declare(strict_types=1);

/** ORD-COMP01 orchestrates the existing canonical writer; never owns clinical identity. */
final class ClinicalOrderCompositionException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly ?string $group = null) { parent::__construct($message); }
}
function clinical_order_routing_config(): array
{
    static $config;
    return $config ??= json_decode(file_get_contents(__DIR__.'/../../modules/clinical/catalog/study_order_routing_v1.json'), true, 512, JSON_THROW_ON_ERROR);
}
/** Only this validated dental location is allowed to distinguish repeated canonical study items. */
function clinical_order_composition_identity(array $snapshot): string
{
    if ($snapshot['study_type_key'] === 'dental_occlusal_xray') {
        $location = $snapshot['dental_location'] ?? null;
        if (!is_array($location) || ($location['location_type'] ?? null) !== 'ARCH_LOCATION'
            || !in_array($location['arch_key'] ?? null, ['MAXILLARY','MANDIBULAR'], true)) {
            throw new InvalidArgumentException('DENTAL_OCCLUSAL_LOCATION_INVALID');
        }
        return 'id:'.$snapshot['study_type_id'].':ARCH:'.$location['arch_key'];
    }
    return 'id:'.$snapshot['study_type_id'];
}
function clinical_order_composition_create(PDO $pdo, string $doctor, string $patient, string $actor, array $command, string $key): array
{
    $uuid = $command['order_composition_batch_uuid'] ?? null;
    if (!is_string($uuid) || !clinical_study_valid_uuid($uuid) || $uuid !== $key) {
        throw new ClinicalOrderCompositionException('BATCH_UUID_KEY_MISMATCH');
    }
    if (array_diff(array_keys($command), ['order_composition_batch_uuid','order_routing_version','orders'])) {
        throw new ClinicalOrderCompositionException('BATCH_FIELDS_INVALID');
    }
    $orders=$command['orders']??null;
    if (!is_array($orders)||!array_is_list($orders)||!count($orders)||count($orders)>20) {
        throw new ClinicalOrderCompositionException('BATCH_ORDERS_INVALID');
    }
    // Stable child request hashes permit exact replay even if catalog configuration changes later.
    $childKey=fn(int $i):string=>'ordcomp01.order.'.hash('sha256',$uuid.':'.$i);
    $childHash=fn(int $i):string=>clinical_idempotency_request_hash(['command'=>'ORD_COMP01_ORDER','batch_uuid'=>$uuid,'order'=>$orders[$i]]);
    $repository=new ClinicalIdempotencyRepository($pdo);
    $fetch=function(int $firstId) use($pdo,$repository,$doctor,$patient,$uuid,$orders,$childKey,$childHash):array {
        $issued=[];
        foreach($orders as $i=>$order){
            $ref=$repository->replay('CREATE_ENCOUNTER_DOCUMENT',$doctor,'PATIENT',$patient,$childKey($i),$childHash($i));
            $document=clinical_v1_document_fetch($pdo,(int)$ref['document_id']);
            if($i===0&&(int)$document['document_id']!==$firstId)throw new RuntimeException('BATCH_REFERENCE_MISMATCH');
            $issued[]=$document+['order_routing_group_key'=>$order['order_routing_group_key']];
        }
        return ['order_composition_batch_uuid'=>$uuid,'orders'=>$issued];
    };
    clinical_encounter_integrity_assert_schema_ready($pdo);
    return (new ClinicalEncounterIntegrityService($pdo))->idempotentCreate(
        'CREATE_ENCOUNTER_DOCUMENT',$doctor,'PATIENT',$patient,'ordcomp01.batch.'.$uuid,
        ['command'=>'ORD_COMP01_BATCH','patient_id'=>$patient,'body'=>$command], 'document_id',$actor,
        function() use($pdo,$doctor,$patient,$actor,$command,$uuid,$orders,$repository,$childKey,$childHash):int {
            $config=clinical_order_routing_config();
            if(($command['order_routing_version']??null)!==$config['version'])throw new ClinicalOrderCompositionException('ROUTING_VERSION_INVALID');
            $prepared=[];$groups=[];$seen=[];$count=0;
            foreach($orders as $order){
                $group=is_array($order)&&is_string($order['order_routing_group_key']??null)?$order['order_routing_group_key']:'';
                try {
                    if(!isset($config['groups'][$group])||isset($groups[$group]))throw new InvalidArgumentException('ORDER_GROUP_INVALID_OR_DUPLICATE');
                    if(array_diff(array_keys($order),['order_routing_group_key','priority','indication','order_items','lab_preset_applications']))throw new InvalidArgumentException('ORDER_FIELDS_INVALID');
                    $groups[$group]=true;
                    $priority=$order['priority']??'Rutinaria';
                    if(!in_array($priority,['Rutinaria','Urgente'],true))throw new InvalidArgumentException('ORDER_PRIORITY_INVALID');
                    $indication=clinical_study_optional_text($order['indication']??null,2000,'ORDER_INDICATION_INVALID')??'';
                    $inputs=$order['order_items']??null;
                    if(!is_array($inputs)||!array_is_list($inputs)||count($inputs)>100)throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
                    $presetExpansion=clinical_lab_preset_expand($pdo,$inputs,$order['lab_preset_applications']??null,$group);
                    $inputs=$presetExpansion['items'];
                    if(!$inputs)throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
                    $snapshots=[];
                    foreach($inputs as $i=>$item){
                        if(!is_array($item))throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
                        if(array_diff(array_keys($item),['study_type_id','study_type_key','study_category','study_display_name','note','dental_location','dental_study_policy_version','dental_acquisition_protocol','specimen_collection_requirements','pathology_order_parameters','imaging_order_parameters','functional_order_parameters','lab_panel_request','lab_arterial_oxygen_context','custom_routing_confirmed']))throw new InvalidArgumentException('ORDER_ITEM_FIELDS_INVALID');
                        $snapshot=clinical_study_order_snapshot($pdo,$item,$i+1);
                        if($snapshot['study_type_key']!==null){
                            if(($config['studies'][$snapshot['study_type_key']]??null)!==$group)throw new InvalidArgumentException('STUDY_ROUTING_MISMATCH');
                            $identity=clinical_order_composition_identity($snapshot);
                        }else{
                            if(($item['custom_routing_confirmed']??false)!==true)throw new InvalidArgumentException('CUSTOM_ROUTING_CONFIRMATION_REQUIRED');
                            $identity='custom:'.$snapshot['study_category'].':'.mb_strtolower($snapshot['study_display_name']);
                        }
                        if(isset($seen[$identity]))throw new InvalidArgumentException('STUDY_DUPLICATE');
                        $seen[$identity]=true;$count++;$snapshots[]=$snapshot;
                    }
                    if($count>100)throw new InvalidArgumentException('BATCH_STUDY_LIMIT');
                    $categories=array_unique(array_column($snapshots,'study_category'));
                    $type=count($categories)===1&&$categories[0]==='LABORATORIO'?'lab_order':(count($categories)===1&&$categories[0]==='IMAGEN'?'imaging_order':'orders');
                    $prepared[]=['document_type'=>$type,'title'=>mb_substr(count($snapshots)===1?$snapshots[0]['study_display_name']:$config['groups'][$group].' ('.count($snapshots).')',0,128),
                        'summary'=>count($snapshots).' estudios · '.$priority,
                        'payload'=>['source'=>'ord_comp01','order_routing_group_key'=>$group,'order_routing_version'=>$config['version'],
                            'order_composition_batch_uuid'=>$uuid,'order_area'=>$config['groups'][$group],'priority'=>$priority,'indication'=>$indication,'order_items'=>$inputs,
                            ...($presetExpansion['applications'] ? ['lab_preset_applications'=>$presetExpansion['applications']] : [])]];
                }catch(InvalidArgumentException $e){throw new ClinicalOrderCompositionException($e->getMessage(),$group?:null);}
            }
            // All validation above, then all canonical writes and child ledger records in one transaction.
            $first=0;
            foreach($prepared as $i=>$payload){
                $claim=$repository->claim('CREATE_ENCOUNTER_DOCUMENT',$doctor,'PATIENT',$patient,$childKey($i),$childHash($i),$actor);
                try{$id=clinical_v1_document_insert($pdo,['patient_id'=>$patient,'doctor_id'=>$doctor],$payload,$actor);}
                catch(InvalidArgumentException $e){throw new ClinicalOrderCompositionException($e->getMessage(),$orders[$i]['order_routing_group_key']);}
                $repository->complete($claim,'document_id',$id);$first=$first?:$id;
            }
            return $first;
        }, $fetch);
}
