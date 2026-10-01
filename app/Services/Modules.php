<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Settings;

final class Modules
{
    public static function all(): array
    {
        return [
            'reviews' => ['icon' => '⭐', 'name' => __('Recensioni prodotto'), 'text' => __('I clienti lasciano stelle e commenti, con moderazione, badge "acquisto verificato" e stelle su Google.'), 'default' => 0, 'link' => 'admin/reviews'],
            'newsletter' => ['icon' => '✉️', 'name' => __('Newsletter'), 'text' => __('Iscrizione con doppia conferma, invio campagne ai iscritti ed esportazione contatti.'), 'default' => 0, 'link' => 'admin/newsletter'],
            'abandoned_cart' => ['icon' => '🛒', 'name' => __('Recupero carrelli abbandonati'), 'text' => __('Un\'email automatica ricorda ai clienti il carrello lasciato, con sconto facoltativo.'), 'default' => 0, 'link' => 'admin/abandoned'],
            'invoices' => ['icon' => '🧾', 'name' => __('Fatture e ricevute PDF'), 'text' => __('Documenti PDF numerati per ogni ordine, inviabili al cliente, con esportazione per il commercialista.'), 'default' => 1, 'link' => 'admin/invoices'],
            'wishlist' => ['icon' => '❤️', 'name' => __('Lista dei desideri'), 'text' => __('I visitatori salvano i prodotti preferiti e li ritrovano in una pagina dedicata.'), 'default' => 0, 'link' => null],
            'stock_alerts' => ['icon' => '🔔', 'name' => __('Avvisami quando torna disponibile'), 'text' => __('Sui prodotti esauriti il cliente lascia l\'email e riceve un avviso al rifornimento.'), 'default' => 1, 'link' => null],
            'einvoice' => ['icon' => '🇮🇹', 'name' => __('Fatturazione elettronica e contabilità (Italia)'), 'text' => __('Fatture XML verso SdI via PEC, ricezione fatture dei fornitori, registri vendite e acquisti, IVA e scadenzario.'), 'default' => 0, 'link' => 'admin/einvoice'],
            'stats' => ['icon' => '📈', 'name' => __('Statistiche interne'), 'text' => __('Visite, carrelli e vendite per prodotto, senza cookie né dati personali.'), 'default' => 1, 'link' => 'admin/stats'],
        ];
    }

    public static function on(string $id): bool
    {
        $all = self::all();
        return isset($all[$id]) && (string)Settings::get('mod_' . $id, (string)$all[$id]['default']) === '1';
    }

    public static function set(string $id, bool $enabled): void
    {
        if (isset(self::all()[$id])) {
            Settings::set('mod_' . $id, $enabled ? 1 : 0);
        }
    }
}
