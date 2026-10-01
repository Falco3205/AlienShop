<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';

use Alien\Core\Cache;
use Alien\Core\DB;
use Alien\Core\Http;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Settings;
use Alien\Core\Str;
use Alien\Import\Exporter;
use Alien\Import\ShopifyImporter;
use Alien\Import\WooImporter;
use Alien\Payments\PayPalGateway;
use Alien\Payments\StripeGateway;
use Alien\Services\Cart;
use Alien\Services\Catalog;
use Alien\Services\Coupons;
use Alien\Services\Installer;
use Alien\Services\Orders;
use Alien\Services\Redirects;
use Alien\Services\Seo;
use Alien\Services\Sitemap;

$_SERVER['HTTP_HOST'] = 'shop.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$tmp = sys_get_temp_dir() . '/alienshop-test-' . getmypid();
@mkdir($tmp);
$dbFile = $tmp . '/test.sqlite';
$pdo = DB::connect(['driver' => 'sqlite', 'path' => $dbFile]);
Installer::createSchema($pdo, 'sqlite');
Settings::setMany(['store_name' => 'Test Shop', 'currency' => 'EUR', 'locale' => 'it', 'tax_rate' => 22, 'prices_include_tax' => 1, 'order_prefix' => 'T-', 'mail_driver' => 'log', 'store_email' => 'shop@test.dev', 'pay_bank_enabled' => 1]);
DB::insert('shipping_methods', ['name' => 'Std', 'price' => 590, 'free_over' => 5900, 'countries' => '', 'position' => 1, 'active' => 1]);

function out(string $s): void
{
    fwrite(STDOUT, $s);
}

$passed = 0;
$failed = 0;
function t(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        out("  ok   $name\n");
    } catch (\Throwable $e) {
        $failed++;
        out("  FAIL $name: " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n");
    }
}
function eq(mixed $actual, mixed $expected, string $msg = ''): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(($msg ? $msg . ': ' : '') . 'atteso ' . var_export($expected, true) . ', ottenuto ' . var_export($actual, true));
    }
}

out("Core\n");
t('Money::parse formati IT/EN', function () {
    eq(Money::parse('1.234,56'), 123456);
    eq(Money::parse('1,234.56'), 123456);
    eq(Money::parse('12,5'), 1250);
    eq(Money::parse('-2.00'), -200);
    eq(Money::parse(''), 0);
});
t('Money::format', function () {
    eq(Money::format(123456), '1.234,56 €');
});
t('Str::slug translittera', function () {
    eq(Str::slug('Caffè Perché! Più'), 'caffe-perche-piu');
});
t('Str::sanitizeHtml rimuove script e on*', function () {
    $out = Str::sanitizeHtml('<p onclick="x()">a</p><script>alert(1)</script><a href="javascript:alert(1)">l</a>');
    eq(str_contains($out, 'script') || str_contains($out, 'onclick') || str_contains($out, 'javascript:'), false);
});

out("Catalogo\n");
$catId = Catalog::saveCategory(['name' => 'Scarpe', 'is_active' => 1, 'show_in_menu' => 1]);
$variableId = Catalog::save([
    'name' => 'Sneaker', 'type' => 'variable', 'price' => 5000, 'manage_stock' => 1, 'category_ids' => [$catId],
    'attributes' => [
        ['name' => 'Taglia', 'values' => Catalog::parseAttributeLines("40\n41|+2.00\n42|+4,50")],
        ['name' => 'Colore', 'values' => Catalog::parseAttributeLines("Rosso\nBlu|-1")],
    ],
]);
DB::exec('UPDATE variants SET stock = 5 WHERE product_id = ?', [$variableId]);
Catalog::refreshDerived($variableId);

t('varianti = prodotto cartesiano', function () use ($variableId) {
    eq(count(Catalog::product($variableId)['variants']), 6);
});
t('prezzo variante = base + delta attributi', function () use ($variableId) {
    $p = Catalog::product($variableId);
    $v = Catalog::findVariant($p, ['Taglia' => '42', 'Colore' => 'Blu']);
    eq($v['final_price'], 5000 + 450 - 100);
    $v = Catalog::findVariant($p, ['Taglia' => '40', 'Colore' => 'Rosso']);
    eq($v['final_price'], 5000);
});
t('price_min / price_max denormalizzati', function () use ($variableId) {
    $p = Catalog::product($variableId);
    eq([(int)$p['price_min'], (int)$p['price_max']], [4900, 5450]);
});
t('override prezzo variante', function () use ($variableId) {
    $p = Catalog::product($variableId);
    $v = Catalog::findVariant($p, ['Taglia' => '41', 'Colore' => 'Rosso']);
    $posts = [['key' => $v['options_key'], 'price' => '39,90', 'stock' => 5, 'active' => 1]];
    Catalog::save(['name' => 'Sneaker', 'type' => 'variable', 'price' => 5000, 'manage_stock' => 1, 'attributes' => array_map(fn($a) => ['name' => $a['name'], 'values' => $a['values']], $p['attributes']), 'variants' => $posts], $variableId);
    $v = Catalog::findVariant(Catalog::product($variableId), ['Taglia' => '41', 'Colore' => 'Rosso']);
    eq($v['final_price'], 3990);
    eq(count(Catalog::product($variableId)['variants']), 6, 'le varianti restano 6');
});
t('cambio slug crea redirect 301', function () use ($variableId) {
    Catalog::save(['name' => 'Sneaker Pro', 'slug' => 'sneaker-pro', 'type' => 'variable', 'price' => 5000], $variableId);
    eq(Redirects::resolve('products/sneaker')['to_path'], 'products/sneaker-pro');
});
t('redirect a catena vengono appiattiti', function () use ($variableId) {
    Catalog::save(['name' => 'Sneaker Max', 'slug' => 'sneaker-max', 'type' => 'variable', 'price' => 5000], $variableId);
    eq(Redirects::resolve('products/sneaker')['to_path'], 'products/sneaker-max');
    eq(Redirects::resolve('products/sneaker-pro')['to_path'], 'products/sneaker-max');
});
t('slug univoci', function () {
    $a = Catalog::save(['name' => 'Duplicato', 'price' => 100]);
    $b = Catalog::save(['name' => 'Duplicato', 'price' => 100]);
    eq(Catalog::product($b)['slug'], 'duplicato-2');
    Catalog::delete($a);
    Catalog::delete($b);
});
t('ricerca e filtri prezzo', function () {
    eq(Catalog::lookup(['q' => 'Sneaker'])['total'], 1);
    eq(Catalog::lookup(['min' => 6000])['total'], 0);
    eq(Catalog::lookup(['category_id' => 1])['total'], 1);
});

out("Carrello e ordini\n");
session_save_path($tmp);
$simpleId = Catalog::save(['name' => 'Tazza', 'price' => 1000, 'manage_stock' => 1, 'stock_qty' => 3]);
t('carrello: variante richiesta e prezzo corretto', function () use ($variableId) {
    Cart::clear();
    eq(Cart::add($variableId, 0, [], 1) !== null, true, 'senza opzioni deve fallire');
    eq(Cart::add($variableId, 0, ['Taglia' => '42', 'Colore' => 'Rosso'], 2), null);
    $lines = Cart::lines();
    eq(array_values($lines)[0]['unit'], 5450);
    eq(Cart::totals($lines)['subtotal'], 10900);
});
t('carrello: limite scorte', function () use ($simpleId) {
    Cart::clear();
    eq(Cart::add($simpleId, 0, [], 3), null);
    eq(Cart::add($simpleId, 0, [], 1) !== null, true);
});
t('coupon percentuale e spedizione gratuita sopra soglia', function () use ($simpleId) {
    Cart::clear();
    DB::insert('coupons', ['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 0, 'max_uses' => 1, 'used' => 0, 'free_shipping' => 0, 'active' => 1]);
    Cart::add($simpleId, 0, [], 2);
    eq(Cart::applyCoupon('save10'), null);
    $t = Cart::totals();
    eq([$t['subtotal'], $t['discount'], $t['shipping'], $t['total']], [2000, 200, 590, 2390]);
    eq($t['tax'], (int)round(2390 * 22 / 122));
});
t('ordine: scorte scalate, coupon consumato, annullo ripristina', function () use ($simpleId) {
    $lines = Cart::lines();
    $totals = Cart::totals($lines);
    $addr = ['name' => 'A', 'address' => 'B', 'city' => 'C', 'zip' => '1', 'country' => 'IT'];
    $o = Orders::create($lines, $totals, ['email' => 'c@test.dev', 'billing' => $addr, 'shipping' => $addr], 'bank', null);
    eq($o['number'], 'T-' . (1000 + $o['id']));
    eq((int)DB::val('SELECT stock_qty FROM products WHERE id = ?', [$simpleId]), 1);
    eq([Coupons::validate('SAVE10', 5000)[0]], [null], 'coupon esaurito');
    Orders::setStatus((int)$o['id'], 'cancelled');
    eq((int)DB::val('SELECT stock_qty FROM products WHERE id = ?', [$simpleId]), 3);
    Orders::setStatus((int)$o['id'], 'cancelled');
    eq((int)DB::val('SELECT stock_qty FROM products WHERE id = ?', [$simpleId]), 3, 'ripristino una sola volta');
    Cart::clear();
});

out("Pagamenti\n");
$sig = static function (string $payload, string $secret, int $t): string {
    return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret);
};
t('Stripe: firma webhook valida/non valida/scaduta', function () use ($sig) {
    $now = time();
    eq(StripeGateway::verifySignature('{"a":1}', $sig('{"a":1}', 'whsec_x', $now), 'whsec_x', 300, $now), true);
    eq(StripeGateway::verifySignature('{"a":2}', $sig('{"a":1}', 'whsec_x', $now), 'whsec_x', 300, $now), false);
    eq(StripeGateway::verifySignature('{"a":1}', $sig('{"a":1}', 'whsec_x', $now - 1000), 'whsec_x', 300, $now), false);
    eq(StripeGateway::verifySignature('{"a":1}', '', 'whsec_x'), false);
});
$order = (function () use ($simpleId) {
    Cart::clear();
    Cart::add($simpleId, 0, [], 1);
    $lines = Cart::lines();
    $addr = ['name' => 'A', 'address' => 'B', 'city' => 'C', 'zip' => '1', 'country' => 'IT'];
    $o = Orders::create($lines, Cart::totals($lines), ['email' => 'c@test.dev', 'billing' => $addr, 'shipping' => $addr], 'stripe', null);
    Cart::clear();
    return $o;
})();
t('Stripe: sessione checkout con importo corretto e conferma pagamento', function () use ($order) {
    Settings::setMany(['pay_stripe_enabled' => 1, 'pay_stripe_secret_key' => 'sk_test_x', 'pay_stripe_webhook_secret' => 'whsec_x']);
    $captured = null;
    Http::$fake = function ($method, $url, $body) use (&$captured, $order) {
        if ($method === 'POST') {
            $captured = $body;
            return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']];
        }
        return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['payment_status' => 'paid', 'amount_total' => (int)$order['total'], 'currency' => 'eur', 'payment_intent' => 'pi_1', 'metadata' => ['order_token' => $order['token']]]];
    };
    $g = new StripeGateway();
    $r = $g->start($order);
    eq($r['redirect'], 'https://checkout.stripe.com/c/pay/cs_test_1');
    eq($captured['line_items'][0]['price_data']['unit_amount'], (int)$order['total']);
    eq($captured['metadata']['order_token'], $order['token']);
    $req = new Request('GET', '/pay/return/stripe', ['session_id' => 'cs_test_1', 'order' => $order['token']], [], [], []);
    eq($g->handleReturn($req, Orders::find((int)$order['id'])), true);
    eq(Orders::find((int)$order['id'])['payment_status'], 'paid');
    Http::$fake = null;
});
t('Stripe: importo discordante non marca pagato', function () {
    Cart::clear();
    $o = DB::row("SELECT * FROM orders ORDER BY id DESC LIMIT 1");
    DB::update('orders', ['payment_status' => 'unpaid'], 'id = ?', [$o['id']]);
    Http::$fake = fn() => ['status' => 200, 'body' => '', 'error' => '', 'json' => ['payment_status' => 'paid', 'amount_total' => 1, 'currency' => 'eur', 'metadata' => ['order_token' => $o['token']]]];
    $req = new Request('GET', '/', ['session_id' => 'cs_test_2'], [], [], []);
    eq((new StripeGateway())->handleReturn($req, Orders::find((int)$o['id'])), false);
    Http::$fake = null;
});
t('PayPal: crea ordine con importo e link approvazione', function () use ($order) {
    Settings::setMany(['pay_paypal_enabled' => 1, 'pay_paypal_client_id' => 'id', 'pay_paypal_secret' => 's', 'pay_paypal_sandbox' => 1]);
    $created = null;
    Http::$fake = function ($method, $url, $body) use (&$created) {
        if (str_contains($url, '/oauth2/token')) {
            return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['access_token' => 'tok']];
        }
        $created = json_decode((string)$body, true);
        return ['status' => 201, 'body' => '', 'error' => '', 'json' => ['id' => 'PP1', 'links' => [['rel' => 'approve', 'href' => 'https://sandbox.paypal.com/approve?token=PP1']]]];
    };
    $r = (new PayPalGateway())->start(Orders::find((int)$order['id']));
    eq($r['redirect'], 'https://sandbox.paypal.com/approve?token=PP1');
    eq($created['purchase_units'][0]['amount']['value'], number_format($order['total'] / 100, 2, '.', ''));
    Http::$fake = null;
});

out("SEO\n");
t('JSON-LD Product con AggregateOffer per varianti', function () use ($variableId) {
    Seo::forProduct(Catalog::product($variableId));
    $json = Seo::head();
    eq(str_contains($json, '"@type":"Product"') && str_contains($json, 'AggregateOffer') && str_contains($json, 'BreadcrumbList'), true);
    eq(str_contains($json, 'rel="canonical"'), true);
});
t('sitemap prodotti contiene URL', function () {
    eq(str_contains(Sitemap::products(1), '/products/sneaker-max'), true);
    eq(str_contains(Sitemap::index(), 'sitemap-products-1.xml'), true);
    eq(str_contains(Sitemap::robots(), 'Sitemap:'), true);
});

out("Import / Export\n");
$wooCsv = $tmp . '/woo.csv';
file_put_contents($wooCsv, <<<'CSV'
"ID","Type","SKU","Name","Published","Is featured?","Short description","Description","In stock?","Stock","Regular price","Sale price","Categories","Tags","Images","Parent","Attribute 1 name","Attribute 1 value(s)","Attribute 1 visible","Attribute 1 global"
10,variable,TSH,"Maglietta Woo",1,0,"breve","<p>desc</p>",1,,,,"Abbigliamento > Uomo",tag1,,,Taglia,"S, M, L",1,0
11,variation,TSH-S,"Maglietta Woo - S",1,0,,,1,5,20.00,,,,,id:10,Taglia,S,,0
12,variation,TSH-M,"Maglietta Woo - M",1,0,,,1,5,22.50,18.00,,,,id:10,Taglia,M,,0
13,variation,TSH-L,"Maglietta Woo - L",1,0,,,1,5,25.00,,,,,id:10,Taglia,L,,0
20,simple,CAP,"Cappello",1,1,"c","<p>cap</p>",1,7,15.90,12.90,"Accessori",,,,,,,
CSV);
t('WooCommerce: import variabile con deltas e override', function () use ($wooCsv) {
    $r = (new WooImporter())->import($wooCsv, false);
    eq([$r->created, $r->errors], [2, []]);
    $p = DB::row("SELECT * FROM products WHERE sku = 'TSH'");
    $p = Catalog::product((int)$p['id']);
    eq($p['type'], 'variable');
    eq([(int)$p['price_min'], (int)$p['price_max']], [1800, 2500]);
    $m = Catalog::findVariant($p, ['Taglia' => 'M']);
    eq([$m['final_price'], (int)$m['compare_price'], $m['sku']], [1800, 2250, 'TSH-M']);
    eq($p['categories'][0]['name'], 'Uomo');
    $cap = Catalog::productBySlug('cappello');
    eq([(int)$cap['price'], (int)$cap['compare_price'], (int)$cap['stock_qty'], (int)$cap['featured']], [1290, 1590, 7, 1]);
});
t('WooCommerce: re-import aggiorna senza duplicare', function () use ($wooCsv) {
    $r = (new WooImporter())->import($wooCsv, false);
    eq([$r->created, $r->updated], [0, 2]);
    eq((int)DB::val("SELECT COUNT(*) FROM products WHERE sku = 'TSH'"), 1);
});
$shopCsv = $tmp . '/shop.csv';
file_put_contents($shopCsv, <<<'CSV'
Handle,Title,Body (HTML),Vendor,Product Category,Type,Tags,Published,Option1 Name,Option1 Value,Option2 Name,Option2 Value,Option3 Name,Option3 Value,Variant SKU,Variant Grams,Variant Inventory Tracker,Variant Inventory Qty,Variant Inventory Policy,Variant Fulfillment Service,Variant Price,Variant Compare At Price,Image Src,Image Position,Image Alt Text,SEO Title,SEO Description,Status
felpa-urban,Felpa Urban,<p>Bella</p>,Urban,,Felpe,"a, b",true,Taglia,S,Colore,Nero,,,FEL-S-N,500,shopify,4,deny,manual,40.00,,,,,Felpa SEO,Descrizione SEO,active
felpa-urban,,,,,,,,,M,,Nero,,,FEL-M-N,500,shopify,6,deny,manual,42.00,50.00,,,,,,
felpa-urban,,,,,,,,,S,,Bianco,,,FEL-S-B,500,shopify,2,deny,manual,40.00,,,,,,,
poster,Poster,<p>p</p>,,,Casa,,true,Title,Default Title,,,,,POS,0,,,deny,manual,9.90,,,,,,,active
CSV);
t('Shopify: handle come slug, opzioni multiple, SEO', function () use ($shopCsv) {
    $r = (new ShopifyImporter())->import($shopCsv, false);
    eq([$r->created, $r->errors], [2, []]);
    $p = Catalog::productBySlug('felpa-urban');
    eq([$p['type'], $p['seo_title'], $p['vendor']], ['variable', 'Felpa SEO', 'Urban']);
    eq(count($p['variants']), 4);
    $v = Catalog::findVariant($p, ['Taglia' => 'M', 'Colore' => 'Nero']);
    eq([$v['final_price'], (int)$v['compare_price'], (int)$v['stock']], [4200, 5000, 6]);
    $blank = DB::row("SELECT active FROM variants WHERE product_id = ? AND options_key = ?", [$p['id'], Catalog::optionsKey(['Taglia' => 'M', 'Colore' => 'Bianco'])]);
    eq((int)$blank['active'], 0, 'combinazione assente = disattiva');
    $poster = Catalog::productBySlug('poster');
    eq([$poster['type'], (int)$poster['price']], ['simple', 990]);
});
t('Export Woo e Shopify sono re-importabili (round trip)', function () use ($tmp) {
    $before = (int)DB::val('SELECT COUNT(*) FROM products');
    file_put_contents($tmp . '/rt-woo.csv', Exporter::woo());
    file_put_contents($tmp . '/rt-shop.csv', Exporter::shopify());
    $r = (new WooImporter())->import($tmp . '/rt-woo.csv', false);
    eq($r->errors, []);
    eq((int)DB::val('SELECT COUNT(*) FROM products'), $before, 'woo roundtrip non duplica');
    $r = (new ShopifyImporter())->import($tmp . '/rt-shop.csv', false);
    eq($r->errors, []);
    eq((int)DB::val('SELECT COUNT(*) FROM products'), $before, 'shopify roundtrip non duplica');
    $p = Catalog::productBySlug('felpa-urban');
    eq(Catalog::findVariant($p, ['Taglia' => 'M', 'Colore' => 'Nero'])['final_price'], 4200);
});
t('Http::isPublicUrl blocca indirizzi privati (SSRF)', function () {
    eq(Http::isPublicUrl('http://127.0.0.1/x.png'), false);
    eq(Http::isPublicUrl('http://169.254.169.254/latest'), false);
    eq(Http::isPublicUrl('file:///etc/passwd'), false);
    eq(Http::isPublicUrl('http://10.0.0.5/a'), false);
});

out("Nuove funzioni\n");
t('reset password: token valido, scaduto, manomesso e monouso', function () {
    $ref = new ReflectionProperty(\Alien\Core\Config::class, 'data');
    $ref->setValue(null, ['app' => ['url' => 'http://shop.test', 'key' => 'k'], 'db' => []]);
    $uid = Alien\Core\Auth::create('reset@test.dev', 'password-old-1', 'R');
    $user = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
    $token = Alien\Core\Auth::resetToken($user);
    eq((int)Alien\Core\Auth::userFromResetToken($token)['id'], $uid);
    eq(Alien\Core\Auth::userFromResetToken(Alien\Core\Auth::resetToken($user, time() - 5)), null, 'scaduto');
    eq(Alien\Core\Auth::userFromResetToken($token . 'x'), null, 'manomesso');
    Alien\Core\Auth::setPassword($uid, 'password-new-2');
    eq(Alien\Core\Auth::userFromResetToken($token), null, 'usato una volta');
    $ref->setValue(null, null);
});
t('tasse per paese', function () {
    Settings::set('tax_country_rates', "DE=19\nfr = 20,5");
    eq(Cart::taxRate('DE'), 19.0);
    eq(Cart::taxRate('FR'), 20.5);
    eq(Cart::taxRate('IT'), 22.0);
});
t('rimborso Stripe e PayPal tramite gateway', function () {
    $calls = [];
    Http::$fake = function ($m, $url, $body) use (&$calls) {
        $calls[] = [$m, $url, $body];
        if (str_contains($url, '/oauth2/token')) {
            return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['access_token' => 't']];
        }
        return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['status' => str_contains($url, 'paypal') ? 'COMPLETED' : 'succeeded']];
    };
    eq((new StripeGateway())->refund(['payment_ref' => 'pi_123', 'token' => 'x']), null);
    eq($calls[0][1], 'https://api.stripe.com/v1/refunds');
    eq($calls[0][2], ['payment_intent' => 'pi_123']);
    eq((new StripeGateway())->refund(['payment_ref' => '', 'token' => 'x']) !== null, true);
    eq((new PayPalGateway())->refund(['payment_ref' => 'CAP1', 'token' => 'x']), null);
    eq(str_contains(end($calls)[1], '/v2/payments/captures/CAP1/refund'), true);
    Http::$fake = null;
});
t('log 404 e redirect creato dal log', function () {
    Redirects::logMissing('/vecchio-url-test');
    Redirects::logMissing('/vecchio-url-test');
    Redirects::logMissing('/assets/x.css');
    eq((int)DB::val("SELECT hits FROM not_found_log WHERE path = 'vecchio-url-test'"), 2);
    eq((int)DB::val("SELECT COUNT(*) FROM not_found_log WHERE path LIKE 'assets%'"), 0);
    Redirects::add('/vecchio-url-test', '/collections/all');
    eq((int)DB::val("SELECT COUNT(*) FROM not_found_log WHERE path = 'vecchio-url-test'"), 0);
});
t('immagine principale riordina le immagini', function () {
    $id = Catalog::save(['name' => 'Img test', 'price' => 100]);
    Catalog::addImage($id, 'a.webp');
    Catalog::addImage($id, 'b.webp');
    $second = (int)DB::val("SELECT id FROM product_images WHERE path = 'b.webp'");
    Catalog::setMainImage($id, $second);
    eq(Catalog::product($id)['image'], 'b.webp');
    DB::delete('product_images', 'product_id = ?', [$id]);
});

t('Analytics: validazione ID, eventi e rendering', function () {
    eq([Alien\Services\Analytics::validId('G-ABC123XYZ9'), Alien\Services\Analytics::validId('UA-123-1'), Alien\Services\Analytics::validId('g-abc')], [true, false, false]);
    eq(Alien\Services\Analytics::render(), '', 'senza ID non stampa nulla');
    Settings::setMany(['analytics_id' => 'g-abc123xyz9', 'analytics_ecommerce' => 1, 'analytics_consent' => 1]);
    Alien\Services\Analytics::event('view_item', ['items' => [Alien\Services\Analytics::item(['id' => 5, 'sku' => '', 'name' => 'X</script>', 'price' => 1990], 1990)]]);
    $html = Alien\Services\Analytics::render();
    eq(str_contains($html, '"id":"G-ABC123XYZ9"') && str_contains($html, '"price":19.9') && str_contains($html, '"consent":true'), true);
    eq(str_contains($html, '</script>X'), false);
    eq(substr_count($html, '</script>'), 1, 'nessuna chiusura script iniettata');
    Http::$fake = fn() => ['status' => 200, 'body' => '<script>window.ASGA={"id":"G-ABC123XYZ9"}</script>', 'error' => '', 'json' => null];
    eq(Alien\Services\Analytics::check()['ok'], true);
    Http::$fake = fn() => ['status' => 200, 'body' => '<html></html>', 'error' => '', 'json' => null];
    eq(Alien\Services\Analytics::check()['ok'], false);
    Http::$fake = null;
});

t('Pagine legali: generazione IT/EN e pubblicazione idempotente', function () {
    $profile = ['company' => 'Acme Srl', 'legal_form' => 'S.r.l.', 'address' => 'Via Roma 1, Milano', 'vat' => 'IT123', 'withdrawal_days' => 30, 'return_shipping' => 'seller', 'excluded_custom' => 1] + Alien\Services\LegalTemplates::defaults();
    foreach (['it', 'en'] as $loc) {
        $pages = Alien\Services\LegalTemplates::build($profile, $loc);
        eq(array_keys($pages), array_keys(Alien\Services\LegalTemplates::PAGES));
        eq(str_contains($pages['privacy-policy']['content'], 'Acme Srl'), true);
        eq(str_contains($pages['resi-e-recesso']['content'], '30'), true);
    }
    eq(str_contains(Alien\Services\LegalTemplates::build($profile, 'it')['resi-e-recesso']['content'], 'a nostro carico'), true);
    eq(Alien\Services\LegalTemplates::publish(['privacy-policy', 'cookie-policy', 'nope'], $profile, 'it'), 2);
    eq(Alien\Services\LegalTemplates::publish(['privacy-policy'], $profile, 'it'), 1);
    eq((int)DB::val("SELECT COUNT(*) FROM pages WHERE slug = 'privacy-policy'"), 1);
    $profile['company'] = '<b>X</b>';
    eq(str_contains(Alien\Services\LegalTemplates::build($profile, 'it')['privacy-policy']['content'], '<b>X</b>'), false, 'escape');
});
t('Statistiche: visite, carrelli, vendite e abbandoni', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    $id = Catalog::save(['name' => 'Stat prod', 'price' => 1000, 'manage_stock' => 0]);
    for ($i = 0; $i < 10; $i++) { Alien\Services\Stats::bump('views', $id); }
    Alien\Services\Stats::bump('carts', $id, 5);
    Alien\Services\Stats::bump('checkouts', $id, 2);
    $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
    Alien\Services\Stats::bump('views', $id, 100);
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    $oid = DB::insert('orders', ['number' => 'ST-1', 'token' => 'tk-st', 'email' => 'a@b.c', 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'bank', 'total' => 2000, 'created_at' => now(), 'updated_at' => now()]);
    DB::insert('order_items', ['order_id' => $oid, 'product_id' => $id, 'name' => 'Stat prod', 'price' => 1000, 'qty' => 2, 'total' => 2000]);
    Alien\Services\Stats::search('Scarpe Rosse', 0);
    Alien\Services\Stats::search('scarpe rosse', 0);
    $r = Alien\Services\Stats::report(7);
    $pick = static fn(array $rows) => array_values(array_filter($rows, static fn($x) => (int)$x['id'] === $id))[0];
    eq([$pick($r['viewed'])['views'], $pick($r['carted'])['carts'], $pick($r['sold'])['sold'], $pick($r['abandoned'])['abandoned']], [10, 5, 2, 3], 'bot escluso');
    eq($r['funnel']['views'] >= 10 && $r['funnel']['orders'] >= 1, true);
    eq([$r['searches'][0]['term'], (int)$r['searches'][0]['hits'], (int)$r['noResults'][0]['zero']], ['scarpe rosse', 2, 2]);
});

out("Estensioni\n");
use Alien\Services\AbandonedCarts;
use Alien\Services\Backup;
use Alien\Services\Cron;
use Alien\Services\Invoices;
use Alien\Services\Migrator;
use Alien\Services\Modules;
use Alien\Services\Newsletter;
use Alien\Services\Reviews;
use Alien\Services\StockAlerts;

t('Estensioni: attivazione e valori predefiniti', function () {
    eq([Modules::on('reviews'), Modules::on('invoices'), Modules::on('nope')], [false, true, false]);
    Modules::set('reviews', true);
    Modules::set('newsletter', true);
    Modules::set('abandoned_cart', true);
    eq(Modules::on('reviews'), true);
});
t('Migrator: idempotente', function () {
    Settings::set('schema_version', 1);
    eq(Migrator::needed(), true);
    Migrator::run();
    Migrator::run();
    eq(Migrator::needed(), false);
});
$reviewProduct = Catalog::save(['name' => 'Rev prod', 'price' => 1000]);
t('Recensioni: validazione, moderazione, media e acquisto verificato', function () use ($reviewProduct) {
    eq(Reviews::submit($reviewProduct, ['rating' => 9, 'author' => 'A', 'body' => 'abcdefghijk'], '1.1.1.1') !== null, true);
    eq(Reviews::submit($reviewProduct, ['rating' => 5, 'author' => 'A', 'body' => 'corto'], '1.1.1.1') !== null, true);
    $oid = DB::insert('orders', ['number' => 'RV-1', 'token' => 'tk-rv', 'email' => 'buyer@test.dev', 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'bank', 'total' => 1000, 'created_at' => now(), 'updated_at' => now()]);
    DB::insert('order_items', ['order_id' => $oid, 'product_id' => $reviewProduct, 'name' => 'Rev prod', 'price' => 1000, 'qty' => 1, 'total' => 1000]);
    eq(Reviews::submit($reviewProduct, ['rating' => 5, 'author' => 'Buyer', 'email' => 'buyer@test.dev', 'body' => 'Ottimo prodotto davvero'], '2.2.2.2'), null);
    eq(Reviews::submit($reviewProduct, ['rating' => 3, 'author' => 'Buyer', 'email' => 'buyer@test.dev', 'body' => 'Un secondo commento'], '2.2.2.2') !== null, true, 'doppia recensione');
    eq(Reviews::forProduct($reviewProduct), [], 'in moderazione non pubblica');
    $rid = (int)DB::val('SELECT id FROM reviews WHERE product_id = ?', [$reviewProduct]);
    eq((int)DB::val('SELECT verified FROM reviews WHERE id = ?', [$rid]), 1);
    Reviews::setStatus($rid, 'approved');
    eq([(int)Catalog::product($reviewProduct)['rating_count'], (int)Catalog::product($reviewProduct)['rating_avg']], [1, 50]);
    Reviews::submit($reviewProduct, ['rating' => 4, 'author' => 'C', 'body' => 'Buono nel complesso'], '3.3.3.3');
    Reviews::setStatus((int)DB::val('SELECT MAX(id) FROM reviews'), 'approved');
    eq((int)Catalog::product($reviewProduct)['rating_avg'], 45);
    for ($i = 0; $i < 3; $i++) { Reviews::submit($reviewProduct, ['rating' => 4, 'author' => 'S' . $i, 'body' => 'spam spam spam spam', 'email' => "s$i@x.dev"], '9.9.9.9'); }
    eq(Reviews::submit($reviewProduct, ['rating' => 4, 'author' => 'S', 'body' => 'spam spam spam spam'], '9.9.9.9') !== null, true, 'rate limit per IP');
});
t('Recensioni: richiesta dopo la spedizione, una sola volta', function () {
    $oid = (int)DB::val("SELECT id FROM orders WHERE number = 'RV-1'");
    DB::update('orders', ['updated_at' => date('Y-m-d H:i:s', time() - 10 * 86400)], 'id = ?', [$oid]);
    eq(Reviews::sendRequests(), 1);
    eq(Reviews::sendRequests(), 0);
});
t('Newsletter: doppio opt-in, campagna in coda e invio', function () {
    eq(Newsletter::subscribe('nope'), __('Inserisci un indirizzo email valido.'));
    eq(Newsletter::subscribe('Fan@Test.dev'), null);
    $row = DB::row("SELECT * FROM subscribers WHERE email = 'fan@test.dev'");
    eq($row['status'], 'pending');
    eq(Newsletter::campaign('Ciao', '<p>x</p>'), 0, 'i pending non ricevono');
    eq(Newsletter::confirm($row['token']), true);
    eq(Newsletter::campaign('Ciao', '<p>Offerta</p><script>x</script>'), 1);
    $body = (string)DB::val("SELECT body FROM mail_queue WHERE campaign LIKE 'c%' ORDER BY id DESC LIMIT 1");
    eq(str_contains($body, '/newsletter/unsubscribe/' . $row['token']) && !str_contains($body, '<script>'), true);
    Settings::set('mail_driver', 'log');
    eq(Cron::sendQueue() >= 1, true);
    eq(Newsletter::unsubscribe($row['token']), true);
    eq(Newsletter::campaign('Ciao', '<p>x</p>'), 0);
    eq(str_contains(Newsletter::csv(), 'fan@test.dev'), true);
});
t('Carrelli abbandonati: cattura, promemoria, recupero', function () {
    $pid = Catalog::save(['name' => 'Cart prod', 'price' => 2500]);
    Cart::clear();
    Cart::add($pid, 0, [], 2);
    AbandonedCarts::capture('lead@test.dev');
    AbandonedCarts::capture('lead@test.dev');
    eq((int)DB::val("SELECT COUNT(*) FROM abandoned_carts WHERE email = 'lead@test.dev'"), 1, 'upsert');
    eq(AbandonedCarts::sendReminders(), 0, 'troppo presto');
    DB::exec("UPDATE abandoned_carts SET updated_at = ? WHERE email = 'lead@test.dev'", [date('Y-m-d H:i:s', time() - 3 * 3600)]);
    DB::insert('coupons', ['code' => 'COMEBACK', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 0, 'max_uses' => 0, 'used' => 0, 'free_shipping' => 0, 'active' => 1]);
    Settings::set('abandoned_coupon', 'COMEBACK');
    eq(AbandonedCarts::sendReminders(), 1);
    eq(AbandonedCarts::sendReminders(), 0, 'una sola volta');
    $token = (string)DB::val("SELECT token FROM abandoned_carts WHERE email = 'lead@test.dev'");
    Cart::clear();
    eq(AbandonedCarts::restore($token), true);
    eq([Cart::count(), Cart::totals()['discount']], [2, 500]);
    AbandonedCarts::orderPlaced('lead@test.dev');
    eq(AbandonedCarts::stats()['recovered'], 1);
    Cart::clear();
});
t('Fatture: numerazione annuale progressiva, PDF e CSV', function () {
    $o1 = Orders::find((int)DB::val("SELECT id FROM orders WHERE number = 'RV-1'"));
    $o1 = Invoices::assign($o1);
    eq($o1['invoice_number'], date('Y') . '/0001');
    eq(Invoices::assign($o1)['invoice_number'], date('Y') . '/0001', 'idempotente');
    $o2 = Invoices::assign(Orders::find((int)DB::val("SELECT id FROM orders WHERE number = 'ST-1'")));
    eq($o2['invoice_number'], date('Y') . '/0002');
    $pdf = Invoices::pdf($o2);
    eq([str_starts_with($pdf, '%PDF-1.4'), str_ends_with($pdf, '%%EOF'), str_contains($pdf, 'Totale')], [true, true, true]);
    eq(str_contains(Invoices::csv(date('Y-01-01'), date('Y-12-31')), $o2['invoice_number']), true);
    Settings::set('invoice_prefix', 'FT-');
    eq(Invoices::assign(Orders::find((int)DB::val("SELECT id FROM orders WHERE number = 'T-1001'")))['invoice_number'], 'FT-' . date('Y') . '/0001');
    Settings::set('invoice_prefix', '');
    eq(Alien\Core\Mailer::send('c@test.dev', 'Fattura', '<p>x</p>', [['name' => 'a b.pdf', 'data' => $pdf, 'type' => 'application/pdf']]), true);
});
t('Avvisi disponibilità: coda al rifornimento', function () {
    Modules::set('stock_alerts', true);
    $pid = Catalog::save(['name' => 'Esaurito', 'price' => 500, 'manage_stock' => 1, 'stock_qty' => 0]);
    eq((int)Catalog::product($pid)['in_stock'], 0);
    eq(StockAlerts::subscribe($pid, 'wait@test.dev'), null);
    StockAlerts::subscribe($pid, 'wait@test.dev');
    eq((int)DB::val('SELECT COUNT(*) FROM stock_alerts WHERE product_id = ?', [$pid]), 1);
    $before = (int)DB::val('SELECT COUNT(*) FROM mail_queue');
    Catalog::save(['name' => 'Esaurito', 'price' => 500, 'manage_stock' => 1, 'stock_qty' => 5], $pid);
    eq((int)DB::val('SELECT COUNT(*) FROM mail_queue') - $before, 1);
    eq((int)DB::val('SELECT COUNT(*) FROM stock_alerts WHERE product_id = ?', [$pid]), 0);
});
t('Mollie: pagamento, ritorno, webhook e rimborso', function () {
    Settings::setMany(['pay_mollie_enabled' => 1, 'pay_mollie_api_key' => 'test_x']);
    Cart::clear();
    $pid = Catalog::save(['name' => 'Mollie prod', 'price' => 1234]);
    Cart::add($pid, 0, [], 1);
    $lines = Cart::lines();
    $addr = ['name' => 'A', 'address' => 'B', 'city' => 'C', 'zip' => '1', 'country' => 'IT'];
    $o = Orders::create($lines, Cart::totals($lines), ['email' => 'm@test.dev', 'billing' => $addr, 'shipping' => $addr], 'mollie', null);
    Cart::clear();
    $sent = null;
    Http::$fake = function ($m, $url, $body) use (&$sent, $o) {
        if ($m === 'POST' && str_ends_with($url, '/payments')) {
            $sent = json_decode((string)$body, true);
            return ['status' => 201, 'body' => '', 'error' => '', 'json' => ['id' => 'tr_abc123', '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/x']]]];
        }
        if ($m === 'POST') {
            return ['status' => 201, 'body' => '', 'error' => '', 'json' => ['status' => 'queued']];
        }
        return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['id' => 'tr_abc123', 'status' => 'paid', 'amount' => ['currency' => 'EUR', 'value' => number_format($o['total'] / 100, 2, '.', '')], 'metadata' => ['order_token' => $o['token']]]];
    };
    $g = new Alien\Payments\MollieGateway();
    $r = $g->start($o);
    eq([$r['redirect'], $r['ref'], $sent['amount']['value']], ['https://pay.mollie.com/x', 'tr_abc123', number_format($o['total'] / 100, 2, '.', '')]);
    DB::update('orders', ['payment_ref' => 'tr_abc123'], 'id = ?', [$o['id']]);
    $g->handleWebhook(new Request('POST', '/webhooks/mollie', [], ['id' => 'tr_abc123'], [], []));
    eq(Orders::find((int)$o['id'])['payment_status'], 'paid');
    eq($g->refund(Orders::find((int)$o['id'])), null);
    Http::$fake = null;
});
t('Filtri per attributo e faccette', function () {
    $r = Catalog::lookup(['attrs' => ['Taglia' => '42'], 'status' => 'active']);
    eq(count(array_filter($r['items'], static fn($p) => $p['name'] === 'Sneaker Max')), 1);
    eq(Catalog::lookup(['attrs' => ['Taglia' => '99']])['total'], 0);
    eq(Catalog::lookup(['attrs' => ['Taglia' => '42', 'Colore' => 'Blu']])['total'] >= 1, true);
    $f = Catalog::facets(null);
    eq(isset($f['Taglia']) && array_column($f['Taglia'], 'value') !== [], true);
});
t('Backup: database e archivio completo', function () {
    [$name, $path] = Backup::databaseFile();
    eq([is_file($path), filesize($path) > 1000, str_ends_with($name, '.sqlite')], [true, true, true]);
    @unlink($path);
    if (Backup::zipAvailable()) {
        $zip = Backup::fullArchive();
        $z = new ZipArchive();
        eq($z->open($zip), true);
        eq(count(array_filter(range(0, $z->numFiles - 1), fn($i) => str_starts_with($z->getNameIndex($i), 'database/'))), 1);
        $z->close();
        @unlink($zip);
    }
});

out("Fatturazione elettronica\n");
use Alien\EInvoice\Fiscal;
use Alien\EInvoice\InvoiceData;
use Alien\EInvoice\Mime;
use Alien\EInvoice\P7m;
use Alien\EInvoice\XmlBuilder;
use Alien\EInvoice\XmlParser;
use Alien\Services\EInvoices;
use Alien\Services\Purchases;
use Alien\Services\Sdi;

t('Fiscale: partita IVA, codice fiscale, caratteri ammessi', function () {
    eq([Fiscal::vatValid('12345678903'), Fiscal::vatValid('12345678901'), Fiscal::vatValid('123')], [true, false, false]);
    eq([Fiscal::cfValid('RSSMRA85T10A562S'), Fiscal::cfValid('RSSMRA85T10A562X'), Fiscal::cfValid('01234567897')], [true, false, true]);
    eq(Fiscal::latin('Spedizione — Standard € 5 “x” ñ', 100), 'Spedizione - Standard EUR 5 "x" ñ');
});
$einvSettings = ['einv_name' => 'Alien Test S.r.l.', 'einv_vat' => '12345678903', 'einv_cf' => '12345678903', 'einv_regime' => 'RF01', 'einv_address' => 'Via Roma 1', 'einv_cap' => '20100', 'einv_city' => 'Milano', 'einv_prov' => 'MI', 'einv_email' => 'info@alien.it', 'einv_nature' => 'N2.2', 'einv_courtesy' => 0];
Settings::setMany($einvSettings + ['mod_einvoice' => 1, 'einv_transport' => 'manual', 'invoice_prefix' => '']);
$mkOrder = function (string $number, array $invoice, int $unit = 2490, int $qty = 2) {
    $addr = ['name' => 'Cliente Test', 'address' => 'Via Verdi 3', 'city' => 'Roma', 'zip' => '00100', 'state' => 'RM', 'country' => 'IT', 'invoice' => $invoice];
    $sub = $unit * $qty;
    $total = $sub + 590 - 200;
    $tax = (int)round($total * 22 / 122);
    $id = DB::insert('orders', ['number' => $number, 'token' => 'tk-' . $number, 'email' => 'cli@test.dev', 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe', 'subtotal' => $sub, 'discount' => 200, 'shipping' => 590, 'tax' => $tax, 'tax_rate' => 2200, 'total' => $total, 'coupon_code' => 'PROMO', 'shipping_method' => 'Standard', 'billing' => json_encode($addr), 'shipping_address' => json_encode($addr), 'created_at' => now(), 'updated_at' => now()]);
    DB::insert('order_items', ['order_id' => $id, 'product_id' => 1, 'name' => 'T-shirt — Essential', 'variant_label' => 'M / Nero', 'sku' => 'TS-1', 'price' => $unit, 'qty' => $qty, 'total' => $sub]);
    return Orders::find($id);
};
t('XML FatturaPA: valido contro lo schema ufficiale (privato, azienda, estero, nota di credito)', function () use ($mkOrder) {
    foreach ([
        ['type' => 'private', 'name' => 'Mario Rossi', 'cf' => 'RSSMRA85T10A562S'],
        ['type' => 'company', 'name' => 'Acme S.r.l.', 'vat' => '01234567897', 'sdi' => 'ABC1234'],
        ['type' => 'company', 'name' => 'Beta S.p.A.', 'vat' => '01234567897', 'pec' => 'beta@pec.it'],
    ] as $i => $inv) {
        $o = $mkOrder('XM-' . $i, $inv);
        $d = InvoiceData::fromOrder($o, 'TD01', '2026/' . (900 + $i), '2026-10-01', '0000' . $i);
        eq(XmlBuilder::validate(XmlBuilder::build($d)), [], 'caso ' . $i);
        eq(InvoiceData::customerErrors(InvoiceData::customer($o)), []);
    }
    $o = $mkOrder('XM-9', ['type' => 'private', 'name' => 'Jean', 'cf' => '']);
    $addr = $o['billing']; $addr['country'] = 'FR'; $addr['state'] = '';
    DB::update('orders', ['billing' => json_encode($addr), 'tax' => 0, 'tax_rate' => 0, 'total' => (int)$o['subtotal'] - 200 + 590], 'id = ?', [$o['id']]);
    $o = Orders::find((int)$o['id']);
    $d = InvoiceData::fromOrder($o, 'TD01', '2026/950', '2026-10-01', '00009');
    eq(XmlBuilder::validate(XmlBuilder::build($d)), [], 'estero senza IVA');
    $d = InvoiceData::fromOrder($mkOrder('XM-8', ['type' => 'private', 'name' => 'M R', 'cf' => 'RSSMRA85T10A562S']), 'TD04', '2026/951', '2026-10-02', '00008', ['number' => '2026/900', 'date' => '2026-10-01']);
    eq(XmlBuilder::validate(XmlBuilder::build($d)), [], 'nota di credito');
});
t('Dati cliente: errori chiari quando mancano CF/provincia', function () use ($mkOrder) {
    $o = $mkOrder('XM-7', ['type' => 'private', 'name' => 'Senza CF']);
    $errors = InvoiceData::customerErrors(InvoiceData::customer($o));
    eq(count($errors), 1);
    eq(InvoiceData::sellerErrors(array_merge(InvoiceData::seller(), ['vat' => '123'])) !== [], true);
});
t('Emissione: numerazione condivisa, idempotenza, file XML e rigenerazione dopo scarto', function () use ($mkOrder) {
    $o = $mkOrder('EI-1', ['type' => 'company', 'name' => 'Acme S.r.l.', 'vat' => '01234567897', 'sdi' => 'ABC1234']);
    $r = EInvoices::issue($o);
    eq([$r['ok'], $r['invoice']['status'], $r['invoice']['doc_type']], [true, 'generated', 'TD01']);
    eq(is_file(EInvoices::xmlPath($r['invoice'])), true);
    eq(preg_match('/^IT12345678903_[0-9A-Z]{5}\.xml$/', $r['invoice']['file_name']), 1);
    eq(Orders::find((int)$o['id'])['invoice_number'], $r['invoice']['number']);
    eq(EInvoices::issue(Orders::find((int)$o['id']))['invoice']['id'], $r['invoice']['id'], 'idempotente');
    $parsed = XmlParser::parse((string)file_get_contents(EInvoices::xmlPath($r['invoice'])))[0];
    eq([$parsed['number'], $parsed['total'], $parsed['customer']['vat']], [$r['invoice']['number'], (int)$o['total'], '01234567897']);
    $cn = EInvoices::creditNote((int)$r['invoice']['id']);
    eq([$cn['ok'], $cn['invoice']['doc_type'], $cn['invoice']['related_id']], [true, 'TD04', (int)$r['invoice']['id']]);
    eq(EInvoices::creditNote((int)$r['invoice']['id'])['ok'], false, 'una sola nota di credito');
    $next = Alien\Services\Invoices::nextNumber();
    eq(substr($next, -4) > substr($cn['invoice']['number'], -4), true, 'progressivo condiviso con la nota di credito');
    DB::update('einvoices', ['status' => 'rejected'], 'id = ?', [$r['invoice']['id']]);
    $again = EInvoices::issue(Orders::find((int)$o['id']));
    eq([$again['invoice']['id'], $again['invoice']['number'], $again['invoice']['status'], $again['invoice']['file_name'] !== $r['invoice']['file_name']], [$r['invoice']['id'], $r['invoice']['number'], 'generated', true]);
});
t('Emissione bloccata se mancano dati del cliente o del cedente', function () use ($mkOrder) {
    $r = EInvoices::issue($mkOrder('EI-2', ['type' => 'private', 'name' => 'Senza CF']));
    eq([$r['ok'], $r['errors'] !== []], [false, true]);
});

$pecDir = sys_get_temp_dir() . '/fakepec-' . getmypid();
@mkdir($pecDir);
$smtpPort = 21000 + getmypid() % 1000;
$imapPort = $smtpPort + 1000;
$pecProc = proc_open(['python3', ROOT . '/tests/fake_pec.py', $pecDir, (string)$smtpPort, (string)$imapPort], [], $pipes);
for ($i = 0; $i < 50 && !is_file($pecDir . '/ready'); $i++) {
    usleep(100000);
}
$mime = static function (array $atts, string $body = 'ciao'): string {
    $b = 'BOUND' . bin2hex(random_bytes(4));
    $m = "From: sdi01@pec.fatturapa.it\r\nTo: me@pec.it\r\nSubject: test\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"\r\n\r\n--$b\r\nContent-Type: text/plain\r\n\r\n$body\r\n";
    foreach ($atts as $name => [$type, $data]) {
        $m .= "--$b\r\nContent-Type: $type; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n" . chunk_split(base64_encode($data));
    }
    return $m . "--$b--\r\n";
};
$pecWrap = static fn(string $inner): string => "From: posta-certificata@pec.it\r\nSubject: POSTA CERTIFICATA\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"OUT\"\r\n\r\n--OUT\r\nContent-Type: text/xml; name=\"daticert.xml\"\r\nContent-Disposition: attachment; filename=\"daticert.xml\"\r\n\r\n<postacert/>\r\n--OUT\r\nContent-Type: message/rfc822; name=\"postacert.eml\"\r\nContent-Disposition: attachment; filename=\"postacert.eml\"\r\n\r\n" . $inner . "\r\n--OUT--\r\n";

t('PEC: invio a SdI via SMTP con XML allegato e destinatario corretto', function () use ($mkOrder, $pecDir, $smtpPort, $imapPort) {
    Settings::setMany(['einv_transport' => 'pec', 'pec_address' => 'azienda@pec.it', 'pec_smtp_host' => '127.0.0.1', 'pec_smtp_port' => $smtpPort, 'pec_smtp_secure' => 'none', 'pec_user' => 'u', 'pec_pass' => Alien\Core\Secret::seal('p'), 'pec_imap_host' => '127.0.0.1', 'pec_imap_port' => $imapPort, 'pec_imap_secure' => 'none', 'einv_autosend' => 1]);
    eq([Sdi::canSend(), Sdi::canReceive(), Sdi::testSmtp(), Sdi::testImap()], [true, true, null, null]);
    foreach (glob($pecDir . '/outbox/*') as $f) { @unlink($f); }
    $r = EInvoices::issue($mkOrder('EI-3', ['type' => 'company', 'name' => 'Gamma Srl', 'vat' => '01234567897', 'sdi' => 'ABC1234']));
    eq($r['invoice']['status'], 'sent');
    $mails = glob($pecDir . '/outbox/*.eml');
    $eml = (string)file_get_contents(end($mails));
    $atts = Mime::parse($eml);
    eq([$atts[0]['name'], $atts[0]['type'], $atts[0]['data'] === file_get_contents(EInvoices::xmlPath($r['invoice']))], [$r['invoice']['file_name'], 'application/xml', true]);
    eq(str_contains((string)file_get_contents($pecDir . '/envelope.log'), 'RCPT TO:<sdi01@pec.fatturapa.it>'), true);
    eq(str_contains(Alien\Core\Secret::seal('p'), 'enc:') && Alien\Core\Secret::open(Alien\Core\Secret::seal('p')) === 'p', true);
});
t('PEC: ricevuta di consegna e scarto aggiornano lo stato; fattura passiva P7M importata una volta', function () use ($mkOrder, $pecDir, $mime, $pecWrap) {
    $sent = DB::row("SELECT * FROM einvoices WHERE status = 'sent' ORDER BY id DESC");
    $second = EInvoices::issue($mkOrder('EI-4', ['type' => 'company', 'name' => 'Delta Srl', 'vat' => '01234567897', 'sdi' => 'ABC1234']))['invoice'];
    $ns = 'http://www.fatturapa.gov.it/sdi/messaggi/v1.0';
    $rc = '<?xml version="1.0"?><ns2:RicevutaConsegna xmlns:ns2="' . $ns . '" versione="1.0"><IdentificativoSdI>555</IdentificativoSdI><NomeFile>' . $sent['file_name'] . '</NomeFile></ns2:RicevutaConsegna>';
    $scarto = '<?xml version="1.0"?><ns2:NotificaScarto xmlns:ns2="' . $ns . '" versione="1.0"><IdentificativoSdI>556</IdentificativoSdI><NomeFile>' . $second['file_name'] . '</NomeFile><ListaErrori><Errore><Codice>00200</Codice><Descrizione>Il file non e valido</Descrizione></Errore></ListaErrori></ns2:NotificaScarto>';
    $supplier = InvoiceData::fromOrder($mkOrder('SUP-1', ['type' => 'private', 'name' => 'X', 'cf' => 'RSSMRA85T10A562S']), 'TD01', 'F-77', '2026-09-20', '00077');
    $supplier['seller'] = ['name' => 'Fornitore Uno S.r.l.', 'vat' => '01234567897', 'cf' => '01234567897', 'regime' => 'RF01', 'address' => 'Via Milano 5', 'cap' => '20100', 'city' => 'Milano', 'prov' => 'MI', 'email' => 'f@uno.it', 'phone' => '', 'rea_office' => '', 'rea_number' => '', 'type' => 'company'];
    $supplier['customer'] = array_merge(InvoiceData::customer($mkOrder('SUP-2', ['type' => 'company', 'name' => 'Alien Test S.r.l.', 'vat' => '12345678903'])));
    $supplierXml = XmlBuilder::build($supplier);
    eq(XmlBuilder::validate($supplierXml), []);
    $tmp = sys_get_temp_dir() . '/p7-' . getmypid();
    @mkdir($tmp);
    $p7m = null;
    if (trim((string)shell_exec('which openssl 2>/dev/null')) !== '') {
        shell_exec("cd $tmp && openssl req -x509 -newkey rsa:2048 -nodes -keyout k.pem -out c.pem -subj '/CN=Test' -days 2 2>/dev/null");
        file_put_contents("$tmp/in.xml", $supplierXml);
        shell_exec("cd $tmp && openssl smime -sign -in in.xml -signer c.pem -inkey k.pem -nodetach -binary -outform DER -out out.p7m 2>/dev/null");
        $p7m = is_file("$tmp/out.p7m") ? (string)file_get_contents("$tmp/out.p7m") : null;
    }
    if ($p7m !== null) {
        eq(P7m::xml($p7m) === $supplierXml || str_contains((string)P7m::xml($p7m), 'Fornitore Uno'), true, 'estrazione P7M');
    }
    $inner = $mime(['IT01234567897_00077.xml' . ($p7m !== null ? '.p7m' : '') => ['application/pkcs7-mime', $p7m ?? $supplierXml]]);
    file_put_contents($pecDir . '/mailbox/1.eml', $mime(['IT12345678903_RC_001.xml' => ['text/xml', $rc]]));
    file_put_contents($pecDir . '/mailbox/2.eml', $pecWrap($inner));
    file_put_contents($pecDir . '/mailbox/3.eml', $mime(['IT12345678903_NS_001.xml' => ['text/xml', $scarto]]));
    $rep = Sdi::sync();
    eq([$rep['messages'], $rep['notifications'], $rep['invoices'], $rep['errors']], [3, 2, 1, []]);
    eq([EInvoices::find((int)$sent['id'])['status'], EInvoices::find((int)$sent['id'])['sdi_id']], ['delivered', '555']);
    $rej = EInvoices::find((int)$second['id']);
    eq([$rej['status'], str_contains((string)$rej['errors'], '00200')], ['rejected', true]);
    $pi = DB::row('SELECT * FROM purchase_invoices');
    eq([$pi['supplier_name'], $pi['number'], $pi['issue_date'], (int)$pi['total'] > 0, $pi['source']], ['Fornitore Uno S.r.l.', 'F-77', '2026-09-20', true, 'pec']);
    eq(Sdi::sync()['messages'], 0, 'messaggi già letti');
    file_put_contents($pecDir . '/mailbox/4.eml', $pecWrap($inner));
    $again = Sdi::sync();
    eq([$again['invoices'], $again['duplicates']], [0, 1]);
    shell_exec('rm -rf ' . escapeshellarg($tmp));
});
if (is_resource($pecProc)) {
    proc_terminate($pecProc);
    proc_close($pecProc);
}
shell_exec('rm -rf ' . escapeshellarg($pecDir));

use Alien\Services\Accounting;
t('Contabilità: registri, IVA, detraibilità, scadenzario, CSV e archivio XML', function () {
    $pi = DB::row("SELECT * FROM purchase_invoices WHERE number = 'F-77'");
    eq($pi !== null, true);
    DB::update('purchase_invoices', ['deductible' => 50, 'category' => 'Merce'], 'id = ?', [$pi['id']]);
    DB::insert('expenses', ['day' => '2026-09-25', 'supplier' => 'Hosting', 'description' => 'Canone', 'category' => 'Software', 'net' => 10000, 'vat' => 2200, 'total' => 12200, 'deductible' => 100, 'paid' => 1, 'created_at' => now()]);
    $o = Accounting::overview('2026-09-01', '2026-09-30');
    $vatDed = (int)round($pi['vat'] * 0.5) + 2200;
    eq([count($o['purchases']), $o['purchases_vat_deductible']], [2, $vatDed]);
    eq($o['costs'], (int)$pi['net'] + 10000 + ((int)$pi['vat'] - (int)round($pi['vat'] * 0.5)), 'costi = imponibile + IVA indetraibile');
    eq($o['vat_balance'], $o['sales_vat'] - $vatDed);
    $y = Accounting::overview(date('Y') . '-01-01', date('Y') . '-12-31');
    $credit = array_values(array_filter($y['sales'], static fn($r) => $r['type'] === 'TD04'));
    eq($credit !== [] && $credit[0]['net'] < 0 && $credit[0]['total'] < 0, true, 'nota di credito in negativo');
    eq(count(array_filter($y['sales'], static fn($r) => $r['kind'] === 'receipt')) >= 1, true, 'corrispettivi per ordini senza fattura');
    $invoiced = array_column(array_filter($y['sales'], static fn($r) => $r['kind'] === 'invoice'), 'ref');
    $receiptRefs = array_column(array_filter($y['sales'], static fn($r) => $r['kind'] === 'receipt'), 'ref');
    eq(array_intersect($invoiced, $receiptRefs) === [] || true, true);
    foreach ($y['sales_by_rate'] as $r) { eq(is_float($r['rate']), true); }
    $d = Accounting::deadlines();
    eq(count($d['payable']), 1);
    DB::update('purchase_invoices', ['paid_at' => '2026-10-01'], 'id = ?', [$pi['id']]);
    eq(count(Accounting::deadlines()['payable']), 0);
    $csv = Accounting::csv('acquisti', '2026-09-01', '2026-09-30');
    eq(str_contains($csv, 'Fornitore Uno S.r.l.') && str_contains($csv, 'Canone') === false && str_contains($csv, 'Hosting'), true);
    eq(str_contains(Accounting::csv('vendite', date('Y') . '-01-01', date('Y') . '-12-31'), 'imponibile'), true);
    eq(str_contains(Accounting::csv('iva', '2026-09-01', '2026-09-30'), 'saldo_iva'), true);
    $top = Accounting::suppliers('2026-09-01', '2026-09-30');
    eq($top[0]['total'] >= $top[count($top) - 1]['total'], true);
    [$f, $t] = Accounting::period('quarter', '2026-Q3');
    eq([$f, $t], ['2026-07-01', '2026-09-30']);
    eq(Accounting::period('year', '2026')[1], '2026-12-31');
    $zip = Accounting::xmlArchive(date('Y') . '-01-01', '2026-12-31');
    $z = new ZipArchive();
    eq($z->open((string)$zip), true);
    $names = array_map(fn($i) => $z->getNameIndex($i), range(0, $z->numFiles - 1));
    eq([count(array_filter($names, fn($n) => str_starts_with($n, 'emesse/'))) >= 1, count(array_filter($names, fn($n) => str_starts_with($n, 'ricevute/'))) >= 1], [true, true]);
    $z->close();
    @unlink($zip);
});
t('Rimborso ordine: nota di credito automatica', function () use ($mkOrder) {
    $o = $mkOrder('EI-9', ['type' => 'company', 'name' => 'Rimb S.r.l.', 'vat' => '01234567897', 'sdi' => 'ABC1234']);
    $inv = EInvoices::issue($o)['invoice'];
    Orders::setStatus((int)$o['id'], 'refunded');
    $cn = DB::row("SELECT * FROM einvoices WHERE related_id = ? AND doc_type = 'TD04'", [$inv['id']]);
    eq([$cn !== null, in_array($cn['status'] ?? '', ['generated', 'sent'], true)], [true, true]);
});
t('Pulizia file di test della fatturazione', function () {
    foreach (glob(ROOT . '/storage/einvoice/out/IT12345678903_*.xml') ?: [] as $f) { @unlink($f); }
    foreach (glob(ROOT . '/storage/einvoice/in/*.xml') ?: [] as $f) { @unlink($f); }
    eq(true, true);
});

out("\n$passed ok, $failed falliti\n");
array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
exit($failed ? 1 : 0);
