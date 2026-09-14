<?php
declare(strict_types=1);
namespace Profiles\Services;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Complete doctor-owned draft. Credentials are intentionally not accepted. */
final class ProfessionalInformationService
{
    public const TYPES = ['CERTIFICATION','COURSE','DIPLOMA','MEMBERSHIP','SERVICE','DISEASE','TREATMENT'];
    public function __construct(private PDO $pdo) {}
    public static function emptyDraft(): array { return ['public_professional_summary'=>'','items'=>array_fill_keys(self::TYPES,[])]; }
    public static function validate(array $draft): array
    {
        $keys=array_keys($draft);sort($keys);
        if($keys!==['items','public_professional_summary'] || !is_string($draft['public_professional_summary']) || !is_array($draft['items'])) throw new InvalidArgumentException('Envía el borrador profesional completo.');
        $types=array_keys($draft['items']);sort($types);$expected=self::TYPES;sort($expected);
        if($types!==$expected) throw new InvalidArgumentException('Las categorías profesionales no son válidas.');
        $summary=trim(str_replace(["\r\n","\r"],"\n",$draft['public_professional_summary']));
        // No previous UI maxlength exists. TEXT's byte capacity is the storage boundary.
        if(!mb_check_encoding($summary,'UTF-8') || strlen($summary)>65535 || str_contains($summary,"\0")) throw new InvalidArgumentException('El texto profesional excede 65535 bytes o contiene caracteres inválidos.');
        $result=['public_professional_summary'=>$summary,'items'=>[]];
        foreach(self::TYPES as $type){
            $values=$draft['items'][$type];
            if(!is_array($values)||!array_is_list($values)||count($values)>($type==='SERVICE'?4:65535)) throw new InvalidArgumentException('La lista '.$type.' no es válida; servicios admite hasta 4.');
            $max=in_array($type,['DISEASE','TREATMENT'],true)?40:50;
            $result['items'][$type]=[];
            foreach($values as $value){
                if(!is_string($value)||!mb_check_encoding($value,'UTF-8')) throw new InvalidArgumentException('Valor profesional inválido.');
                $value=trim($value);
                if($value===''||mb_strlen($value,'UTF-8')>$max||str_contains($value,"\0")) throw new InvalidArgumentException($type.' admite valores de 1 a '.$max.' caracteres.');
                // Existing add-item semantics allow duplicates; order is array position.
                $result['items'][$type][]=$value;
            }
        }
        return $result;
    }
    public function current(string $doctor): array
    {
        // One query guarantees a consistent summary/items snapshot during replacement.
        $q=$this->pdo->prepare('SELECT p.public_professional_summary,i.item_type,i.value FROM profiles_doctor_professional_information p LEFT JOIN profiles_doctor_professional_items i ON i.doctor_id=p.doctor_id WHERE p.doctor_id=? ORDER BY i.item_type,i.sort_order');
        $q->execute([$doctor]);$result=self::emptyDraft();
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $result['public_professional_summary']=$row['public_professional_summary'];
            if($row['item_type']!==null)$result['items'][$row['item_type']][]=$row['value'];
        }
        return $result;
    }
    public function save(string $doctor,array $draft): array
    {
        $draft=self::validate($draft);
        if($this->pdo->inTransaction())throw new RuntimeException('professional_information_nested_transaction');
        $this->pdo->beginTransaction();
        try{
            // Stable parent lock serializes first creation and all later complete saves.
            $lock=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$lock->execute([$doctor]);
            if($lock->fetchColumn()===false)throw new RuntimeException('professional_information_doctor_unavailable');
            $q=$this->pdo->prepare('INSERT INTO profiles_doctor_professional_information (doctor_id,public_professional_summary) VALUES (?,?) ON DUPLICATE KEY UPDATE public_professional_summary=VALUES(public_professional_summary),updated_at=CURRENT_TIMESTAMP');
            $q->execute([$doctor,$draft['public_professional_summary']]);
            $q=$this->pdo->prepare('DELETE FROM profiles_doctor_professional_items WHERE doctor_id=?');$q->execute([$doctor]);
            $q=$this->pdo->prepare('INSERT INTO profiles_doctor_professional_items (doctor_id,item_type,value,sort_order) VALUES (?,?,?,?)');
            foreach($draft['items'] as $type=>$values)foreach($values as $order=>$value)$q->execute([$doctor,$type,$value,$order]);
            $this->pdo->commit();return $draft;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
