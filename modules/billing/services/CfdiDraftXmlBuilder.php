<?php
declare(strict_types=1);
namespace Billing\Services;

/** Creates an unsigned CFDI 4.0 payload for local preview/validation only. */
final class CfdiDraftXmlBuilder
{
    public function build(array $draft,array $issuer,array $receiver):string
    {
        $dom=new \DOMDocument('1.0','UTF-8');$dom->formatOutput=false;
        $root=$dom->createElementNS('http://www.sat.gob.mx/cfd/4','cfdi:Comprobante');$dom->appendChild($root);
        $root->setAttribute('Version','4.0');$root->setAttribute('Fecha',str_replace(' ','T',substr((string)$draft['updated_at'],0,19)));
        $root->setAttribute('SubTotal',$this->money($draft['subtotal']));
        if (bccomp($draft['discount'],'0',6)>0)$root->setAttribute('Descuento',$this->money($draft['discount']));
        $root->setAttribute('Moneda',$draft['currency_code']);$root->setAttribute('Total',$this->money($draft['total']));
        $root->setAttribute('TipoDeComprobante','I');$root->setAttribute('Exportacion','01');
        $root->setAttribute('LugarExpedicion',$issuer['expedition_postal_code']);
        $root->setAttribute('MetodoPago',$draft['payment_method_code']);$root->setAttribute('FormaPago',$draft['payment_form_code']);
        if ($draft['series']!==null)$root->setAttribute('Serie',$draft['series']);
        if ($draft['internal_folio']!==null)$root->setAttribute('Folio',$draft['internal_folio']);
        $emisor=$dom->createElementNS($root->namespaceURI,'cfdi:Emisor');$root->appendChild($emisor);
        $emisor->setAttribute('Rfc',$issuer['rfc']);$emisor->setAttribute('Nombre',$issuer['issuer_legal_name']);$emisor->setAttribute('RegimenFiscal',$issuer['fiscal_regime_code']);
        $receptor=$dom->createElementNS($root->namespaceURI,'cfdi:Receptor');$root->appendChild($receptor);
        $receptor->setAttribute('Rfc',$receiver['rfc']);$receptor->setAttribute('Nombre',$receiver['receiver_legal_name']);
        $receptor->setAttribute('DomicilioFiscalReceptor',$receiver['fiscal_zip_code']);$receptor->setAttribute('RegimenFiscalReceptor',$receiver['fiscal_regime_code']);
        $receptor->setAttribute('UsoCFDI',$draft['cfdi_use_code']);
        $concepts=$dom->createElementNS($root->namespaceURI,'cfdi:Conceptos');$root->appendChild($concepts);
        foreach($draft['items'] as $item){
            $concept=$dom->createElementNS($root->namespaceURI,'cfdi:Concepto');$concepts->appendChild($concept);
            $concept->setAttribute('ClaveProdServ',$item['product_service_code']);$concept->setAttribute('Cantidad',$item['quantity']);
            $concept->setAttribute('ClaveUnidad',$item['unit_code']);$concept->setAttribute('Descripcion',$item['description']);
            $concept->setAttribute('ValorUnitario',$item['unit_value']);$concept->setAttribute('Importe',$item['line_subtotal']);
            $concept->setAttribute('ObjetoImp',$item['tax_object_code']);
            if (bccomp($item['discount'],'0',6)>0)$concept->setAttribute('Descuento',$item['discount']);
            if ($item['taxes']!==[]) {
                $impuestos=$dom->createElementNS($root->namespaceURI,'cfdi:Impuestos');$concept->appendChild($impuestos);
                foreach(['TRANSFER'=>'cfdi:Traslados','WITHHOLD'=>'cfdi:Retenciones'] as $direction=>$containerName){
                    $taxes=array_values(array_filter($item['taxes'],static fn($tax)=>$tax['direction']===$direction));
                    if ($taxes===[])continue;
                    $container=$dom->createElementNS($root->namespaceURI,$containerName);$impuestos->appendChild($container);
                    foreach($taxes as $tax){$node=$dom->createElementNS($root->namespaceURI,$direction==='TRANSFER'?'cfdi:Traslado':'cfdi:Retencion');$container->appendChild($node);
                        $node->setAttribute('Base',$tax['tax_base']);$node->setAttribute('Impuesto',$tax['tax_code']);$node->setAttribute('TipoFactor',$tax['factor_code']);
                        if($tax['rate']!==null)$node->setAttribute('TasaOCuota',$tax['rate']);if($tax['amount']!==null)$node->setAttribute('Importe',$tax['amount']);}
                }
            }
        }
        $xml=$dom->saveXML();if($xml===false)throw new \RuntimeException('cfdi_draft_generation_failed');return $xml;
    }
    private function money(string $value):string { return \Billing\Services\ExactInvoiceMath::round($value,2); }
}
