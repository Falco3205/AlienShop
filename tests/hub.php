<?php
declare(strict_types=1);

require dirname(__DIR__) . '/hub/app/bootstrap.php';

use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Http;
use Alien\Core\Request;
use Alien\Core\Settings;
use Hub\Fleet;
use Hub\Jobs;
use Hub\Nodes;
use Hub\Shops;

$_SERVER['HTTP_HOST'] = 'hub.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$tmp = sys_get_temp_dir() . '/alienhub-test-' . getmypid();
mkdir($tmp);
$pdo = DB::connect(['driver' => 'sqlite', 'path' => $tmp . '/hub.sqlite']);
Alien\Services\Installer::createSchema($pdo, 'sqlite');
$cfg = new ReflectionProperty(Config::class, 'data');
$cfg->setValue(null, ['app' => ['url' => 'https://hub.test/alienshop', 'key' => str_repeat('k', 64)]]);
Settings::setMany(['shop_repo' => 'Falco3205/AlienShop', 'shop_branch' => 'main', 'default_admin_email' => 'me@test.it']);

$passed = $failed = 0;
function out(string $s): void
{
    fwrite(STDOUT, $s);
}
function eq(mixed $a, mixed $b, string $msg = ''): void
{
    if ($a !== $b) {
        throw new RuntimeException(($msg ? $msg . ': ' : '') . 'atteso ' . json_encode($b) . ', ottenuto ' . json_encode($a));
    }
}
function t(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        out("  ok   $name\n");
    } catch (Throwable $e) {
        $failed++;
        out("  FAIL $name: " . $e->getMessage() . "\n");
    }
}

out("Hub\n");
[$backId, $backToken] = (function () {
    [$id, $token, $err] = Nodes::create('VPS backend', 'backend', '203.0.113.2', 'http://10.0.0.2:80', 'falco3205');
    return [$id, $token];
})();
[$edgeId, $edgeToken] = (function () {
    [$id, $token, $err] = Nodes::create('VPS frontend', 'edge', '203.0.113.1', '', 'falco3205');
    return [$id, $token];
})();

t('server: token, autenticazione, validazione', function () use ($backId, $backToken, $edgeToken) {
    eq(strlen($backToken), 48);
    eq(Nodes::authenticate($backToken)['id'], $backId);
    eq(Nodes::authenticate(str_repeat('0', 48)), null);
    eq(Nodes::authenticate('x'), null);
    eq(Nodes::authenticate($edgeToken)['role'], 'edge');
    [$id, , $errors] = Nodes::create('', 'backend', '', 'ftp://x', 'Bad User');
    eq($id, null);
    eq(count($errors) >= 2, true);
    eq(DB::val('SELECT COUNT(*) FROM nodes WHERE token_hash = ?', [$backToken]), 0, 'il token non è salvato in chiaro');
});

t('negozio: validazione di dominio, cartella, duplicati', function () use ($backId, $edgeId) {
    $base = ['name' => 'Cliente', 'admin_email' => 'c@cliente.it', 'node_id' => $backId];
    foreach ([['domain' => 'non valido'], ['domain' => 'ok.it', 'path' => '../x'], ['domain' => 'ok.it', 'path' => 'admin'], ['domain' => 'ok.it', 'admin_email' => 'no']] as $bad) {
        [$id, $errors] = Shops::create($bad + $base + ['domain' => 'ok.it']);
        eq($id, null, json_encode($bad));
    }
    foreach (["ok.it\nBcc: x", 'ok.it/../x', "ok.it\0.evil.com"] as $dom) {
        [$id] = Shops::create(['domain' => $dom] + $base);
        eq($id, null, json_encode($dom));
    }
    [$id] = Shops::create(['domain' => 'ok.it', 'path' => "neg\n"] + $base);
    eq($id, null, 'cartella con ritorno a capo');
    [$id, , $errs] = Nodes::create('Srv', 'backend', '', "http://10.0.0.2\nlocation / {}", 'falco3205');
    eq($id, null, 'upstream con ritorno a capo');
    [$id, $errors] = Shops::create($base + ['domain' => 'edge-needs.it', 'mode' => 'edge', 'edge_node_id' => $backId]);
    eq($id, null, 'il frontend deve avere ruolo edge');
});

$shopId = null;
t('negozio diretto: job di installazione con parametri corretti', function () use ($backId, &$shopId) {
    [$shopId, $errors] = Shops::create(['name' => 'Rossi Moda', 'domain' => 'Rossi-Moda.it', 'path' => '', 'admin_email' => 'Mario@Rossi.it', 'node_id' => $backId, 'theme' => 'boutique', 'demo' => '1']);
    eq($errors, []);
    $shop = Shops::find($shopId);
    eq([$shop['domain'], $shop['admin_email'], $shop['status']], ['rossi-moda.it', 'mario@rossi.it', 'pending']);
    $jobs = Jobs::claimFor($backId);
    eq(count($jobs), 1);
    $p = $jobs[0]['payload'];
    eq([$jobs[0]['type'], $p['url'], $p['mode'], $p['theme'], $p['demo'], $p['hub_url']], ['install_shop', 'https://rossi-moda.it', 'direct', 'boutique', 1, 'https://hub.test/alienshop']);
    eq(strlen($p['hub_secret']), 48);
    eq(strlen($p['admin_password']) >= 16, true);
    eq(Shops::find($shopId)['status'], 'installing');
    eq(str_contains((string)DB::val('SELECT secret FROM shops WHERE id = ?', [$shopId]), $p['hub_secret']), false, 'secret cifrato a riposo');
    $stored = (string)DB::val('SELECT payload FROM jobs WHERE id = ?', [$jobs[0]['id']]);
    eq(str_contains($stored, $p['hub_secret']) || str_contains($stored, $p['admin_password']), false, 'segreti del lavoro cifrati a riposo');
    eq(Jobs::claimFor($backId), [], 'un job viene consegnato una volta sola');
    Jobs::complete($jobs[0]['id'], true, [], 'fatto');
    eq(Shops::find($shopId)['status'], 'active');
    $after = json_decode((string)DB::val('SELECT payload FROM jobs WHERE id = ?', [$jobs[0]['id']]), true);
    eq([$after['hub_secret'], $after['admin_password']], ['', ''], 'segreti rimossi a lavoro concluso');
    Jobs::complete($jobs[0]['id'], false, [], 'doppio');
    eq(DB::val('SELECT status FROM jobs WHERE id = ?', [$jobs[0]['id']]), 'ok', 'risultato definitivo');
});

t('negozio dietro frontend: installazione poi pubblicazione, URL in sottocartella, proxy fidato', function () use ($backId, $edgeId) {
    [$id, $errors] = Shops::create(['name' => 'Bianchi', 'domain' => 'bianchi.it', 'path' => 'negozio', 'admin_email' => 'b@bianchi.it', 'node_id' => $backId, 'mode' => 'edge', 'edge_node_id' => $edgeId]);
    eq($errors, []);
    $jobs = Jobs::claimFor($backId);
    $p = $jobs[0]['payload'];
    eq([$p['url'], $p['mode'], $p['trusted_proxies']], ['https://bianchi.it/negozio', 'edge', ['203.0.113.1']]);
    DB::update('nodes', ['trusted' => '10.0.0.0/24, 172.16.0.5'], 'id = ?', [$backId]);
    [$id2] = Shops::create(['name' => 'Neri', 'domain' => 'neri.it', 'admin_email' => 'n@neri.it', 'node_id' => $backId, 'mode' => 'edge', 'edge_node_id' => $edgeId]);
    $j2 = Jobs::claimFor($backId);
    eq($j2[0]['payload']['trusted_proxies'], ['10.0.0.0/24', '172.16.0.5'], 'intervallo indicato al posto del default');
    DB::update('nodes', ['trusted' => ''], 'id = ?', [$backId]);
    [$nid, , $nerr] = Nodes::create('X', 'backend', '', 'http://10.0.0.2', 'falco3205', '1.2.3.4; id');
    eq($nid, null, 'intervallo non valido');
    Jobs::complete($jobs[0]['id'], true, [], 'ok');
    eq(Shops::find($id)['status'], 'installing', 'finché il frontend non è pronto');
    $edgeJobs = Jobs::claimFor($edgeId);
    eq(count($edgeJobs), 1);
    eq([$edgeJobs[0]['type'], $edgeJobs[0]['payload']['upstream'], $edgeJobs[0]['payload']['domain']], ['add_edge', 'http://10.0.0.2:80', 'bianchi.it']);
    Jobs::complete($edgeJobs[0]['id'], false, ['error' => 'nginx non valido'], 'log');
    eq([Shops::find($id)['status'], Shops::find($id)['last_error']], ['error', 'nginx non valido']);
});

t('backend su Tailscale: proxy fidati locali e l\'hub interroga il negozio dalla tailnet con Host del dominio', function () use ($edgeId) {
    [$id, , $err] = Nodes::create('Backend TS', 'backend', '198.51.100.8', 'http://100.101.102.103:80', 'falco3205');
    eq($err, []);
    $n = Nodes::find($id);
    eq(Nodes::tailnet($n), true);
    eq(Nodes::tailnet(['upstream' => 'http://10.0.0.2:80']), false);
    eq(Nodes::tailnet(['upstream' => 'http://100.128.0.1:80']), false, 'fuori da 100.64.0.0/10');
    [$sid] = Shops::create(['name' => 'TS', 'domain' => 'ts.it', 'path' => 'negozio', 'admin_email' => 't@t.it', 'node_id' => $id, 'mode' => 'edge', 'edge_node_id' => $edgeId]);
    $j = Jobs::claimFor($id)[0];
    eq($j['payload']['trusted_proxies'], ['100.64.0.0/10'], 'backend su tailnet: si fida dell\'intera tailnet');
    [$url, $headers, $private] = Hub\ShopClient::target(Shops::find($sid), '/hub/stats');
    eq([$url, $headers, $private], ['http://100.101.102.103:80/negozio/hub/stats', ['Host: ts.it', 'X-Forwarded-Proto: https'], true]);
    [$url2, $h2, $p2] = Hub\ShopClient::target(['node_id' => 1, 'domain' => 'x.it', 'path' => ''], '/hub/stats');
    eq([$p2, $h2], [false, []], 'senza tailnet si usa l\'indirizzo pubblico');
});
t('dominio creato da Hestia: negozio sul backend predefinito, pubblicato dal frontend con l\'utente Hestia del dominio', function () use ($backId, $edgeId) {
    $edge = Nodes::find($edgeId);
    $backs = Nodes::byRole('backend');
    [$id, $err] = Shops::claimEdge($edge, 'falco3205', 'auto-uno.it');
    eq([$id, str_contains((string)$err, 'backend predefinito')], [null, true], 'più backend senza predefinito');
    Settings::set('default_backend', (string)$backId);
    [$id, $err] = Shops::claimEdge($edge, 'cliente_x', 'auto-uno.it');
    eq($err, null);
    $shop = Shops::find($id);
    eq([$shop['domain'], $shop['path'], (int)$shop['node_id'], (int)$shop['edge_node_id'], $shop['hestia_user'], $shop['admin_email']], ['auto-uno.it', '', $backId, $edgeId, 'cliente_x', 'me@test.it']);
    $j = Jobs::claimFor($backId);
    $install = null;
    foreach ($j as $x) {
        if ($x['payload']['domain'] === 'auto-uno.it') {
            $install = $x;
        }
    }
    eq($install['type'], 'install_shop');
    eq($install['payload']['mode'], 'edge');
    Jobs::complete($install['id'], true, [], 'ok');
    $e = Jobs::claimFor($edgeId);
    $payload = end($e)['payload'];
    eq([$payload['domain'], $payload['hestia_user']], ['auto-uno.it', 'cliente_x'], 'il frontend usa l\'utente Hestia del dominio');
    [$dup, $err2] = Shops::claimEdge($edge, 'cliente_x', 'auto-uno.it');
    eq([$dup, $err2 !== null], [null, true], 'duplicato rifiutato');
    [$bad] = Shops::claimEdge($edge, 'Bad User', 'altro-dominio.it');
    eq($bad, null);
    [$bad] = Shops::claimEdge(Nodes::find($backId), 'cliente_x', 'altro-dominio.it');
    eq($bad, null, 'un backend non può registrare domini');
});
t('installazione fallita: stato errore con messaggio e nuovo tentativo', function () use ($backId) {
    [$id] = Shops::create(['name' => 'Verdi', 'domain' => 'verdi.it', 'admin_email' => 'v@verdi.it', 'node_id' => $backId]);
    $j = Jobs::claimFor($backId)[0];
    Jobs::complete($j['id'], false, ['error' => 'database non creato'], 'log');
    eq([Shops::find($id)['status'], Shops::find($id)['last_error']], ['error', 'database non creato']);
});

t('job bloccati vengono chiusi con errore', function () use ($backId) {
    $id = Jobs::startRunning($backId, 0, 'node_update', []);
    DB::update('jobs', ['started_at' => date('Y-m-d H:i:s', time() - 4000)], 'id = ?', [$id]);
    Jobs::claimFor($backId);
    eq(DB::val('SELECT status FROM jobs WHERE id = ?', [$id]), 'error');
});

t('polling: metriche, stato offline, avvisi e totali', function () use ($shopId, $backId) {
    $sha = str_repeat('c', 40);
    Settings::set('shop_scheme', 'http');
    $daily = [];
    for ($i = 29; $i >= 0; $i--) {
        $daily[] = ['d' => date('Y-m-d', strtotime("-$i days")), 't' => $i === 0 ? 5000 : 1000];
    }
    Http::$fake = function ($method, $url, $body, $headers) use ($sha, $daily) {
        $joined = implode('|', $headers);
        if (!str_contains($joined, 'X-Alien-Signature') || !str_contains($url, '/hub/stats')) {
            return ['status' => 404, 'body' => '', 'error' => '', 'json' => null];
        }
        return ['status' => 200, 'body' => '', 'error' => '', 'json' => ['ok' => true, 'metrics' => ['commit' => $sha, 'version' => '1.1.0', 'currency' => 'EUR', 'orders_today' => 2, 'revenue_today' => 5000, 'orders_30d' => 31, 'revenue_30d' => 34000, 'revenue_prev_30d' => 17000, 'to_ship' => 3, 'out_of_stock' => 1, 'errors_24h' => 2, 'update_available' => true, 'payments_on' => [], 'daily' => $daily]]];
    };
    $shop = Shops::find($shopId);
    eq(Hub\Poller::pollShop($shop), true);
    $shop = Shops::find($shopId);
    eq([$shop['version'], $shop['last_error']], [substr($sha, 0, 7), '']);
    $sum = Fleet::summary([$shop]);
    eq([$sum['active'], $sum['revenue_today'], $sum['revenue_30d'], $sum['to_ship']], [1, 5000, 34000, 3]);
    eq(count($sum['daily']), 30);
    $texts = implode(' | ', array_column(Fleet::alerts([$shop], [Nodes::find($backId)]), 'text'));
    foreach (['aggiornamento disponibile', 'errori nel log', 'ordini da spedire', 'prodotti esauriti', 'nessun pagamento online', 'non risponde da più di 5 minuti'] as $needle) {
        eq(str_contains($texts, $needle), true, $needle);
    }
    Http::$fake = fn() => ['status' => 0, 'body' => '', 'error' => 'timeout', 'json' => null];
    eq(Hub\Poller::pollShop($shop), false);
    $shop = Shops::find($shopId);
    eq(str_contains($shop['last_error'], 'non raggiungibile'), true);
    eq(Fleet::offline($shop), false, 'meno di 15 minuti dall\'ultimo contatto');
    DB::update('shops', ['last_ok' => date('Y-m-d H:i:s', time() - 3600)], 'id = ?', [$shopId]);
    eq(Fleet::offline(Shops::find($shopId)), true);
    Http::$fake = fn() => ['status' => 403, 'body' => '', 'error' => '', 'json' => null];
    Hub\Poller::pollShop(Shops::find($shopId));
    eq(str_contains(Shops::find($shopId)['last_error'], 'rifiuta'), true);
    Http::$fake = null;
});

out("\n$passed ok, $failed falliti\n");
@shell_exec('rm -rf ' . escapeshellarg($tmp));
exit($failed ? 1 : 0);
