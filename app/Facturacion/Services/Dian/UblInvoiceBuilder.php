<?php

namespace App\Facturacion\Services\Dian;

use DOMDocument;
use DOMElement;

/**
 * Constructor del XML UBL 2.1 de la Factura Electrónica de Venta (DIAN).
 *
 * Genera el documento con la estructura y namespaces que exige el Anexo Técnico:
 *   - UBLExtensions: DianExtensions (InvoiceControl, InvoiceSource, SoftwareProvider,
 *     SoftwareSecurityCode, QRCode) + hueco para la FIRMA (ds:Signature, la pone el firmador).
 *   - Cabecera: UBLVersionID 2.1, CustomizationID, ProfileID, ProfileExecutionID (1 prod/2 hab),
 *     ID (número), UUID = CUFE, IssueDate/Time, InvoiceTypeCode 01, DocumentCurrencyCode.
 *   - AccountingSupplierParty (emisor), AccountingCustomerParty (adquiriente).
 *   - TaxTotal, LegalMonetaryTotal, InvoiceLine[].
 *
 * NO firma: devuelve el XML listo para que el firmador XAdES inserte ds:Signature
 * dentro de ext:UBLExtensions. La firma es la siguiente pieza (XadesSigner).
 *
 * El `$doc` es un arreglo normalizado (lo arma un mapper desde FeDocumento + config).
 */
class UblInvoiceBuilder
{
    private const NS = [
        'inv'  => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cac'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'ext'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'sts'  => 'dian:gov:co:facturaelectronica:Structures-2-1',
        'ds'   => 'http://www.w3.org/2000/09/xmldsig#',
    ];

    private DOMDocument $dom;

    public function construir(array $doc): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = false;

        $inv = $this->dom->createElementNS(self::NS['inv'], 'Invoice');
        foreach (['cac', 'cbc', 'ext', 'sts', 'ds'] as $p) {
            $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $p, self::NS[$p]);
        }
        $this->dom->appendChild($inv);

        $this->extensiones($inv, $doc);
        $this->cabecera($inv, $doc);
        $this->emisor($inv, $doc['emisor']);
        $this->adquiriente($inv, $doc['adquiriente']);
        $this->impuestos($inv, $doc['totales']);
        $this->totales($inv, $doc['totales']);
        foreach ($doc['items'] as $i => $item) {
            $this->linea($inv, $item, $i + 1);
        }

        return $this->dom->saveXML();
    }

    // ─── UBLExtensions: DianExtensions + hueco de firma ───────────────────────
    private function extensiones(DOMElement $inv, array $doc): void
    {
        $exts = $this->el($inv, 'ext', 'UBLExtensions');

        // Extensión 1: DianExtensions
        $ext = $this->el($exts, 'ext', 'UBLExtension');
        $content = $this->el($ext, 'ext', 'ExtensionContent');
        $dian = $this->dom->createElementNS(self::NS['sts'], 'sts:DianExtensions');
        $content->appendChild($dian);

        $ctrl = $this->dom->createElementNS(self::NS['sts'], 'sts:InvoiceControl');
        $dian->appendChild($ctrl);
        $this->txtNs($ctrl, 'sts', 'InvoiceAuthorization', $doc['resolucion']['numero']);
        $period = $this->dom->createElementNS(self::NS['sts'], 'sts:AuthorizationPeriod');
        $ctrl->appendChild($period);
        $this->txt($period, 'cbc', 'StartDate', $doc['resolucion']['fecha_desde']);
        $this->txt($period, 'cbc', 'EndDate',   $doc['resolucion']['fecha_hasta']);
        $ranges = $this->dom->createElementNS(self::NS['sts'], 'sts:AuthorizedInvoices');
        $ctrl->appendChild($ranges);
        $this->txtNs($ranges, 'sts', 'Prefix', $doc['resolucion']['prefijo']);
        $this->txtNs($ranges, 'sts', 'From',   (string) $doc['resolucion']['desde']);
        $this->txtNs($ranges, 'sts', 'To',     (string) $doc['resolucion']['hasta']);

        $src = $this->dom->createElementNS(self::NS['sts'], 'sts:InvoiceSource');
        $dian->appendChild($src);
        $this->txtAttr($src, 'cbc', 'IdentificationCode', 'CO',
            ['listAgencyID' => '6', 'listAgencyName' => 'United Nations Economic Commission for Europe', 'listSchemeURI' => 'urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1']);

        $sw = $this->dom->createElementNS(self::NS['sts'], 'sts:SoftwareProvider');
        $dian->appendChild($sw);
        $this->txtAttr($sw, 'sts', 'ProviderID', $doc['emisor']['nit'],
            ['schemeAgencyID' => '195', 'schemeAgencyName' => 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)', 'schemeID' => $doc['emisor']['dv']]);
        $this->txtNs($sw, 'sts', 'SoftwareID', $doc['software']['id']);

        $this->txtNs($dian, 'sts', 'SoftwareSecurityCode', $doc['software']['security_code']);
        $qr = $this->dom->createElementNS(self::NS['sts'], 'sts:QRCode');
        $qr->appendChild($this->dom->createTextNode($doc['qr']));
        $dian->appendChild($qr);

        // Extensión 2: hueco para la firma (el firmador XAdES inserta ds:Signature aquí)
        $extFirma = $this->el($exts, 'ext', 'UBLExtension');
        $this->el($extFirma, 'ext', 'ExtensionContent'); // vacío a propósito
    }

    // ─── Cabecera ─────────────────────────────────────────────────────────────
    private function cabecera(DOMElement $inv, array $doc): void
    {
        $this->txt($inv, 'cbc', 'UBLVersionID', 'UBL 2.1');
        $this->txt($inv, 'cbc', 'CustomizationID', $doc['tipo_operacion'] ?? '10');
        $this->txt($inv, 'cbc', 'ProfileID', 'DIAN 2.1: Factura Electrónica de Venta');
        $this->txt($inv, 'cbc', 'ProfileExecutionID', $doc['ambiente']); // 1 prod, 2 hab
        $this->txt($inv, 'cbc', 'ID', $doc['numero']);
        $this->txtAttr($inv, 'cbc', 'UUID', $doc['cufe'],
            ['schemeID' => $doc['ambiente'], 'schemeName' => 'CUFE-SHA384']);
        $this->txt($inv, 'cbc', 'IssueDate', $doc['fecha']);
        $this->txt($inv, 'cbc', 'IssueTime', $doc['hora']);
        $this->txtAttr($inv, 'cbc', 'InvoiceTypeCode', '01', ['listAgencyID' => '195', 'listAgencyName' => 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)', 'listURI' => 'http://www.dian.gov.co/contratos/facturaelectronica/v1/InvoiceType']);
        $this->txtAttr($inv, 'cbc', 'DocumentCurrencyCode', $doc['moneda'] ?? 'COP', ['listAgencyID' => '6', 'listAgencyName' => 'United Nations Economic Commission for Europe', 'listID' => 'ISO 4217 Alpha']);
        $this->txt($inv, 'cbc', 'LineCountNumeric', (string) count($doc['items']));
    }

    // ─── Emisor ───────────────────────────────────────────────────────────────
    private function emisor(DOMElement $inv, array $e): void
    {
        $sup = $this->el($inv, 'cac', 'AccountingSupplierParty');
        $this->txt($sup, 'cbc', 'AdditionalAccountID', $e['tipo_persona']); // 1 jurídica, 2 natural
        $party = $this->el($sup, 'cac', 'Party');
        $this->nombreComercial($party, $e['nombre']);
        $legal = $this->el($party, 'cac', 'PartyTaxScheme');
        $this->txt($legal, 'cbc', 'RegistrationName', $e['nombre']);
        $this->txtAttr($legal, 'cbc', 'CompanyID', $e['nit'], ['schemeAgencyID' => '195', 'schemeName' => '31', 'schemeID' => $e['dv']]);
        $this->txt($legal, 'cbc', 'TaxLevelCode', $e['responsabilidades'] ?? 'O-13');
        $scheme = $this->el($legal, 'cac', 'TaxScheme');
        $this->txt($scheme, 'cbc', 'ID', '01');
        $this->txt($scheme, 'cbc', 'Name', 'IVA');
    }

    // ─── Adquiriente ──────────────────────────────────────────────────────────
    private function adquiriente(DOMElement $inv, array $a): void
    {
        $cus = $this->el($inv, 'cac', 'AccountingCustomerParty');
        $this->txt($cus, 'cbc', 'AdditionalAccountID', $a['tipo_persona'] ?? '2');
        $party = $this->el($cus, 'cac', 'Party');
        $this->nombreComercial($party, $a['nombre']);
        $legal = $this->el($party, 'cac', 'PartyTaxScheme');
        $this->txt($legal, 'cbc', 'RegistrationName', $a['nombre']);
        $this->txtAttr($legal, 'cbc', 'CompanyID', $a['documento'], ['schemeAgencyID' => '195', 'schemeName' => $a['tipo_documento'] ?? '13']);
        $scheme = $this->el($legal, 'cac', 'TaxScheme');
        $this->txt($scheme, 'cbc', 'ID', 'ZZ');
        $this->txt($scheme, 'cbc', 'Name', 'No aplica');
        if (!empty($a['email'])) {
            $contact = $this->el($party, 'cac', 'Contact');
            $this->txt($contact, 'cbc', 'ElectronicMail', $a['email']);
        }
    }

    private function nombreComercial(DOMElement $party, string $nombre): void
    {
        $pn = $this->el($party, 'cac', 'PartyName');
        $this->txt($pn, 'cbc', 'Name', $nombre);
    }

    // ─── Impuestos (TaxTotal) ─────────────────────────────────────────────────
    private function impuestos(DOMElement $inv, array $t): void
    {
        if (($t['impuestos'] ?? 0) <= 0) {
            return;
        }
        $tax = $this->el($inv, 'cac', 'TaxTotal');
        $this->txtAttr($tax, 'cbc', 'TaxAmount', $this->m($t['impuestos']), ['currencyID' => 'COP']);
        $sub = $this->el($tax, 'cac', 'TaxSubtotal');
        $this->txtAttr($sub, 'cbc', 'TaxableAmount', $this->m($t['subtotal']), ['currencyID' => 'COP']);
        $this->txtAttr($sub, 'cbc', 'TaxAmount', $this->m($t['impuestos']), ['currencyID' => 'COP']);
        $cat = $this->el($sub, 'cac', 'TaxCategory');
        // porcentaje efectivo (si es homogéneo); en multi-tarifa se detalla por línea
        $pct = $t['subtotal'] > 0 ? round($t['impuestos'] * 100 / $t['subtotal'], 2) : 0;
        $this->txt($cat, 'cbc', 'Percent', $this->m($pct));
        $scheme = $this->el($cat, 'cac', 'TaxScheme');
        $this->txt($scheme, 'cbc', 'ID', '01');
        $this->txt($scheme, 'cbc', 'Name', 'IVA');
    }

    // ─── Totales monetarios ───────────────────────────────────────────────────
    private function totales(DOMElement $inv, array $t): void
    {
        $lmt = $this->el($inv, 'cac', 'LegalMonetaryTotal');
        $this->txtAttr($lmt, 'cbc', 'LineExtensionAmount', $this->m($t['subtotal']), ['currencyID' => 'COP']);
        $this->txtAttr($lmt, 'cbc', 'TaxExclusiveAmount', $this->m($t['subtotal']), ['currencyID' => 'COP']);
        $this->txtAttr($lmt, 'cbc', 'TaxInclusiveAmount', $this->m($t['total']), ['currencyID' => 'COP']);
        $this->txtAttr($lmt, 'cbc', 'PayableAmount', $this->m($t['total']), ['currencyID' => 'COP']);
    }

    // ─── Línea de factura ─────────────────────────────────────────────────────
    private function linea(DOMElement $inv, array $it, int $num): void
    {
        $line = $this->el($inv, 'cac', 'InvoiceLine');
        $this->txt($line, 'cbc', 'ID', (string) $num);
        $this->txtAttr($line, 'cbc', 'InvoicedQuantity', $this->m($it['cantidad']), ['unitCode' => $it['unidad'] ?? '94']);
        $this->txtAttr($line, 'cbc', 'LineExtensionAmount', $this->m($it['base']), ['currencyID' => 'COP']);
        $item = $this->el($line, 'cac', 'Item');
        $this->txt($item, 'cbc', 'Description', $it['descripcion']);
        $ident = $this->el($item, 'cac', 'SellersItemIdentification');
        $this->txt($ident, 'cbc', 'ID', $it['codigo']);
        $price = $this->el($line, 'cac', 'Price');
        $this->txtAttr($price, 'cbc', 'PriceAmount', $this->m($it['precio_unitario']), ['currencyID' => 'COP']);
        $this->txtAttr($price, 'cbc', 'BaseQuantity', $this->m($it['cantidad']), ['unitCode' => $it['unidad'] ?? '94']);
    }

    // ─── Helpers DOM ──────────────────────────────────────────────────────────
    private function el(DOMElement $parent, string $ns, string $name): DOMElement
    {
        $e = $this->dom->createElementNS(self::NS[$ns], "$ns:$name");
        $parent->appendChild($e);
        return $e;
    }

    private function txt(DOMElement $parent, string $ns, string $name, string $value): DOMElement
    {
        $e = $this->dom->createElementNS(self::NS[$ns], "$ns:$name");
        $e->appendChild($this->dom->createTextNode($value));
        $parent->appendChild($e);
        return $e;
    }

    private function txtNs(DOMElement $parent, string $ns, string $name, string $value): DOMElement
    {
        return $this->txt($parent, $ns, $name, $value);
    }

    private function txtAttr(DOMElement $parent, string $ns, string $name, string $value, array $attrs): DOMElement
    {
        $e = $this->txt($parent, $ns, $name, $value);
        foreach ($attrs as $k => $v) {
            $e->setAttribute($k, (string) $v);
        }
        return $e;
    }

    private function m($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}
