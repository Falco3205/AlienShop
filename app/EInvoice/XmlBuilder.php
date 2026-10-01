<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class XmlBuilder
{
    public static function build(array $d): string
    {
        $x = static fn(string $s) => Fiscal::xml($s);
        $f = static fn($v, int $dec = 2) => Fiscal::amount($v, $dec);
        $s = $d['seller'];
        $c = $d['customer'];

        $anag = static function (array $p) use ($x): string {
            if (($p['type'] ?? 'company') === 'private') {
                $parts = preg_split('/\s+/', trim($p['name'])) ?: [''];
                $first = array_shift($parts);
                $last = implode(' ', $parts) ?: $first;
                return '<Anagrafica><Nome>' . $x(Fiscal::latin($first, 60)) . '</Nome><Cognome>' . $x(Fiscal::latin($last, 60)) . '</Cognome></Anagrafica>';
            }
            return '<Anagrafica><Denominazione>' . $x(Fiscal::latin($p['name'], 80)) . '</Denominazione></Anagrafica>';
        };
        $sede = static fn(array $p) => '<Sede><Indirizzo>' . $x(Fiscal::latin($p['address'], 60)) . '</Indirizzo><CAP>' . $x(($p['country'] ?? 'IT') === 'IT' ? str_pad(preg_replace('/\D/', '', $p['cap']) ?? '', 5, '0', STR_PAD_LEFT) : '00000') . '</CAP><Comune>' . $x(Fiscal::latin($p['city'], 60)) . '</Comune>'
            . (($p['country'] ?? 'IT') === 'IT' && $p['prov'] !== '' ? '<Provincia>' . $x(strtoupper(substr($p['prov'], 0, 2))) . '</Provincia>' : '')
            . '<Nazione>' . $x($p['country'] ?? 'IT') . '</Nazione></Sede>';

        $sellerVat = preg_replace('/\D/', '', $s['vat']) ?? '';
        $tel = preg_match('/^[+0-9 ]{5,12}$/', $s['phone']) ? $s['phone'] : '';
        $mail = strlen($s['email']) >= 7 ? $s['email'] : '';
        $recipient = $c['sdi'] !== '' ? strtoupper($c['sdi']) : (($c['country'] ?? 'IT') !== 'IT' ? 'XXXXXXX' : '0000000');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:FatturaElettronica versione="FPR12" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2 http://www.fatturapa.gov.it/export/fatturazione/sdi/fatturapa/v1.2/Schema_del_file_xml_FatturaPA_versione_1.2.xsd">'
            . '<FatturaElettronicaHeader><DatiTrasmissione><IdTrasmittente><IdPaese>IT</IdPaese><IdCodice>' . $x($s['cf'] !== '' ? $s['cf'] : $sellerVat) . '</IdCodice></IdTrasmittente>'
            . '<ProgressivoInvio>' . $x($d['progressivo']) . '</ProgressivoInvio><FormatoTrasmissione>FPR12</FormatoTrasmissione><CodiceDestinatario>' . $x($recipient) . '</CodiceDestinatario>'
            . ($c['pec'] !== '' && $recipient === '0000000' ? '<PECDestinatario>' . $x($c['pec']) . '</PECDestinatario>' : '')
            . '</DatiTrasmissione>'
            . '<CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>' . $x($sellerVat) . '</IdCodice></IdFiscaleIVA>'
            . ($s['cf'] !== '' ? '<CodiceFiscale>' . $x($s['cf']) . '</CodiceFiscale>' : '')
            . $anag(['type' => $s['type'] ?? 'company', 'name' => $s['name']]) . '<RegimeFiscale>' . $x($s['regime']) . '</RegimeFiscale></DatiAnagrafici>'
            . $sede($s + ['country' => 'IT'])
            . ($s['rea_office'] !== '' && $s['rea_number'] !== '' ? '<IscrizioneREA><Ufficio>' . $x(strtoupper($s['rea_office'])) . '</Ufficio><NumeroREA>' . $x($s['rea_number']) . '</NumeroREA><StatoLiquidazione>LN</StatoLiquidazione></IscrizioneREA>' : '')
            . ($tel !== '' || $mail !== '' ? '<Contatti>' . ($tel !== '' ? '<Telefono>' . $x($tel) . '</Telefono>' : '') . ($mail !== '' ? '<Email>' . $x($mail) . '</Email>' : '') . '</Contatti>' : '')
            . '</CedentePrestatore>'
            . '<CessionarioCommittente><DatiAnagrafici>';
        $cvat = preg_replace('/\D/', '', $c['vat']) ?? '';
        if ($cvat !== '') {
            $xml .= '<IdFiscaleIVA><IdPaese>' . $x($c['country'] ?? 'IT') . '</IdPaese><IdCodice>' . $x($c['vat']) . '</IdCodice></IdFiscaleIVA>';
        } elseif (($c['country'] ?? 'IT') !== 'IT') {
            $xml .= '<IdFiscaleIVA><IdPaese>' . $x($c['country']) . '</IdPaese><IdCodice>99999999999</IdCodice></IdFiscaleIVA>';
        }
        if ($c['cf'] !== '') {
            $xml .= '<CodiceFiscale>' . $x(strtoupper($c['cf'])) . '</CodiceFiscale>';
        }
        $xml .= $anag($c) . '</DatiAnagrafici>' . $sede($c) . '</CessionarioCommittente></FatturaElettronicaHeader>';

        $xml .= '<FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>' . $x($d['type']) . '</TipoDocumento><Divisa>' . $x($d['currency']) . '</Divisa>'
            . '<Data>' . $x($d['date']) . '</Data><Numero>' . $x($d['number']) . '</Numero>'
            . (!empty($d['bollo']) ? '<DatiBollo><BolloVirtuale>SI</BolloVirtuale><ImportoBollo>2.00</ImportoBollo></DatiBollo>' : '')
            . '<ImportoTotaleDocumento>' . $f($d['total']) . '</ImportoTotaleDocumento>'
            . (($d['causale'] ?? '') !== '' ? '<Causale>' . $x(Fiscal::latin($d['causale'], 200)) . '</Causale>' : '')
            . '</DatiGeneraliDocumento>'
            . (!empty($d['related']) ? '<DatiFattureCollegate><IdDocumento>' . $x($d['related']['number']) . '</IdDocumento><Data>' . $x($d['related']['date']) . '</Data></DatiFattureCollegate>' : '')
            . '</DatiGenerali><DatiBeniServizi>';
        foreach ($d['lines'] as $i => $l) {
            $xml .= '<DettaglioLinee><NumeroLinea>' . ($i + 1) . '</NumeroLinea><Descrizione>' . $x(Fiscal::latin($l['desc'], 1000)) . '</Descrizione>'
                . '<Quantita>' . $f($l['qty'], 2) . '</Quantita><PrezzoUnitario>' . $f($l['unit'], 8) . '</PrezzoUnitario><PrezzoTotale>' . $f($l['total'], 8) . '</PrezzoTotale>'
                . '<AliquotaIVA>' . $f($d['rate']) . '</AliquotaIVA>' . ($d['rate'] == 0 ? '<Natura>' . $x($d['nature']) . '</Natura>' : '') . '</DettaglioLinee>';
        }
        $xml .= '<DatiRiepilogo><AliquotaIVA>' . $f($d['rate']) . '</AliquotaIVA>' . ($d['rate'] == 0 ? '<Natura>' . $x($d['nature']) . '</Natura>' : '')
            . (abs($d['rounding']) >= 0.005 ? '<Arrotondamento>' . $f($d['rounding'], 8) . '</Arrotondamento>' : '')
            . '<ImponibileImporto>' . $f($d['net']) . '</ImponibileImporto><Imposta>' . $f($d['vat']) . '</Imposta><EsigibilitaIVA>I</EsigibilitaIVA></DatiRiepilogo></DatiBeniServizi>'
            . '<DatiPagamento><CondizioniPagamento>TP02</CondizioniPagamento><DettaglioPagamento><ModalitaPagamento>' . $x($d['payment']['mode']) . '</ModalitaPagamento>'
            . (!empty($d['payment']['due']) ? '<DataScadenzaPagamento>' . $x($d['payment']['due']) . '</DataScadenzaPagamento>' : '')
            . '<ImportoPagamento>' . $f($d['total']) . '</ImportoPagamento>'
            . (!empty($d['payment']['iban']) ? '<IBAN>' . $x(strtoupper(preg_replace('/\s+/', '', $d['payment']['iban']) ?? '')) . '</IBAN>' : '')
            . '</DettaglioPagamento></DatiPagamento></FatturaElettronicaBody></p:FatturaElettronica>';
        return $xml;
    }

    public static function validate(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new \DOMDocument();
        $errors = [];
        if (!@$doc->loadXML($xml)) {
            $errors[] = 'XML non ben formato.';
        } elseif (!@$doc->schemaValidate(__DIR__ . '/schema/FatturaPA_v1.2.2.xsd')) {
            foreach (libxml_get_errors() as $e) {
                $errors[] = trim($e->message) . ' (riga ' . $e->line . ')';
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return array_slice(array_values(array_unique($errors)), 0, 12);
    }
}
