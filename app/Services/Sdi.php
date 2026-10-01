<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Secret;
use Alien\Core\Settings;
use Alien\EInvoice\Imap;
use Alien\EInvoice\Mime;

final class Sdi
{
    public static function smtpConfig(): array
    {
        return [
            'host' => (string)Settings::get('pec_smtp_host', ''), 'port' => (int)Settings::get('pec_smtp_port', 465), 'secure' => (string)Settings::get('pec_smtp_secure', 'ssl'),
            'user' => (string)Settings::get('pec_user', ''), 'pass' => Secret::open((string)Settings::get('pec_pass', '')),
        ];
    }

    public static function imapConfig(): array
    {
        return [
            'host' => (string)Settings::get('pec_imap_host', ''), 'port' => (int)Settings::get('pec_imap_port', 993), 'secure' => (string)Settings::get('pec_imap_secure', 'ssl'),
            'user' => (string)Settings::get('pec_user', ''), 'pass' => Secret::open((string)Settings::get('pec_pass', '')),
        ];
    }

    public static function canSend(): bool
    {
        return (string)Settings::get('einv_transport', 'manual') === 'pec' && self::smtpConfig()['host'] !== '' && (string)Settings::get('pec_address', '') !== '';
    }

    public static function canReceive(): bool
    {
        return (string)Settings::get('einv_transport', 'manual') === 'pec' && self::imapConfig()['host'] !== '' && self::imapConfig()['user'] !== '';
    }

    public static function sdiAddress(): string
    {
        return (string)Settings::get('sdi_address', 'sdi01@pec.fatturapa.it') ?: 'sdi01@pec.fatturapa.it';
    }

    public static function send(array $inv): ?string
    {
        if (!self::canSend()) {
            return __('Invio via PEC non configurato.');
        }
        $path = EInvoices::xmlPath($inv);
        if (!is_file($path)) {
            return __('File XML non trovato.');
        }
        try {
            Mailer::sendSmtp(
                self::smtpConfig(), (string)Settings::get('pec_address'), (string)Settings::get('einv_name', Settings::get('store_name', '')),
                self::sdiAddress(), (string)$inv['file_name'], '<p>' . e((string)$inv['file_name']) . '</p>',
                [['name' => $inv['file_name'], 'data' => (string)file_get_contents($path), 'type' => 'application/xml']]
            );
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        return null;
    }

    public static function testSmtp(): ?string
    {
        if (!self::canSend()) {
            return __('Compila i dati del server PEC in uscita.');
        }
        try {
            Mailer::sendSmtp(self::smtpConfig(), (string)Settings::get('pec_address'), 'AlienShop', (string)Settings::get('pec_address'), 'Test AlienShop', '<p>Test</p>');
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function testImap(): ?string
    {
        if (!self::canReceive()) {
            return __('Compila i dati del server PEC in entrata.');
        }
        try {
            $imap = new Imap(self::imapConfig());
            $imap->connect();
            $imap->close();
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function sync(int $limit = 40): array
    {
        $report = ['messages' => 0, 'invoices' => 0, 'duplicates' => 0, 'notifications' => 0, 'errors' => []];
        Settings::set('einv_last_sync', now());
        if (!self::canReceive()) {
            $report['errors'][] = __('Ricezione via PEC non configurata.');
            return $report;
        }
        try {
            $imap = new Imap(self::imapConfig());
            $imap->connect();
            foreach ($imap->unseen($limit) as $uid) {
                $raw = $imap->fetch($uid);
                $report['messages']++;
                foreach (Mime::parse($raw) as $att) {
                    $name = $att['name'];
                    if (preg_match('/_(RC|NS|MC|NE|DT|AT|SE)_\d+\.xml$/i', $name)) {
                        $report['notifications'] += self::notification($name, $att['data']) ? 1 : 0;
                    } elseif (preg_match('/_MT_\d+\.xml$/i', $name) || strtolower($name) === 'daticert.xml') {
                        continue;
                    } elseif (preg_match('/\.(xml|p7m)$/i', $name)) {
                        $r = Purchases::ingest($att['data'], 'pec', $name);
                        $report['invoices'] += $r['added'];
                        $report['duplicates'] += $r['duplicates'];
                        array_push($report['errors'], ...$r['errors']);
                    }
                }
                $imap->markSeen($uid);
            }
            $imap->close();
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();
        }
        Settings::set('einv_last_report', json_encode($report, json_flags()));
        return $report;
    }

    public static function notification(string $filename, string $xml): bool
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = stripos($xml, '<!DOCTYPE') === false && stripos($xml, '<!ENTITY') === false && @$doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$doc->documentElement) {
            return false;
        }
        $xp = new \DOMXPath($doc);
        $v = static function (string $name) use ($xp): string {
            $n = $xp->query("//*[local-name()='$name']");
            return $n && $n->length ? trim($n->item(0)->textContent) : '';
        };
        $root = (string)$doc->documentElement->localName;
        $nome = $v('NomeFile') ?: preg_replace('/_(RC|NS|MC|NE|DT|AT|SE)_\d+\.xml$/i', '.xml', $filename);
        $inv = DB::row('SELECT * FROM einvoices WHERE file_name = ?', [$nome]);
        if (!$inv) {
            return false;
        }
        $update = ['sdi_id' => $v('IdentificativoSdI') ?: $inv['sdi_id']];
        $msg = '';
        switch ($root) {
            case 'RicevutaConsegna':
                $update['status'] = 'delivered';
                $msg = __('Ricevuta di consegna: la fattura è stata recapitata.');
                break;
            case 'NotificaMancataConsegna':
                $update['status'] = 'undeliverable';
                $msg = __('Mancata consegna: la fattura è disponibile nel cassetto fiscale del cliente.');
                break;
            case 'NotificaScarto':
                $errors = [];
                foreach ($xp->query("//*[local-name()='Errore']") ?: [] as $e) {
                    $errors[] = trim($xp->evaluate("string(*[local-name()='Codice'])", $e) . ' ' . $xp->evaluate("string(*[local-name()='Descrizione'])", $e));
                }
                $update['status'] = 'rejected';
                $update['errors'] = json_encode($errors ?: [__('Fattura scartata da SdI.')], json_flags());
                $msg = __('Scarto SdI: %s', implode(' | ', $errors));
                break;
            case 'NotificaEsito':
                $esito = $v('Esito');
                $update['status'] = $esito === 'EC02' ? 'refused' : 'accepted';
                $msg = $esito === 'EC02' ? __('Il cliente ha rifiutato la fattura.') : __('Il cliente ha accettato la fattura.');
                break;
            case 'NotificaDecorrenzaTermini':
                $update['status'] = 'expired';
                $msg = __('Decorrenza termini: fattura considerata accettata.');
                break;
            case 'AttestazioneTrasmissioneFattura':
                $update['status'] = 'delivered';
                $msg = __('Attestazione di avvenuta trasmissione.');
                break;
            default:
                return false;
        }
        DB::update('einvoices', $update, 'id = ?', [$inv['id']]);
        EInvoices::log((int)$inv['id'], $msg);
        return true;
    }
}
