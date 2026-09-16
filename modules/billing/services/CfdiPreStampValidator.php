<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;
require_once __DIR__.'/SatCfdiCatalog.php';
require_once __DIR__.'/SatIssuanceCatalog.php';
require_once __DIR__.'/ExactInvoiceMath.php';
require_once __DIR__.'/CfdiDraftXmlBuilder.php';

final class CfdiPreStampValidator
{
    public function __construct(private PDO $pdo,private SatCfdiCatalog $fiscal,private SatIssuanceCatalog $codes) {}
    public function inspect(string $doctorId,array $draft):array
    {
        $errors=[];
        $issuer=$this->one('SELECT * FROM billing_issuer_profiles WHERE doctor_id=? AND issuer_profile_id=? AND archived_at IS NULL',[$doctorId,$draft['issuer_profile_id']]);
        $receiver=$this->one('SELECT * FROM billing_patient_profiles WHERE doctor_id=? AND patient_id=? AND billing_profile_id=? AND archived_at IS NULL',[$doctorId,$draft['patient_id'],$draft['billing_profile_id']]);
        $link=$this->one('SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status=\'active\'',[$doctorId,$draft['patient_id']]);
        if (!$link)$errors[]=['field'=>'patient_id','code'=>'patient_scope_denied'];
        if (!$issuer)$errors[]=['field'=>'issuer_profile_id','code'=>'issuer_profile_not_found'];
        if (!$receiver)$errors[]=['field'=>'billing_profile_id','code'=>'billing_profile_not_found'];
        if ($issuer) {
            try{$this->fiscal->validateRegime($issuer['rfc'],$issuer['fiscal_regime_code']);}catch(\InvalidArgumentException $e){$errors[]=['field'=>'issuer_profile_id','code'=>$e->getMessage()];}
            $csd=$this->one('SELECT credential_id FROM billing_csd_credentials WHERE doctor_id=? AND issuer_profile_id=? AND certificate_rfc=? AND certificate_type=\'CSD_VERIFIED\' AND archived_at IS NULL AND valid_from<=UTC_TIMESTAMP() AND valid_to>UTC_TIMESTAMP() ORDER BY created_at DESC LIMIT 1',[$doctorId,$issuer['issuer_profile_id'],$issuer['rfc']]);
            if (!$csd)$errors[]=['field'=>'issuer_profile_id','code'=>'active_verified_csd_required'];
        }
        if($receiver)try{$this->fiscal->validateCombination($receiver['rfc'],$receiver['fiscal_regime_code'],$draft['cfdi_use_code']);}catch(\InvalidArgumentException $e){$errors[]=['field'=>'cfdi_use_code','code'=>$e->getMessage()];}
        foreach(['c_Moneda'=>'currency_code','c_MetodoPago'=>'payment_method_code','c_FormaPago'=>'payment_form_code'] as $group=>$field)
            try{$this->codes->requireCode($group,$draft[$field]);}catch(\InvalidArgumentException $e){$errors[]=['field'=>$field,'code'=>$e->getMessage()];}
        if($draft['payment_method_code']==='PPD' && $draft['payment_form_code']!=='99' || $draft['payment_method_code']==='PUE' && in_array($draft['payment_form_code'],['30','99'],true))$errors[]=['field'=>'payment_form_code','code'=>'payment_method_form_conflict'];
        if($draft['items']===[])$errors[]=['field'=>'items','code'=>'invoice_items_required'];
        $hasRate=false;
        foreach($draft['items'] as $i=>$item){
            foreach(['c_ClaveProdServ'=>'product_service_code','c_ClaveUnidad'=>'unit_code','c_ObjetoImp'=>'tax_object_code'] as $group=>$field)
                try{$this->codes->requireCode($group,$item[$field]);}catch(\InvalidArgumentException $e){$errors[]=['field'=>'items.'.$i.'.'.$field,'code'=>$e->getMessage()];}
            if($item['tax_object_code']==='01' && $item['taxes']!==[] || $item['tax_object_code']==='02' && $item['taxes']===[])$errors[]=['field'=>'items.'.$i.'.taxes','code'=>'tax_object_mismatch'];
            if(!in_array($item['tax_object_code'],['01','02'],true))$errors[]=['field'=>'items.'.$i.'.tax_object_code','code'=>'tax_object_rule_unverified'];
            foreach($item['taxes'] as $tax){
                try{$this->codes->requireCode('c_Impuesto',$tax['tax_code']);}catch(\InvalidArgumentException $e){$errors[]=['field'=>'items.'.$i.'.taxes','code'=>$e->getMessage()];}
                if($tax['factor_code']!=='Exento')$hasRate=true;
            }
        }
        if($hasRate)$errors[]=['field'=>'items.taxes','code'=>'tax_rate_catalog_unverified'];
        if($draft['items']!==[])$errors[]=['field'=>'items','code'=>'sat_catalog_effective_dates_unverified'];
        if($draft['items']!==[]){
            try {
                $calculated=ExactInvoiceMath::compute($draft['items']);
                foreach(['subtotal','discount','tax_total','total'] as $field)
                    if(bccomp($calculated[$field],(string)$draft[$field],6)!==0)$errors[]=['field'=>$field,'code'=>'invoice_amount_mismatch'];
                foreach($calculated['items'] as $i=>$item){
                    foreach(['line_subtotal','line_total'] as $field)
                        if(bccomp($item[$field],(string)$draft['items'][$i][$field],6)!==0)$errors[]=['field'=>'items.'.$i.'.'.$field,'code'=>'invoice_amount_mismatch'];
                    foreach($item['taxes'] as $j=>$tax){
                        foreach(['tax_base','amount'] as $field){
                            $stored=$draft['items'][$i]['taxes'][$j][$field];
                            if($tax[$field]===null ? $stored!==null : ($stored===null || bccomp($tax[$field],(string)$stored,6)!==0))
                                $errors[]=['field'=>'items.'.$i.'.taxes.'.$j.'.'.$field,'code'=>'invoice_amount_mismatch'];
                        }
                    }
                }
            } catch (\InvalidArgumentException $e) {$errors[]=['field'=>'items','code'=>'invoice_amount_invalid'];}
        }
        // Current SAT decimal/currency-specific rules need complete catalog evidence before a stamp.
        if($draft['currency_code']!=='MXN')$errors[]=['field'=>'currency_code','code'=>'currency_precision_catalog_unverified'];
        if(bccomp($draft['total'],'0',6)<0)$errors[]=['field'=>'total','code'=>'negative_invoice_total'];
        if($issuer && $receiver && $draft['items']!==[]){
            try{$xml=(new CfdiDraftXmlBuilder())->build($draft,$issuer,$receiver);}catch(\Throwable $e){$errors[]=['field'=>'cfdi','code'=>'cfdi_draft_generation_failed'];}
        }
        return ['valid'=>$errors===[],'errors'=>$errors,'issuer'=>$issuer,'receiver'=>$receiver,'xml_sha256'=>isset($xml)?hash('sha256',$xml):null,
            'pac_provider_selected'=>false,'certification_enabled'=>false];
    }
    private function one(string $sql,array $args):array|false { $q=$this->pdo->prepare($sql);$q->execute($args);return $q->fetch(PDO::FETCH_ASSOC); }
}
