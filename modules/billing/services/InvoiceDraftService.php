<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;
require_once __DIR__.'/IssuerProfileService.php';
require_once __DIR__.'/SatIssuanceCatalog.php';
require_once __DIR__.'/ExactInvoiceMath.php';
require_once __DIR__.'/BillingIssuanceAudit.php';
require_once __DIR__.'/InvoiceIssuanceState.php';
require_once __DIR__.'/../repositories/PatientBillingProfilesRepository.php';

final class InvoiceDraftService
{
    public function __construct(private PDO $pdo,private SatCfdiCatalog $fiscal,private SatIssuanceCatalog $codes) {}

    public function list(string $doctorId):array
    {
        $q=$this->pdo->prepare('SELECT d.draft_id,d.patient_id,p.display_name AS patient_display_name,d.issuer_profile_id,d.billing_profile_id,d.state,d.revision,d.total,d.updated_at FROM billing_invoice_drafts d JOIN patients_patients p ON p.patient_id=d.patient_id WHERE d.doctor_id=? ORDER BY d.updated_at DESC LIMIT 100');
        $q->execute([$doctorId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(string $doctorId,string $id):array
    {
        $q=$this->pdo->prepare('SELECT * FROM billing_invoice_drafts WHERE doctor_id=? AND draft_id=?');$q->execute([$doctorId,$id]);$draft=$q->fetch(PDO::FETCH_ASSOC);
        if (!$draft) throw new \DomainException('invoice_draft_not_found');
        $q=$this->pdo->prepare('SELECT * FROM billing_invoice_draft_items WHERE draft_id=? ORDER BY line_no');$q->execute([$id]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
        $taxQuery=$this->pdo->prepare('SELECT direction,tax_code,factor_code,rate,tax_base,amount FROM billing_invoice_draft_item_taxes WHERE item_id=? ORDER BY item_tax_id');
        foreach($items as &$item){$taxQuery->execute([$item['item_id']]);$item['taxes']=$taxQuery->fetchAll(PDO::FETCH_ASSOC);}unset($item);
        $draft['items']=$items;
        return $draft;
    }

    public function save(string $doctorId,array $input,?string $draftId=null):array
    {
        $isUpdate=$draftId!==null;
        $allowed=['patient_id','issuer_profile_id','billing_profile_id','currency_code','cfdi_use_code','payment_method_code','payment_form_code','series','internal_folio','items','revision'];
        if (array_diff(array_keys($input),$allowed)) throw new \InvalidArgumentException('invalid_draft_fields');
        foreach(['patient_id','issuer_profile_id','billing_profile_id','currency_code','cfdi_use_code','payment_method_code','payment_form_code'] as $key)
            if (!is_string($input[$key]??null)) throw new \InvalidArgumentException('invalid_draft_fields');
        foreach(['patient_id','issuer_profile_id','billing_profile_id'] as $key)
            if (!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$input[$key])) throw new \InvalidArgumentException('invalid_draft_identifier');
        $currency=strtoupper(trim($input['currency_code']));$use=strtoupper(trim($input['cfdi_use_code']));
        $method=strtoupper(trim($input['payment_method_code']));$form=trim($input['payment_form_code']);
        foreach(['c_Moneda'=>$currency,'c_MetodoPago'=>$method,'c_FormaPago'=>$form] as $group=>$code)$this->codes->requireCode($group,$code);
        if ($method==='PPD' && $form!=='99' || $method==='PUE' && in_array($form,['30','99'],true)) throw new \InvalidArgumentException('payment_method_form_conflict');
        if ($currency==='XXX' || $currency==='XTS') throw new \InvalidArgumentException('unsupported_invoice_currency');
        $series=$this->optional($input,'series',25);$folio=$this->optional($input,'internal_folio',40);
        if (!is_array($input['items']??null) || !array_is_list($input['items'])) throw new \InvalidArgumentException('invalid_invoice_items');
        $calculated=ExactInvoiceMath::compute($input['items']);
        foreach($calculated['items'] as $item){
            if (array_diff(array_keys($item),['product_service_code','description','quantity','unit_code','unit_value','discount','tax_object_code','taxes','line_subtotal','line_total'])) throw new \InvalidArgumentException('invalid_invoice_item');
            if (!is_string($item['product_service_code']??null)||!is_string($item['unit_code']??null)||!is_string($item['tax_object_code']??null)||!is_string($item['description']??null))throw new \InvalidArgumentException('invalid_invoice_item');
            $this->codes->requireCode('c_ClaveProdServ',$item['product_service_code']);$this->codes->requireCode('c_ClaveUnidad',$item['unit_code']);$this->codes->requireCode('c_ObjetoImp',$item['tax_object_code']);
            if (trim($item['description'])==='' || mb_strlen($item['description'])>1000 || preg_match('/[\x00-\x1f\x7f]/',$item['description']))throw new \InvalidArgumentException('invalid_item_description');
            if (($item['tax_object_code']==='01' && $item['taxes']!==[]) || ($item['tax_object_code']==='02' && $item['taxes']===[])) throw new \InvalidArgumentException('tax_object_mismatch');
            foreach($item['taxes'] as $tax) {
                if(!is_string($tax['tax_code']??null))throw new \InvalidArgumentException('invalid_invoice_tax');
                $this->codes->requireCode('c_Impuesto',$tax['tax_code']);
            }
        }
        $this->pdo->beginTransaction();
        try {
            (new \Billing\Repositories\PatientBillingProfilesRepository($this->pdo))->requireActiveLink($doctorId,$input['patient_id'],true);
            $issuer=(new IssuerProfileService($this->pdo,$this->fiscal))->get($doctorId,$input['issuer_profile_id']);
            $q=$this->pdo->prepare('SELECT * FROM billing_patient_profiles WHERE doctor_id=? AND patient_id=? AND billing_profile_id=? AND archived_at IS NULL');
            $q->execute([$doctorId,$input['patient_id'],$input['billing_profile_id']]);$receiver=$q->fetch(PDO::FETCH_ASSOC);
            if (!$receiver) throw new \DomainException('billing_profile_not_found');
            $this->fiscal->validateCombination($receiver['rfc'],$receiver['fiscal_regime_code'],$use);
            if ($draftId===null) {
                $draftId=IssuerProfileService::uuid();
                $q=$this->pdo->prepare('INSERT INTO billing_invoice_drafts (draft_id,doctor_id,patient_id,issuer_profile_id,billing_profile_id,currency_code,cfdi_use_code,payment_method_code,payment_form_code,series,internal_folio,state,revision,subtotal,discount,tax_total,total) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'DRAFT\',1,?,?,?,?)');
                $q->execute([$draftId,$doctorId,$input['patient_id'],$issuer['issuer_profile_id'],$receiver['billing_profile_id'],$currency,$use,$method,$form,$series,$folio,$calculated['subtotal'],$calculated['discount'],$calculated['tax_total'],$calculated['total']]);
            } else {
                $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $q=$this->pdo->prepare('SELECT * FROM billing_invoice_drafts WHERE doctor_id=? AND draft_id=?'.$lock);$q->execute([$doctorId,$draftId]);$existing=$q->fetch(PDO::FETCH_ASSOC);
                if (!$existing) throw new \DomainException('invoice_draft_not_found');
                if ($existing['state']!=='DRAFT'){
                    try{InvoiceIssuanceState::requireTransition($existing['state'],'DRAFT');}
                    catch(\DomainException $e){throw new \DomainException('invoice_draft_locked');}
                }
                if (!is_int($input['revision']??null) || $input['revision']!==(int)$existing['revision'])throw new \DomainException('draft_revision_conflict');
                $q=$this->pdo->prepare('DELETE t FROM billing_invoice_draft_item_taxes t JOIN billing_invoice_draft_items i ON i.item_id=t.item_id WHERE i.draft_id=?');$q->execute([$draftId]);
                $q=$this->pdo->prepare('DELETE FROM billing_invoice_draft_items WHERE draft_id=?');$q->execute([$draftId]);
                $q=$this->pdo->prepare('UPDATE billing_invoice_drafts SET patient_id=?,issuer_profile_id=?,billing_profile_id=?,currency_code=?,cfdi_use_code=?,payment_method_code=?,payment_form_code=?,series=?,internal_folio=?,state=\'DRAFT\',revision=revision+1,subtotal=?,discount=?,tax_total=?,total=? WHERE doctor_id=? AND draft_id=?');
                $q->execute([$input['patient_id'],$issuer['issuer_profile_id'],$receiver['billing_profile_id'],$currency,$use,$method,$form,$series,$folio,$calculated['subtotal'],$calculated['discount'],$calculated['tax_total'],$calculated['total'],$doctorId,$draftId]);
            }
            $itemStmt=$this->pdo->prepare('INSERT INTO billing_invoice_draft_items (item_id,draft_id,line_no,product_service_code,description,quantity,unit_code,unit_value,discount,line_subtotal,line_total,tax_object_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $taxStmt=$this->pdo->prepare('INSERT INTO billing_invoice_draft_item_taxes (item_tax_id,item_id,direction,tax_code,factor_code,rate,tax_base,amount) VALUES (?,?,?,?,?,?,?,?)');
            foreach($calculated['items'] as $position=>$item){$itemId=IssuerProfileService::uuid();$itemStmt->execute([$itemId,$draftId,$position+1,$item['product_service_code'],trim($item['description']),$item['quantity'],$item['unit_code'],$item['unit_value'],$item['discount'],$item['line_subtotal'],$item['line_total'],$item['tax_object_code']]);
                foreach($item['taxes'] as $tax)$taxStmt->execute([IssuerProfileService::uuid(),$itemId,$tax['direction'],$tax['tax_code'],$tax['factor_code'],$tax['rate'],$tax['tax_base'],$tax['amount']]);}
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,$isUpdate?'DRAFT_UPDATED':'DRAFT_CREATED','SUCCESS',$input['patient_id'],$draftId,$issuer['issuer_profile_id'],$receiver['billing_profile_id']);
            $this->pdo->commit();
        } catch (\Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return $this->get($doctorId,$draftId);
    }

    private function optional(array $input,string $key,int $max):?string
    {
        if (!array_key_exists($key,$input) || $input[$key]===null || $input[$key]==='')return null;
        if (!is_string($input[$key]))throw new \InvalidArgumentException('invalid_'.$key);
        $value=trim($input[$key]);if(mb_strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value))throw new \InvalidArgumentException('invalid_'.$key);
        return $value;
    }
}
