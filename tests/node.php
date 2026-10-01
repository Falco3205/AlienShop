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

$base = [
    'shop_id' => 1, 'domain' => 'cliente.it', 'path' => '', 'mode' => 'direct', 'url' => 'https://cliente.it',
    'repo' => 'Falco3205/AlienShop', 'branch' => 'main', 'store_name' => "Rossi & Figli's", 'admin_email' => 'a@cliente.it', 'admin_password' => 'Abcdef1234567890',
    'theme' => 'aurora', 'lang' => 'it', 'demo' => 0, 'hub_url' => 'http://hub.falconefabio.it', 'hub_secret' => str_repeat('ab', 24), 'trusted_proxies' => ['100.64.0.0/10'],
];
$backend = fn() => new Node(['hub' => 'http://hub.test', 'token' => 'x', 'role' => 'backend', 'php' => '8.3', 'listen' => '100.101.102.103', 'nginx_user' => 'www-data', 'base' => '/var/www/alienshop'], true);
$edge = fn() => new Node(['hub' => 'http://hub.test', 'token' => 'x', 'role' => 'edge', 'user' => 'falco3205', 'users' => ['falco3205']], true);

fwrite(STDOUT, "Agente server\n");
t('backend senza Hestia: utente, database, pool PHP, Nginx, installazione, cron', function () use ($base, $backend) {
    $n = $backend();
    $r = $n->installShop($base);
    $log = implode("\n", $n->log);
    [$slug, $user] = $n->names('cliente.it', '');
    foreach (["useradd --system --home-dir /var/www/alienshop/shops/$slug", "usermod -aG $user www-data", 'git clone --depth 1 -b main https://github.com/Falco3205/AlienShop.git ' . "/var/www/alienshop/shops/$slug/app",
        'mysql --batch < (SQL)', "/etc/php/8.3/fpm/pool.d/$user.conf", 'php-fpm8.3 -t', 'systemctl reload php8.3-fpm', '/etc/nginx/conf.d/as-cliente.it.conf', '00-root.loc', 'nginx -t', 'systemctl reload nginx',
        'bin/console install --url=https://cliente.it', '--db=mysql', "--db-name=$user", '--trusted-proxies=100.64.0.0/10', "/etc/cron.d/$user", "rm -rf /var/www/alienshop/shops/$slug/app/.git"] as $needle) {
        ok(str_contains($log, $needle), "manca: $needle");
    }
    ok(!str_contains($log, 'v-add') && !str_contains($log, 'hestia'), 'nessun comando di Hestia sul backend');
    ok(!str_contains($log, 'Abcdef1234567890') && !str_contains($log, str_repeat('ab', 24)), 'password e segreti non nel registro');
    ok(!str_contains($log, 'IDENTIFIED BY'), 'la password del database non passa nel registro');
    ok(strpos($log, 'storage/install.key') < strpos($log, 'bin/console install'), 'chiave di installazione prima dell\'installazione');
    ok(strpos($log, 'git clone') < strpos($log, 'rm -rf'), 'il clone precede la rimozione di .git');
    ok($r['url'] === 'https://cliente.it' && $r['user'] === $user);
});
t('nomi: utente e database unici per dominio+cartella, entro i limiti di Linux', function () use ($backend) {
    $n = $backend();
    [, $a] = $n->names('cliente.it', '');
    [, $b] = $n->names('cliente.it', 'negozio');
    [, $c] = $n->names('un-dominio-molto-lungo-davvero.example.com', '');
    ok($a !== $b, 'stesso dominio, cartelle diverse');
    ok(strlen($a) <= 32 && strlen($c) <= 32 && preg_match('/^as_[a-z0-9_]+$/', $c) === 1, $c);
});
t('sottocartella: percorso dedicato, pagina segnaposto, nessuna radice', function () use ($base, $backend) {
    $n = $backend();
    $n->installShop(['path' => 'negozio', 'url' => 'https://cliente.it/negozio'] + $base);
    $log = implode("\n", $n->log);
    ok(str_contains($log, '10-negozio.loc') && str_contains($log, '99-default.loc') && !str_contains($log, '00-root.loc'));
    [, $user] = $n->names('cliente.it', 'negozio');
    $b = $n->subLocation("/var/www/alienshop/shops/x/app", $user, 'negozio');
    ok(str_contains($b, 'location ^~ /negozio/') && str_contains($b, 'alias /var/www/alienshop/shops/x/app/public/;') && str_contains($b, 'fastcgi_param SCRIPT_NAME /negozio/index.php;') && str_contains($b, "location ~ \\.(php|phtml|phar)\$ { return 404; }"));
});
t('radice dopo una sottocartella: il segnaposto viene tolto', function () use ($base, $backend) {
    $n = $backend();
    $n->installShop($base);
    ok(str_contains(implode("\n", $n->log), 'rm -f /etc/nginx/alienshop.d/cliente.it/99-default.loc'));
});
t('isolamento: pool PHP per negozio, open_basedir, funzioni di sistema disattivate, Nginx solo su tailnet', function () use ($backend) {
    $n = $backend();
    $pool = $n->poolConfig('as_x_1234', '/var/www/alienshop/shops/x_1234', 'www-data');
    foreach (['user = as_x_1234', 'listen = /run/php/as_x_1234.sock', 'listen.mode = 0660', 'open_basedir] = /var/www/alienshop/shops/x_1234:/tmp', 'disable_functions] = exec,passthru,shell_exec,system,proc_open,popen', 'expose_php] = off', 'allow_url_include] = off'] as $needle) {
        ok(str_contains($pool, $needle), $needle);
    }
    $v = $n->vhost('cliente.it', '100.101.102.103');
    ok(str_contains($v, 'listen 100.101.102.103:80;') && str_contains($v, 'server_name cliente.it www.cliente.it;') && str_contains($v, 'suspend.inc') && str_contains($v, 'server_tokens off;'));
    $r = $n->rootLocation('/var/www/alienshop/shops/x/app', 'as_x');
    ok(str_contains($r, "location ^~ /uploads/") && str_contains($r, "return 403;") && str_contains($r, 'deny all') && str_contains($r, 'root /var/www/alienshop/shops/x/app/public;'));
    ok(!str_contains($r, 'fastcgi_param SCRIPT_FILENAME $document_root'), 'il front controller è fisso');
});
t('sospensione e riattivazione', function () use ($backend) {
    $n = $backend();
    $n->execute(1, 'suspend_shop', ['domain' => 'cliente.it']);
    ok(str_contains(implode("\n", $n->log), 'suspend.inc') && str_contains(implode("\n", $n->log), 'nginx -t'));
});
t('frontend (Hestia): proxy, cache, certificato; nessun tunnel', function () use ($edge) {
    $n = $edge();
    $n->addEdge(['domain' => 'cliente.it', 'hestia_user' => 'falco3205', 'upstream' => 'http://100.101.102.103:80']);
    $log = implode("\n", $n->log);
    foreach (['alienshop-cache.conf', 'alienshop_edge.inc', 'v-change-web-domain-tpl falco3205 cliente.it alienshop-edge', 'v-add-letsencrypt-domain falco3205 cliente.it'] as $needle) {
        ok(str_contains($log, $needle), "manca: $needle");
    }
    $c = $n->edgeConfig('http://100.101.102.103:80');
    ok(substr_count($c, 'proxy_pass http://100.101.102.103:80;') === 2 && str_contains($c, 'proxy_set_header Host $host;') && str_contains($c, 'proxy_set_header X-Forwarded-For $remote_addr;'));
    ok(str_contains($c, 'proxy_cache_bypass $cookie_as_admin;') && str_contains($c, 'proxy_cache_use_stale'));
    ok(!str_contains($c, 'X-Alien-Relay'));
    $threw = false;
    try {
        $edge()->addEdge(['domain' => 'x.it', 'hestia_user' => 'altro', 'upstream' => 'http://a']);
    } catch (RuntimeException $e) {
        $threw = str_contains($e->getMessage(), 'non autorizzato');
    }
    ok($threw);
});
t('claim da Hestia: solo frontend, utente ammesso, richiesta all\'hub', function () use ($edge, $backend) {
    $calls = [];
    $mk = function (array $cfg) use (&$calls) {
        return new class($cfg, true, $calls) extends Node {
            public function __construct(array $cfg, bool $dry, private array &$calls)
            {
                parent::__construct($cfg, $dry);
            }
            public function http(string $method, string $path, array $body = []): array
            {
                $this->calls[] = [$method, $path, $body];
                return ['status' => 200, 'json' => ['ok' => true]];
            }
        };
    };
    $n = $mk(['hub' => 'x', 'token' => 'y', 'role' => 'edge', 'user' => 'falco3205', 'users' => ['falco3205']]);
    ob_start();
    $rc = $n->claim('falco3205', 'nuovo.it');
    ob_end_clean();
    ok($rc === 0 && $calls === [['POST', '/api/node/claim', ['user' => 'falco3205', 'domain' => 'nuovo.it']]], json_encode($calls));
    $calls = [];
    foreach ([['altro', 'nuovo.it'], ['falco3205', 'nuovo.it; id'], ['falco3205', "nuovo.it\n"]] as [$u, $d]) {
        $x = $mk(['hub' => 'x', 'token' => 'y', 'role' => 'edge', 'users' => ['falco3205']]);
        ob_start();
        $err = fopen('php://memory', 'r');
        $rc = @$x->claim($u, $d);
        ob_end_clean();
        ok($rc === 1, "$u $d");
    }
    ok($calls === [], 'nessuna richiesta all\'hub per input non validi');
    $b = $mk(['hub' => 'x', 'token' => 'y', 'role' => 'backend']);
    ok(@$b->claim('falco3205', 'nuovo.it') === 1 && $calls === [], 'sul backend non esiste');
});
t('validazione: iniezioni e parametri pericolosi rifiutati', function () use ($base, $backend) {
    $bad = [
        ['domain' => 'x.it; rm -rf /'], ['domain' => '../../etc'], ['path' => '../x'], ['path' => 'a b'], ['repo' => 'a/b; id'], ['branch' => 'main;id'], ['theme' => '../x'],
        ['hub_secret' => 'zz'], ['url' => 'https://x.it/$(id)'], ['admin_email' => 'no'], ['admin_password' => 'corta'], ['admin_password' => 'Abcdef1234567890; id'],
        ['trusted_proxies' => ['1.2.3.4; id']], ['hub_url' => 'javascript:alert(1)'],
        ['domain' => "cliente.it\n"], ['path' => "neg\n"], ['theme' => "aurora\n"], ['url' => "https://cliente.it\n"], ['hub_secret' => str_repeat('ab', 24) . "\n"], ['admin_password' => "Abcdef1234567890\n"],
    ];
    foreach ($bad as $over) {
        $threw = false;
        try {
            $backend()->installShop($over + $base);
        } catch (RuntimeException) {
            $threw = true;
        }
        ok($threw, 'accettato: ' . json_encode($over));
    }
});
t('server non preparato: senza stack.sh l\'installazione si ferma', function () use ($base) {
    $threw = false;
    try {
        (new Node(['hub' => 'x', 'token' => 'y']))->installShop($base);
    } catch (RuntimeException $e) {
        $threw = true;
    }
    ok($threw);
});
t('attività sconosciuta rifiutata, info server disponibili', function () use ($backend) {
    $n = $backend();
    $n->execute(1, 'rm_rf', []);
    ok(str_contains(implode("\n", $n->log), 'sconosciuto'));
    $i = $n->info();
    ok(isset($i['php'], $i['load'], $i['disk_used_pct']));
});

fwrite(STDOUT, "\n$passed ok, $failed falliti\n");
exit($failed ? 1 : 0);
