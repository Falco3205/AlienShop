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

out("\n$passed ok, $failed falliti\n");
array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
exit($failed ? 1 : 0);
