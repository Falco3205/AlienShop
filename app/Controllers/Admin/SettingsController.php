<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Lang;
use Alien\Core\Mailer;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Payments\Registry;
use Alien\Services\IndexNow;
use Alien\Services\Shipping;

final class SettingsController extends AdminController
{
    public function hub(): Response
    {
        $cards = [
            ['admin/settings/general', '🏪', __('Dati del negozio'), __('Nome, valuta, lingua, tasse e numerazione ordini.')],
            ['admin/payments', '💳', __('Pagamenti'), __('Stripe, PayPal, bonifico e contrassegno.')],
            ['admin/shipping', '🚚', __('Spedizioni'), __('Metodi, prezzi e spedizione gratuita.')],
            ['admin/settings/mail', '✉️', __('Email'), __('Mittente e server SMTP per le notifiche.')],
            ['admin/settings/seo', '🔍', __('SEO e Google'), __('Titoli, sitemap, Search Console, Bing.')],
            ['admin/einvoice' . (\Alien\Services\Modules::on('einvoice') ? '' : '/setup'), '🇮🇹', __('Fatturazione elettronica'), __('Fatture SdI via PEC, fatture dei fornitori e contabilità (Italia).')],
            ['admin/legal', '⚖️', __('Pagine legali'), __('Privacy, cookie, termini, resi: procedura guidata gratuita.')],
            ['admin/analytics', '📊', __('Google Analytics'), __('Collega le statistiche con una procedura guidata.')],
            ['admin/redirects', '↪️', __('Redirect e 404'), __('Mantieni il posizionamento quando cambiano gli URL.')],
            ['admin/import', '📦', __('Import / Export'), __('Da e verso WooCommerce e Shopify.')],
            ['admin/themes', '🎨', __('Aspetto e temi'), __('10 temi, colori, logo e home page.')],
            ['admin/modules', '🧩', __('Estensioni'), __('Recensioni, newsletter, carrelli abbandonati, fatture e altro: attiva quello che ti serve.')],
            ['admin/backup', '💾', __('Backup'), __('Scarica una copia di sicurezza del tuo negozio.')],
            ['admin/team', '👥', __('Collaboratori'), __('Chi può accedere al pannello.')],
            ['admin/profile', '👤', __('Il mio profilo'), __('Email e password di accesso.')],
        ];
        return $this->view('settings/hub', ['title' => __('Impostazioni'), 'subtitle' => __('Tutto quello che puoi configurare, in un posto solo'), 'cards' => $cards], 'settings');
    }

    public function general(Request $req): Response
    {
        $groups = [
            __('Negozio') => [
                ['store_name', __('Nome del negozio'), 'text'],
                ['store_tagline', __('Slogan'), 'text'],
                ['store_description', __('Descrizione del negozio (usata come meta description predefinita)'), 'textarea'],
                ['store_email', __('Email del negozio (notifiche ordini)'), 'email'],
                ['locale', __('Lingua'), 'select', Lang::available()],
                ['currency', __('Valuta'), 'select', array_combine(Money::currencies(), Money::currencies())],
                ['default_country', __('Paese predefinito'), 'select', Shipping::countries()],
            ],
            __('Tasse e ordini') => [
                ['tax_rate', __('Aliquota IVA / tasse (%)'), 'number', ['step' => '0.01']],
                ['tax_country_rates', __('Aliquote per paese (una per riga, es. DE=19) — sovrascrivono quella predefinita'), 'textarea'],
                ['prices_include_tax', __('I prezzi includono le tasse'), 'checkbox'],
                ['order_prefix', __('Prefisso numero ordine'), 'text'],
                ['products_per_page', __('Prodotti per pagina'), 'number'],
            ],
            __('Footer e social') => [
                ['footer_text', __('Testo del footer'), 'textarea'],
                ['footer_note', __('Nota a piè di pagina'), 'text'],
                ['social_links', __('Profili social (URL separati da virgola)'), 'textarea'],
            ],
        ];
        return $this->settingsPage($req, 'general', __('Impostazioni'), $groups);
    }

    public function seo(Request $req): Response
    {
        $groups = [
            __('Home page') => [
                ['seo_home_title', __('Titolo SEO della home'), 'text'],
                ['seo_home_description', __('Meta description della home'), 'textarea'],
            ],
            __('Motori di ricerca') => [
                ['google_verification', __('Google Search Console — codice di verifica'), 'text'],
                ['bing_verification', __('Bing Webmaster Tools — codice di verifica'), 'text'],
                ['indexnow_enabled', __('Notifica automatica a Bing/Yandex (IndexNow) quando pubichi o modifichi un prodotto'), 'checkbox'],
                ['discourage_indexing', __('Scoraggia l\'indicizzazione (sito in costruzione)'), 'checkbox'],
                ['robots_extra', __('Righe extra per robots.txt'), 'textarea'],
            ],
            __('Analytics e codice personalizzato') => [
                ['head_code', __('Codice nell\'<head>'), 'textarea'],
                ['footer_code', __('Codice a fine pagina'), 'textarea'],
            ],
        ];
        $ga = \Alien\Services\Analytics::id();
        $info = '<div class="card"><h2>Google Analytics</h2><p>' . ($ga !== '' ? '✓ ' . e(__('Collegato')) . ' <code>' . e($ga) . '</code>' : e(__('Non ancora collegato.'))) . ' <a class="btn sec sm" href="' . e(url('admin/analytics')) . '">' . e($ga !== '' ? __('Gestisci') : __('Collega con la procedura guidata')) . '</a></p></div>'
            . '<div class="card"><h2>' . e(__('URL utili')) . '</h2><ul><li>Sitemap: <a href="' . e(url('sitemap.xml')) . '" target="_blank">' . e(url('sitemap.xml')) . '</a> — '
            . e(__('inviala a Google Search Console e Bing Webmaster Tools.')) . '</li><li>robots.txt: <a href="' . e(url('robots.txt')) . '" target="_blank">' . e(url('robots.txt')) . '</a></li>'
            . '<li>Google Merchant feed: <a href="' . e(url('feeds/google.xml')) . '" target="_blank">' . e(url('feeds/google.xml')) . '</a></li>'
            . '<li>IndexNow key: <code>' . e(IndexNow::key()) . '</code></li></ul></div>';
        return $this->settingsPage($req, 'seo', __('SEO'), $groups, $info);
    }

    public function mail(Request $req): Response
    {
        $groups = [
            __('Invio email') => [
                ['mail_driver', __('Metodo di invio'), 'select', ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'log' => __('Solo log (test)')]],
                ['mail_from', __('Indirizzo mittente'), 'email'],
            ],
            'SMTP' => [
                ['smtp_host', __('Host'), 'text'],
                ['smtp_port', __('Porta'), 'number'],
                ['smtp_secure', __('Sicurezza'), 'select', ['tls' => 'STARTTLS', 'ssl' => 'SSL', '' => __('Nessuna')]],
                ['smtp_user', __('Utente'), 'text'],
                ['smtp_pass', __('Password'), 'password'],
            ],
        ];
        if ($req->isPost() && $req->str('test_to') !== '') {
            $this->persist($groups, $req);
            $ok = Mailer::send($req->str('test_to'), 'AlienShop test', '<p>' . e(__('Email di prova inviata correttamente.')) . '</p>');
            return $this->back('admin/settings/mail', $ok ? __('Email di prova inviata.') : __('Invio non riuscito: controlla le impostazioni.'), $ok ? 'success' : 'error');
        }
        $test = '<div class="card"><h2>' . e(__('Invia email di prova')) . '</h2><form method="post" action="' . e(url('admin/settings/mail')) . '" style="display:flex;gap:10px">' . csrf_field()
            . '<input type="email" name="test_to" placeholder="you@example.com" required><button class="btn sec" type="submit">' . e(__('Invia')) . '</button></form></div>';
        return $this->settingsPage($req, 'mail', __('Email'), $groups, $test);
    }

    public function payments(Request $req): Response
    {
        $groups = [];
        foreach (Registry::all() as $g) {
            $fields = [['pay_' . $g->id() . '_enabled', __('Abilita'), 'checkbox']];
            foreach ($g->fields() as $key => $f) {
                $fields[] = ['pay_' . $g->id() . '_' . $key, $f['label'], $f['type'], [], $f['help'] ?? ''];
            }
            $groups[$g->label()] = $fields;
        }
        return $this->settingsPage($req, 'payments', __('Pagamenti'), $groups);
    }

    private function settingsPage(Request $req, string $tab, string $title, array $groups, string $extra = ''): Response
    {
        if ($req->isPost() && $req->str('test_to') === '') {
            $this->persist($groups, $req);
            return $this->back($req->path, __('Impostazioni salvate.'));
        }
        return $this->view('settings/form', ['title' => $title, 'subtitle' => $tab === 'payments' ? __('Scegli come i clienti possono pagarti') : '', 'tab' => $tab, 'groups' => $groups, 'extra' => $extra], match ($tab) { 'payments' => 'payments', 'seo' => 'seo', default => 'settings' });
    }

    private function persist(array $groups, Request $req): void
    {
        foreach ($groups as $fields) {
            foreach ($fields as $f) {
                [$key, , $type] = $f;
                if (!array_key_exists($key, $req->post)) {
                    continue;
                }
                $value = $req->post[$key];
                if (!is_string($value)) {
                    continue;
                }
                if ($type === 'password') {
                    if ($value === '') {
                        continue;
                    }
                    Settings::set($key, \Alien\Core\Secret::seal(trim($value)));
                    continue;
                }
                if ($type === 'checkbox') {
                    $value = $value === '1' ? '1' : '0';
                }
                if ($key === 'tax_rate') {
                    $value = (string)(float)str_replace(',', '.', $value);
                }
                Settings::set($key, trim($value));
            }
        }
    }
}
