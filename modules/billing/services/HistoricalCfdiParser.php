<?php
declare(strict_types=1);
namespace Billing\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

final class HistoricalCfdiParser
{
    public const MAX_XML_BYTES = 5242880;
    public const MAX_PDF_BYTES = 5242880;
    private const CFDI_NS = 'http://www.sat.gob.mx/cfd/4';
    private const TIMBRE_NS = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    public function parse(string $path): array
    {
        $bytes = $this->readBounded($path, self::MAX_XML_BYTES);
        if (str_contains($bytes, "\0") || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $bytes)) {
            throw new InvalidArgumentException('invalid_cfdi_xml');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['application/xml', 'text/xml', 'text/plain'], true)) {
            throw new InvalidArgumentException('invalid_cfdi_xml');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument();
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;
            if (!$doc->loadXML($bytes, LIBXML_NONET) || $doc->doctype !== null) {
                throw new InvalidArgumentException('invalid_cfdi_xml');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $doc->documentElement;
        if (!$root instanceof DOMElement || $root->namespaceURI !== self::CFDI_NS || $root->localName !== 'Comprobante'
            || $root->getAttribute('Version') !== '4.0' || $root->getAttribute('TipoDeComprobante') !== 'I') {
            throw new InvalidArgumentException('unsupported_cfdi');
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cfdi', self::CFDI_NS);
        $xpath->registerNamespace('tfd', self::TIMBRE_NS);
        $receivers = $xpath->query('./cfdi:Receptor', $root);
        $timbres = $xpath->query('./cfdi:Complemento/tfd:TimbreFiscalDigital', $root);
        if ($receivers === false || $receivers->length !== 1 || $timbres === false || $timbres->length !== 1) {
            throw new InvalidArgumentException('invalid_cfdi_xml');
        }
        /** @var DOMElement $receiver */
        $receiver = $receivers->item(0);
        /** @var DOMElement $timbre */
        $timbre = $timbres->item(0);
        if ($timbre->getAttribute('Version') !== '1.1') throw new InvalidArgumentException('invalid_cfdi_timbre');
        $uuid = strtoupper(trim($timbre->getAttribute('UUID')));
        if (!preg_match('/^[0-9A-F]{8}-(?:[0-9A-F]{4}-){3}[0-9A-F]{12}$/D', $uuid)) {
            throw new InvalidArgumentException('invalid_cfdi_uuid');
        }
        $issued = $root->getAttribute('Fecha');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $issued);
        if (!$date || $date->format('Y-m-d\TH:i:s') !== $issued) throw new InvalidArgumentException('invalid_cfdi_date');
        $currency = strtoupper(trim($root->getAttribute('Moneda')));
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) throw new InvalidArgumentException('invalid_cfdi_currency');
        $name = trim($receiver->getAttribute('Nombre'));
        $rfc = strtoupper(trim($receiver->getAttribute('Rfc')));
        $zip = trim($receiver->getAttribute('DomicilioFiscalReceptor'));
        $regime = trim($receiver->getAttribute('RegimenFiscalReceptor'));
        $use = strtoupper(trim($receiver->getAttribute('UsoCFDI')));
        if ($name === '' || mb_strlen($name) > 254 || preg_match('/[\x00-\x1F\x7F]/', $name)
            || !preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/uD', $rfc)
            || !in_array(mb_strlen($rfc), [12, 13], true)
            || !preg_match('/^[0-9]{5}$/D', $zip)
            || !preg_match('/^[0-9]{3}$/D', $regime)
            || !preg_match('/^[A-Z0-9]{3,4}$/D', $use)) {
            throw new InvalidArgumentException('invalid_cfdi_receiver');
        }
        $series = $this->optionalText($root->getAttribute('Serie'), 25);
        $folio = $this->optionalText($root->getAttribute('Folio'), 40);
        $taxNodes = $xpath->query('./cfdi:Impuestos', $root);
        if ($taxNodes === false || $taxNodes->length > 1) throw new InvalidArgumentException('invalid_cfdi_taxes');
        $taxNode = $taxNodes->length === 1 ? $taxNodes->item(0) : null;
        $transferred = $taxNode instanceof DOMElement ? $this->money($taxNode->getAttribute('TotalImpuestosTrasladados'), false) : null;
        $withheld = $taxNode instanceof DOMElement ? $this->money($taxNode->getAttribute('TotalImpuestosRetenidos'), false) : null;
        $taxMicro = ($transferred['micro'] ?? 0) - ($withheld['micro'] ?? 0);
        return [
            'cfdi_version'=>'4.0', 'cfdi_uuid'=>$uuid, 'series'=>$series, 'folio'=>$folio,
            'issued_at'=>$date->format('Y-m-d H:i:s'), 'currency_code'=>$currency,
            'subtotal'=>$this->money($root->getAttribute('SubTotal'), true)['decimal'],
            'discount'=>$this->money($root->getAttribute('Descuento'), false)['decimal'] ?? null,
            'tax_total'=>$this->formatMicro($taxMicro),
            'total'=>$this->money($root->getAttribute('Total'), true)['decimal'],
            'receiver_legal_name_snapshot'=>$name, 'receiver_rfc_snapshot'=>$rfc,
            'receiver_fiscal_zip_snapshot'=>$zip, 'receiver_regime_code_snapshot'=>$regime,
            'cfdi_use_code_snapshot'=>$use, 'xml_sha256'=>hash('sha256', $bytes), 'xml_bytes'=>strlen($bytes),
        ];
    }

    public function validatePdf(?string $path): ?array
    {
        if ($path === null) return null;
        $bytes = $this->readBounded($path, self::MAX_PDF_BYTES);
        if (!preg_match('/^%PDF-(?:1\.[0-7]|2\.0)\R/', $bytes)
            || !preg_match('/startxref\s+([0-9]+)\s+%%EOF\s*$/D', $bytes, $match)
            || !preg_match('#/Type\s*/Catalog\b#', $bytes)
            || !preg_match('#/Root\s+[0-9]+\s+[0-9]+\s+R\b#', $bytes)
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf') {
            throw new InvalidArgumentException('invalid_invoice_pdf');
        }
        $offset = (int)$match[1];
        $xref = substr($bytes, $offset, 2048);
        if ($offset < 8 || $offset >= strlen($bytes)
            || (!str_starts_with($xref, 'xref')
                && !(preg_match('/^[0-9]+\s+[0-9]+\s+obj\b/', $xref) && preg_match('#/Type\s*/XRef\b#', $xref)))) {
            throw new InvalidArgumentException('invalid_invoice_pdf');
        }
        return ['pdf_sha256'=>hash('sha256', $bytes), 'pdf_bytes'=>strlen($bytes)];
    }

    private function readBounded(string $path, int $limit): string
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) === false || filesize($path) < 1 || filesize($path) > $limit) {
            throw new InvalidArgumentException('invoice_document_size_invalid');
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) < 1 || strlen($bytes) > $limit) {
            throw new InvalidArgumentException('invoice_document_size_invalid');
        }
        return $bytes;
    }

    private function optionalText(string $value, int $limit): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new InvalidArgumentException('invalid_cfdi_xml');
        return $value;
    }

    private function money(string $value, bool $required): ?array
    {
        if ($value === '' && !$required) return null;
        if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D', $value)) {
            throw new InvalidArgumentException('invalid_cfdi_amount');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $micro = ((int)$whole * 1000000) + (int)str_pad($fraction, 6, '0');
        return ['micro'=>$micro, 'decimal'=>$this->formatMicro($micro)];
    }

    private function formatMicro(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);
        return $sign.intdiv($absolute, 1000000).'.'.str_pad((string)($absolute % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
