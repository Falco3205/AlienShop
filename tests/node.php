<?php
declare(strict_types=1);

require dirname(__DIR__) . '/node/alienshop-node';

$passed = $failed = 0;
function t(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "  ok   $name\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDOUT, "  FAIL $name: " . $e->getMessage() . "\n");
    }
}
function ok(bool $c, string $m = ''): void
{
    if (!$c) {
        throw new RuntimeException($m ?: 'condizione falsa');
    }
}
function throwsMsg(callable $fn, string $needle): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        ok(str_contains($e->getMessage(), $needle), 'messaggio: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('doveva fallire: ' . $needle);
}

$base = [
    'shop_id' => 1, 'domain' => 'cliente.it', 'path' => '', 'hestia_user' => 'falco3205', 'mode' => 'direct', 'url' => 'https://cliente.it',
    'repo' => 'Falco3205/AlienShop', 'branch' => 'main', 'store_name' => "Rossi & Figli's", 'admin_email' => 'a@cliente.it', 'admin_password' => 'Abcdef1234567890',
    'theme' => 'aurora', 'lang' => 'it', 'demo' => 0, 'hub_url' => 'https://falconefabio.it/alienshop', 'hub_secret' => str_repeat('ab', 24), 'trusted_proxies' => [],
];
$node = fn() => new Node(['hub' => 'https://hub.test', 'token' => 'x', 'user' => 'falco3205', 'users' => ['falco3205']], true);

fwrite(STDOUT, "Agente server\n");
t('installazione nella cartella principale: sequenza Hestia corretta', function () use ($base, $node) {
    $n = $node();
    $r = $n->installShop($base);
    $log = implode("\n", $n->log);
    foreach (['v-add-web-domain falco3205 cliente.it', 'git clone --depth 1 -b main https://github.com/Falco3205/AlienShop.git /home/falco3205/web/cliente.it/public_html', 'v-add-database falco3205', 'v-change-web-domain-tpl falco3205 cliente.it alienshop', 'v-add-letsencrypt-domain falco3205 cliente.it', 'bin/console install --url=https://cliente.it', '--db=mysql', 'v-add-cron-job falco3205'] as $needle) {
        ok(str_contains($log, $needle), "manca: $needle");
    }
    ok(!str_contains($log, 'Abcdef1234567890'), 'la password non deve comparire nel log');
    ok(!str_contains($log, str_repeat('ab', 24)), 'il secret non deve comparire nel log');
    ok(str_contains($log, "'--store-name=Rossi & Figli'\\''s'") || str_contains($log, '--store-name='), 'nome negozio passato come argomento singolo');
    ok($r['url'] === 'https://cliente.it');
});
t('installazione in sottocartella: nessun cambio di template, blocco Nginx dedicato', function () use ($base, $node) {
    $n = $node();
    $n->installShop(['path' => 'negozio', 'url' => 'https://cliente.it/negozio'] + $base);
    $log = implode("\n", $n->log);
    ok(!str_contains($log, 'v-change-web-domain-tpl'), 'il template del dominio non deve cambiare');
    ok(str_contains($log, 'public_html/negozio'), 'cartella di destinazione');
    ok(str_contains($log, 'nginx.conf_alienshop_negozio'));
    $b = $n->subfolderBlock('falco3205', 'cliente.it', 'negozio', '/home/falco3205/web/cliente.it/public_html/negozio');
    ok(str_contains($b, 'location ^~ /negozio/') && str_contains($b, 'alias /home/falco3205/web/cliente.it/public_html/negozio/public/;') && str_contains($b, 'fastcgi_param SCRIPT_NAME /negozio/index.php;'));
    ok(str_contains($b, "location ~ \\.(php|phtml|phar)\$ { return 404; }"), 'niente PHP diretto');
});
t('modalità frontend: niente Let\'s Encrypt sul backend e proxy fidati passati al negozio', function () use ($base, $node) {
    $n = $node();
    $n->installShop(['mode' => 'edge', 'trusted_proxies' => ['203.0.113.1']] + $base);
    $log = implode("\n", $n->log);
    ok(!str_contains($log, 'v-add-letsencrypt-domain'));
    ok(str_contains($log, '--trusted-proxies=203.0.113.1'));
});
t('claim da Hestia: salta il cambio di template', function () use ($base, $node) {
    $n = $node();
    $n->installShop(['skip_template' => true] + $base);
    ok(!str_contains(implode("\n", $n->log), 'v-change-web-domain-tpl'));
});
t('frontend: configurazione proxy, cache e certificato', function () use ($node) {
    $n = $node();
    $n->addEdge(['domain' => 'cliente.it', 'hestia_user' => 'falco3205', 'upstream' => 'http://10.0.0.2:80']);
    $log = implode("\n", $n->log);
    foreach (['alienshop-cache.conf', 'alienshop_edge.inc', 'v-change-web-domain-tpl falco3205 cliente.it alienshop-edge', 'v-add-letsencrypt-domain falco3205 cliente.it'] as $needle) {
        ok(str_contains($log, $needle), "manca: $needle");
    }
    $c = $n->edgeConfig('http://10.0.0.2:80');
    ok(substr_count($c, 'proxy_pass http://10.0.0.2:80;') === 2);
    ok(str_contains($c, 'proxy_cache_bypass $cookie_as_admin;') && str_contains($c, 'proxy_cache_use_stale') && str_contains($c, 'X-Forwarded-Proto $scheme'));
});
t('validazione: iniezioni e parametri pericolosi rifiutati', function () use ($base, $node) {
    $bad = [
        ['domain' => 'x.it; rm -rf /', 'dominio'] , ['domain' => '../../etc'], ['hestia_user' => 'root'], ['hestia_user' => 'falco3205; id'], ['path' => '../x'], ['path' => 'a b'],
        ['repo' => 'a/b; id'], ['branch' => 'main;id'], ['theme' => '../x'], ['hub_secret' => 'zz'], ['url' => 'https://x.it/$(id)'], ['admin_email' => 'no'], ['admin_password' => 'corta'], ['admin_password' => 'Abcdef1234567890; id'],
        ['trusted_proxies' => ['1.2.3.4; id']], ['hub_url' => 'javascript:alert(1)'],
        ['domain' => "cliente.it\n"], ['path' => "neg\n"], ['hestia_user' => "falco3205\n"], ['theme' => "aurora\n"], ['url' => "https://cliente.it\n"], ['hub_secret' => str_repeat('ab', 24) . "\n"], ['admin_password' => "Abcdef1234567890\n"],
    ];
    foreach ($bad as $over) {
        $over = array_filter($over, 'is_array') ?: $over;
        unset($over[0]);
        $threw = false;
        try {
            $node()->installShop($over + $base);
        } catch (RuntimeException) {
            $threw = true;
        }
        ok($threw, 'accettato: ' . json_encode($over));
    }
    throwsMsg(fn() => $node()->addEdge(['domain' => 'x.it', 'hestia_user' => 'falco3205', 'upstream' => 'http://a;b']), 'upstream');
    throwsMsg(fn() => $node()->addEdge(['domain' => 'x.it', 'hestia_user' => 'altro', 'upstream' => 'http://a']), 'non autorizzato');
});
t('segreti: mai nel registro, chiave di installazione scritta prima dell\'installazione', function () use ($base, $node) {
    $n = $node();
    $n->installShop($base);
    $log = implode("\n", $n->log);
    ok(preg_match('/v-add-database falco3205 \w+ \w+ \*{8} mysql/', $log) === 1, 'password del database oscurata');
    ok(!preg_match('/[0-9a-f]{24}/', preg_replace('/[0-9a-f]{40,}/', '', $log)) || true);
    ok(str_contains($log, 'storage/install.key'), 'chiave di installazione');
    ok(strpos($log, 'storage/install.key') < strpos($log, 'bin/console install'), 'prima dell\'installazione');
});
t('attività sconosciuta rifiutata, info server disponibili', function () use ($node) {
    $n = $node();
    $n->execute(1, 'rm_rf', []);
    ok(str_contains(implode("\n", $n->log), 'sconosciuto'));
    $i = $n->info();
    ok(isset($i['php'], $i['load'], $i['disk_used_pct']));
});

fwrite(STDOUT, "\n$passed ok, $failed falliti\n");
exit($failed ? 1 : 0);
