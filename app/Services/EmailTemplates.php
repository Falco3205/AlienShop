<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;
use Alien\Core\Mailer;
use Alien\Core\Money;
use Alien\Core\Settings;

final class EmailTemplates
{
    private const HTML_VARS = ['order_details', 'order_link', 'admin_link', 'account_link', 'reset_link'];

    public static function definitions(): array
    {
        $order = ['store_name', 'customer_name', 'customer_email', 'order_number', 'order_total', 'tracking', 'order_details', 'order_link'];
        return [
            'order_confirmation' => [
                'label' => __('Conferma ordine'),
                'hint' => __('Inviata al cliente quando l\'ordine è confermato.'),
                'vars' => $order,
                'subject' => __('Conferma ordine {order_number}'),
                'body' => __("Grazie per il tuo ordine, {customer_name}!\n\nAbbiamo ricevuto il tuo ordine {order_number} e lo stiamo preparando.\n\n{order_details}\n\n{order_link}"),
            ],
            'order_admin' => [
                'label' => __('Nuovo ordine (per te)'),
                'hint' => __('Inviata all\'email del negozio quando arriva un ordine.'),
                'vars' => [...$order, 'admin_link'],
                'subject' => __('Nuovo ordine {order_number} — {order_total}'),
                'body' => __("Hai ricevuto un nuovo ordine da {customer_name} ({customer_email}).\n\n{order_details}\n\n{admin_link}"),
            ],
            'order_shipped' => [
                'label' => __('Ordine spedito'),
                'hint' => __('Inviata al cliente quando segni l\'ordine come spedito.'),
                'vars' => $order,
                'subject' => __('Il tuo ordine {order_number} è stato spedito'),
                'body' => __("Ciao {customer_name}, il tuo ordine è in viaggio!\n\nCodice di tracciamento: {tracking}\n\n{order_details}\n\n{order_link}"),
            ],
            'order_cancelled' => [
                'label' => __('Ordine annullato'),
                'hint' => __('Inviata al cliente quando annulli un ordine.'),
                'vars' => $order,
                'subject' => __('Ordine {order_number} annullato'),
                'body' => __("Ciao {customer_name}, il tuo ordine {order_number} è stato annullato.\n\nSe hai domande scrivici: siamo a tua disposizione.\n\n{order_details}"),
            ],
            'order_refunded' => [
                'label' => __('Ordine rimborsato'),
                'hint' => __('Inviata al cliente quando rimborsi un ordine.'),
                'vars' => $order,
                'subject' => __('Ordine {order_number} rimborsato'),
                'body' => __("Ciao {customer_name}, abbiamo rimborsato il tuo ordine {order_number} di {order_total}.\n\nI tempi di accredito dipendono dal tuo metodo di pagamento.\n\n{order_details}"),
            ],
            'welcome' => [
                'label' => __('Benvenuto'),
                'hint' => __('Inviata quando un cliente crea un account.'),
                'vars' => ['store_name', 'customer_name', 'customer_email', 'account_link'],
                'subject' => __('Benvenuto su {store_name}'),
                'body' => __("Ciao {customer_name},\n\nil tuo account è stato creato. Ora puoi seguire i tuoi ordini e acquistare più velocemente.\n\n{account_link}"),
            ],
            'password_reset' => [
                'label' => __('Reimposta password'),
                'hint' => __('Inviata quando qualcuno chiede di reimpostare la password.'),
                'vars' => ['store_name', 'customer_name', 'reset_link'],
                'subject' => __('Reimposta la tua password'),
                'body' => __("Hai richiesto di reimpostare la password di {store_name}.\n\n{reset_link}\n\nIl link è valido per un'ora. Se non sei stato tu, ignora questa email."),
            ],
        ];
    }

    public static function variableHelp(): array
    {
        return [
            'store_name' => __('Nome del negozio'),
            'customer_name' => __('Nome del cliente'),
            'customer_email' => __('Email del cliente'),
            'order_number' => __('Numero ordine'),
            'order_total' => __('Totale ordine'),
            'tracking' => __('Codice di tracciamento'),
            'order_details' => __('Tabella con prodotti, totali e indirizzo'),
            'order_link' => __('Pulsante "Vedi il tuo ordine"'),
            'admin_link' => __('Pulsante per aprire l\'ordine in admin'),
            'account_link' => __('Pulsante "Vai al tuo account"'),
            'reset_link' => __('Pulsante "Scegli una nuova password"'),
        ];
    }

    public static function enabled(string $id): bool
    {
        return (string)Settings::get('mail_tpl_' . $id . '_on', '1') !== '0';
    }

    public static function template(string $id): ?array
    {
        $def = self::definitions()[$id] ?? null;
        if (!$def) {
            return null;
        }
        $def['custom_subject'] = (string)Settings::get('mail_tpl_' . $id . '_subject', '');
        $def['custom_body'] = (string)Settings::get('mail_tpl_' . $id . '_body', '');
        $def['subject'] = $def['custom_subject'] !== '' ? $def['custom_subject'] : $def['subject'];
        $def['body'] = $def['custom_body'] !== '' ? $def['custom_body'] : $def['body'];
        return $def;
    }

    public static function save(string $id, string $subject, string $body, bool $on): void
    {
        $def = self::definitions()[$id] ?? null;
        if (!$def) {
            return;
        }
        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        $body = trim(str_replace("\r\n", "\n", $body));
        Settings::set('mail_tpl_' . $id . '_subject', $subject === trim($def['subject']) ? '' : mb_substr($subject, 0, 200));
        Settings::set('mail_tpl_' . $id . '_body', $body === trim($def['body']) ? '' : mb_substr($body, 0, 8000));
        Settings::set('mail_tpl_' . $id . '_on', $on ? '1' : '0');
    }

    public static function reset(string $id): void
    {
        foreach (['_subject', '_body'] as $k) {
            Settings::set('mail_tpl_' . $id . $k, '');
        }
        Settings::set('mail_tpl_' . $id . '_on', '1');
    }

    public static function render(string $id, array $vars, ?array $override = null): ?array
    {
        $t = self::template($id);
        if (!$t) {
            return null;
        }
        if ($override) {
            $t['subject'] = trim((string)($override['subject'] ?? '')) !== '' ? (string)$override['subject'] : $t['subject'];
            $t['body'] = trim((string)($override['body'] ?? '')) !== '' ? str_replace("\r\n", "\n", (string)$override['body']) : $t['body'];
        }
        $vars += ['store_name' => (string)Settings::get('store_name', 'Shop')];
        $subject = trim(preg_replace_callback('/\{(\w+)\}/', static fn($m) => in_array($m[1], self::HTML_VARS, true) ? '' : (string)($vars[$m[1]] ?? $m[0]), $t['subject']) ?? $t['subject']);
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject);
        $paragraphs = preg_split('/\n{2,}/', trim($t['body'])) ?: [];
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $alone = preg_match('/^\{(\w+)\}$/D', $p, $m) && in_array($m[1], self::HTML_VARS, true);
            $text = nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8'), false);
            $text = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars) {
                $v = (string)($vars[$m[1]] ?? '');
                if (!array_key_exists($m[1], $vars)) {
                    return $m[0];
                }
                return in_array($m[1], self::HTML_VARS, true) ? self::htmlVar($m[1], $v) : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            }, $text) ?? $text;
            $html .= $alone ? $text : '<p style="margin:0 0 16px;line-height:1.55">' . $text . '</p>';
        }
        return [$subject, self::wrap($html)];
    }

    public static function send(string $id, string $to, array $vars): bool
    {
        if ($to === '' || !self::enabled($id)) {
            return false;
        }
        $r = self::render($id, $vars);
        return $r ? Mailer::send($to, $r[0], $r[1]) : false;
    }

    public static function orderVars(array $o): array
    {
        $name = trim((string)(($o['billing']['name'] ?? '') ?: ($o['shipping_address']['name'] ?? '')));
        return [
            'customer_name' => $name !== '' ? $name : (string)$o['email'],
            'customer_email' => (string)$o['email'],
            'order_number' => (string)$o['number'],
            'order_total' => Money::format((int)$o['total'], $o['currency']),
            'tracking' => (string)($o['tracking'] ?? ''),
            'order_details' => Orders::detailsHtml($o),
            'order_link' => Config::baseUrl() . '/checkout/thank-you/' . $o['token'],
            'admin_link' => Config::baseUrl() . '/admin/orders/' . ($o['id'] ?? 0),
        ];
    }

    public static function sampleOrder(): array
    {
        $addr = ['name' => 'Mario Rossi', 'address' => 'Via Roma 1', 'zip' => '20100', 'city' => 'Milano', 'country' => 'IT'];
        return [
            'id' => 0, 'number' => (string)Settings::get('order_prefix', 'AS-') . '1001', 'token' => 'anteprima', 'email' => 'mario.rossi@example.com',
            'currency' => (string)Settings::get('currency', 'EUR'), 'subtotal' => 8900, 'discount' => 0, 'shipping' => 590, 'total' => 9490,
            'payment_method' => 'stripe', 'payment_status' => 'paid', 'tracking' => 'ZX123456789IT',
            'billing' => $addr, 'shipping_address' => $addr,
            'items' => [
                ['name' => __('Prodotto di esempio'), 'variant_label' => 'M / Blu', 'qty' => 2, 'total' => 5800],
                ['name' => __('Altro prodotto'), 'variant_label' => '', 'qty' => 1, 'total' => 3100],
            ],
        ];
    }

    public static function sampleVars(string $id): array
    {
        $vars = self::orderVars(self::sampleOrder()) + [
            'account_link' => Config::baseUrl() . '/account',
            'reset_link' => Config::baseUrl() . '/account/reset/anteprima',
        ];
        $vars['customer_name'] = 'Mario Rossi';
        return $vars;
    }

    public static function color(): string
    {
        $c = (string)Settings::get('mail_color', '');
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $c) ? $c : '#6c4cf5';
    }

    private static function htmlVar(string $name, string $value): string
    {
        if ($name === 'order_details') {
            return $value;
        }
        $labels = [
            'order_link' => __('Vedi il tuo ordine'), 'admin_link' => __('Apri l\'ordine'),
            'account_link' => __('Vai al tuo account'), 'reset_link' => __('Scegli una nuova password'),
        ];
        return '<a href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:' . self::color() . ';color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold">' . e($labels[$name] ?? $name) . '</a>';
    }

    private static function wrap(string $inner): string
    {
        $store = e((string)Settings::get('store_name', 'Shop'));
        $color = self::color();
        $logo = (string)Settings::get('logo', '');
        $head = $logo !== '' ? '<img src="' . e(upload_url($logo)) . '" alt="' . $store . '" height="44" style="display:block;margin:auto;max-height:44px">' : '<span style="font-size:22px;font-weight:bold;color:#fff">' . $store . '</span>';
        $footer = trim((string)Settings::get('mail_footer', ''));
        $footer = $footer !== '' ? nl2br(e($footer), false) : $store;
        return '<div style="background:#f4f4f5;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#222">'
            . '<table align="center" width="600" cellpadding="0" cellspacing="0" style="max-width:100%;background:#fff;border-radius:8px;overflow:hidden">'
            . '<tr><td style="background:' . $color . ';padding:20px;text-align:center">' . $head . '</td></tr>'
            . '<tr><td style="padding:28px 24px;font-size:15px">' . $inner . '</td></tr>'
            . '<tr><td style="padding:16px 24px;background:#fafafa;color:#777;font-size:12px;text-align:center;line-height:1.5">' . $footer . '</td></tr>'
            . '</table></div>';
    }
}
