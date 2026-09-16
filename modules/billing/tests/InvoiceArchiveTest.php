<?php
declare(strict_types=1);

require_once __DIR__.'/../services/HistoricalCfdiParser.php';
require_once __DIR__.'/../services/InvoiceDocumentStorage.php';

use Billing\Services\HistoricalCfdiParser;
use Billing\Services\InvoiceDocumentStorage;

function check(bool $condition,string $label): void { if (!$condition) throw new RuntimeException($label); }
function rejects(callable $work,string $label): void {
    try {$work();} catch (InvalidArgumentException $e) {return;}
    throw new RuntimeException($label);
}
$directory=sys_get_temp_dir().'/mxmed-fisc02a-'.bin2hex(random_bytes(6));
mkdir($directory,0700);
$xmlPath=$directory.'/valid.xml';
$pdfPath=$directory.'/valid.pdf';
$xml='<?xml version="1.0" encoding="UTF-8"?>'
    .'<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" TipoDeComprobante="I" Fecha="2026-09-16T10:20:30" Moneda="MXN" SubTotal="100.00" Total="116.00" Serie="A" Folio="1">'
    .'<cfdi:Emisor Rfc="AAA010101AAA" Nombre="EMISOR QA" RegimenFiscal="601"/>'
    .'<cfdi:Receptor Rfc="BBB010101BBB" Nombre="RECEPTOR QA" DomicilioFiscalReceptor="20000" RegimenFiscalReceptor="601" UsoCFDI="G03"/>'
    .'<cfdi:Impuestos TotalImpuestosTrasladados="16.00"/>'
    .'<cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="123e4567-e89b-12d3-a456-426614174000"/></cfdi:Complemento></cfdi:Comprobante>';
file_put_contents($xmlPath,$xml);
$pdf="%PDF-1.4\n";
$objectOne=strlen($pdf);
$pdf.="1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objectTwo=strlen($pdf);
$pdf.="2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n";
$xref=strlen($pdf);
$pdf.="xref\n0 3\n0000000000 65535 f \n".sprintf('%010d 00000 n ',$objectOne)."\n".sprintf('%010d 00000 n ',$objectTwo)."\ntrailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
file_put_contents($pdfPath,$pdf);
try {
    $parser=new HistoricalCfdiParser();
    $row=$parser->parse($xmlPath);
    check($row['cfdi_uuid']==='123E4567-E89B-12D3-A456-426614174000','uuid');
    check($row['receiver_legal_name_snapshot']==='RECEPTOR QA','snapshot');
    check($row['tax_total']==='16.000000' && $row['total']==='116.000000','money');
    check($row['xml_sha256']===hash('sha256',$xml),'hash');
    $pdf=$parser->validatePdf($pdfPath);
    check($pdf['pdf_bytes']>0 && strlen($pdf['pdf_sha256'])===64,'pdf');
    file_put_contents($directory.'/bad.xml','<notCfdi/>');
    rejects(fn()=>$parser->parse($directory.'/bad.xml'),'non-cfdi rejected');
    file_put_contents($directory.'/bad.xml','<broken');
    rejects(fn()=>$parser->parse($directory.'/bad.xml'),'malformed rejected');
    file_put_contents($directory.'/bad.xml','<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><x>&x;</x>');
    rejects(fn()=>$parser->parse($directory.'/bad.xml'),'xxe rejected');
    file_put_contents($directory.'/bad.pdf','%PDF-1.4 malicious');
    rejects(fn()=>$parser->validatePdf($directory.'/bad.pdf'),'malformed pdf rejected');
    $storage=new InvoiceDocumentStorage($directory.'/private');
    $key=InvoiceDocumentStorage::key('doctor-qa','123e4567-e89b-42d3-a456-426614174000','xml');
    $storage->storeImmutable($key,$xmlPath,hash('sha256',$xml));
    check($storage->readVerified($key,hash('sha256',$xml),strlen($xml),HistoricalCfdiParser::MAX_XML_BYTES)===$xml,'private read');
    check((fileperms($directory.'/private/'.$key)&0077)===0,'private permissions');
    try {$storage->storeImmutable($key,$xmlPath,hash('sha256',$xml));throw new RuntimeException('overwrite accepted');} catch (RuntimeException $e) {if($e->getMessage()==='overwrite accepted')throw $e;}
    $storage->delete($key);
    echo "PASS parser, XXE, PDF, private storage\n";
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($directory);
}
