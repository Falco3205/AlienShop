<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class XmlParser
{
    private \DOMXPath $xp;

    public static function parse(string $xml): array
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = stripos($xml, '<!DOCTYPE') === false && stripos($xml, '<!ENTITY') === false && @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$doc->documentElement || !str_contains($doc->documentElement->localName ?? '', 'FatturaElettronica')) {
            throw new \RuntimeException(__('Il file non è una fattura elettronica valida.'));
        }
        return (new self($doc))->extract();
    }

    private function __construct(private readonly \DOMDocument $doc)
    {
        $this->xp = new \DOMXPath($doc);
    }

    private function path(string $p): string
    {
        return implode('/', array_map(static fn($s) => "*[local-name()='$s']", explode('/', $p)));
    }

    private function nodes(string $p, ?\DOMNode $ctx = null): array
    {
        $r = $this->xp->query('./' . $this->path($p), $ctx ?? $this->doc->documentElement);
        return $r ? iterator_to_array($r) : [];
    }

    private function v(string $p, ?\DOMNode $ctx = null): string
    {
        $n = $this->nodes($p, $ctx);
        return $n ? trim($n[0]->textContent) : '';
    }

    private function party(\DOMNode $node): array
    {
        $da = 'DatiAnagrafici';
        $name = $this->v("$da/Anagrafica/Denominazione", $node);
        if ($name === '') {
            $name = trim($this->v("$da/Anagrafica/Nome", $node) . ' ' . $this->v("$da/Anagrafica/Cognome", $node));
        }
        return [
            'name' => $name,
            'country' => $this->v("$da/IdFiscaleIVA/IdPaese", $node) ?: $this->v('Sede/Nazione', $node) ?: 'IT',
            'vat' => $this->v("$da/IdFiscaleIVA/IdCodice", $node),
            'cf' => $this->v("$da/CodiceFiscale", $node),
            'address' => trim($this->v('Sede/Indirizzo', $node) . ' ' . $this->v('Sede/NumeroCivico', $node)),
            'cap' => $this->v('Sede/CAP', $node), 'city' => $this->v('Sede/Comune', $node), 'prov' => $this->v('Sede/Provincia', $node),
        ];
    }

    private function money(string $v): int
    {
        return (int)round((float)str_replace(',', '.', $v) * 100);
    }

    private function extract(): array
    {
        $header = $this->nodes('FatturaElettronicaHeader')[0] ?? null;
        $supplier = $header ? $this->party($this->nodes('CedentePrestatore', $header)[0] ?? $header) : [];
        $customer = $header ? $this->party($this->nodes('CessionarioCommittente', $header)[0] ?? $header) : [];
        $docs = [];
        foreach ($this->nodes('FatturaElettronicaBody') as $body) {
            $g = 'DatiGenerali/DatiGeneraliDocumento';
            $summary = [];
            $net = $vat = 0;
            foreach ($this->nodes('DatiBeniServizi/DatiRiepilogo', $body) as $r) {
                $row = ['rate' => (float)$this->v('AliquotaIVA', $r), 'nature' => $this->v('Natura', $r), 'net' => $this->money($this->v('ImponibileImporto', $r)), 'vat' => $this->money($this->v('Imposta', $r))];
                $summary[] = $row;
                $net += $row['net'];
                $vat += $row['vat'];
            }
            $lines = [];
            foreach ($this->nodes('DatiBeniServizi/DettaglioLinee', $body) as $l) {
                $lines[] = ['desc' => $this->v('Descrizione', $l), 'qty' => (float)$this->v('Quantita', $l), 'unit' => $this->money($this->v('PrezzoUnitario', $l)), 'total' => $this->money($this->v('PrezzoTotale', $l)), 'rate' => (float)$this->v('AliquotaIVA', $l)];
            }
            $payments = [];
            foreach ($this->nodes('DatiPagamento/DettaglioPagamento', $body) as $p) {
                $payments[] = ['mode' => $this->v('ModalitaPagamento', $p), 'due' => $this->v('DataScadenzaPagamento', $p), 'amount' => $this->money($this->v('ImportoPagamento', $p)), 'iban' => $this->v('IBAN', $p)];
            }
            $total = $this->v("$g/ImportoTotaleDocumento", $body) !== '' ? $this->money($this->v("$g/ImportoTotaleDocumento", $body)) : $net + $vat;
            $causale = array_map(static fn($n) => trim($n->textContent), $this->nodes("$g/Causale", $body));
            $docs[] = [
                'supplier' => $supplier, 'customer' => $customer,
                'doc_type' => $this->v("$g/TipoDocumento", $body) ?: 'TD01', 'currency' => $this->v("$g/Divisa", $body) ?: 'EUR',
                'number' => $this->v("$g/Numero", $body), 'date' => $this->v("$g/Data", $body),
                'total' => $total, 'net' => $net, 'vat' => $vat, 'summary' => $summary, 'lines' => $lines, 'payments' => $payments,
                'causale' => implode(' ', $causale),
            ];
        }
        if (!$docs) {
            throw new \RuntimeException(__('La fattura non contiene documenti.'));
        }
        return $docs;
    }
}
