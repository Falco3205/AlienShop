<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\Catalog;
use Alien\Services\Orders;

final class DashboardController extends AdminController
{
    public function index(Request $req): Response
    {
        $days = in_array($req->int('days', 30), [7, 30, 90], true) ? $req->int('days', 30) : 30;
        $checklist = Settings::get('onboard_dismissed') ? [] : $this->checklist();
        return $this->view('dashboard', [
            'title' => __('Home'),
            'subtitle' => __('Ecco cosa succede nel tuo negozio'),
            'actions' => '<a class="btn" href="' . e(url('admin/products/new')) . '">+ ' . e(__('Nuovo prodotto')) . '</a>',
            'name' => explode(' ', trim((string)Auth::user()['name']))[0] ?: 'Admin',
            'days' => $days,
            'stats' => Orders::stats($days),
            'recent' => DB::all('SELECT * FROM orders ORDER BY id DESC LIMIT 6'),
            'todo' => [
                'to_ship' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
                'awaiting' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND payment_status = 'unpaid'"),
                'low' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active' AND manage_stock = 1 AND in_stock = 1 AND stock_qty <= 3 AND type = 'simple'"),
                'out' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active' AND in_stock = 0"),
                'bills' => \Alien\Services\Modules::on('einvoice') ? (int)DB::val('SELECT COUNT(*) FROM purchase_invoices WHERE paid_at IS NULL') : -1,
                'drafts' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'draft'"),
            ],
            'checklist' => $checklist,
            'demo' => (int)DB::val("SELECT COUNT(*) FROM products WHERE sku LIKE 'DEMO-%'") > 0,
            'lowStock' => Catalog::lookup(['low_stock' => 1, 'per' => 5])['items'],
        ], 'dashboard');
    }

    private function checklist(): array
    {
        $paymentsOn = Settings::get('pay_stripe_enabled') || Settings::get('pay_paypal_enabled') || Settings::get('pay_mollie_enabled');
        $items = [
            [(int)DB::val("SELECT COUNT(*) FROM products WHERE sku NOT LIKE 'DEMO-%'") > 0, __('Aggiungi i tuoi prodotti'), __('Creali a mano oppure importali da WooCommerce o Shopify.'), 'admin/products/new'],
            [(bool)Settings::get('theme_customized'), __('Personalizza l\'aspetto'), __('Scegli il tema, il logo e i colori del tuo negozio.'), 'admin/themes'],
            [$paymentsOn, __('Attiva i pagamenti online'), __('Collega Stripe o PayPal per incassare con carta.'), 'admin/payments'],
            [(bool)Settings::get('onboard_shipping'), __('Controlla le spedizioni'), __('Imposta prezzi e soglia di spedizione gratuita.'), 'admin/shipping'],
            [(bool)Settings::get('legal_done'), __('Genera le pagine legali'), __('Privacy, cookie, termini e condizioni, resi: con una procedura guidata.'), 'admin/legal'],
            ((string)Settings::get('default_country', 'IT') === 'IT' ? [(bool)Settings::get('einv_done'), __('Attiva la fatturazione elettronica'), __('Fatture SdI via PEC, fatture dei fornitori e contabilità: gratis.'), 'admin/einvoice/setup'] : null),
            [(bool)Settings::get('smtp_host'), __('Configura l\'invio delle email'), __('Per conferme d\'ordine affidabili usa un account SMTP.'), 'admin/settings/mail'],
            [\Alien\Services\Analytics::id() !== '', __('Collega Google Analytics'), __('Scopri da dove arrivano i visitatori e cosa comprano.'), 'admin/analytics'],
            [(bool)(Settings::get('google_verification') || Settings::get('bing_verification')), __('Collega Google e Bing'), __('Verifica il sito e invia la sitemap.'), 'admin/settings/seo'],
        ];
        return array_values(array_filter($items));
    }

    public function dismiss(): Response
    {
        Settings::set('onboard_dismissed', 1);
        return $this->back('admin');
    }

    public function removeDemo(): Response
    {
        foreach (DB::col("SELECT id FROM products WHERE sku LIKE 'DEMO-%'") as $id) {
            Catalog::delete((int)$id);
        }
        foreach (DB::all("SELECT id FROM categories WHERE slug IN ('abbigliamento','accessori','casa')") as $c) {
            if (!(int)DB::val('SELECT COUNT(*) FROM product_categories WHERE category_id = ?', [$c['id']])) {
                Catalog::deleteCategory((int)$c['id']);
            }
        }
        DB::delete('coupons', "code = 'WELCOME10' AND used = 0");
        return $this->back('admin', __('Contenuti dimostrativi rimossi.'));
    }
}
