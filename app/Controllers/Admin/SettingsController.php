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
                ['analytics_id', __('ID Google Analytics 4 (G-XXXXXXX)'), 'text'],
                ['head_code', __('Codice nell\'<head>'), 'textarea'],
                ['footer_code', __('Codice a fine pagina'), 'textarea'],
            ],
        ];
        $info = '<div class="card"><h2>' . e(__('URL utili')) . '</h2><ul><li>Sitemap: <a href="' . e(url('sitemap.xml')) . '" target="_blank">' . e(url('sitemap.xml')) . '</a> — '
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
        return $this->view('settings/form', ['title' => $title, 'tab' => $tab, 'groups' => $groups, 'extra' => $extra], $tab === 'payments' ? 'payments' : 'settings');
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
                if ($type === 'password' && $value === '') {
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
