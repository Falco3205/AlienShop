<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Cache;
use Alien\Core\Http;
use Alien\Core\Secret;
use Alien\Core\Settings;

final class Updater
{
    private const KEEP = ['.git/', 'config/config.php', 'storage/', 'public/uploads/'];
    private const CHECK_TTL = 600;

    public static function repo(): string
    {
        $r = trim((string)Settings::get('update_repo', 'Falco3205/AlienShop'));
        return preg_match('#^[\w.-]+/[\w.-]+$#D', $r) ? $r : 'Falco3205/AlienShop';
    }

    public static function branch(): string
    {
        $b = trim((string)Settings::get('update_branch', 'main'));
        return preg_match('#^[\w./-]+$#D', $b) && !str_contains($b, '..') ? $b : 'main';
    }

    private static function token(): string
    {
        return Secret::open((string)Settings::get('update_token', ''));
    }

    private static function headers(): array
    {
        $h = ['Accept: application/vnd.github+json', 'User-Agent: AlienShop'];
        if (self::token() !== '') {
            $h[] = 'Authorization: Bearer ' . self::token();
        }
        return $h;
    }

    public static function webhookSecret(): string
    {
        $s = (string)Settings::get('update_webhook_secret', '');
        if ($s === '') {
            $s = bin2hex(random_bytes(20));
            Settings::set('update_webhook_secret', $s);
        }
        return $s;
    }

    public static function regenerateSecret(): void
    {
        Settings::set('update_webhook_secret', bin2hex(random_bytes(20)));
    }

    public static function verifySignature(string $body, string $header, string $secret): bool
    {
        return $secret !== '' && hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $header);
    }

    private static array $memo = [];

    public static function gitAvailable(string $root = ROOT): bool
    {
        if (!is_dir($root . '/.git') || !function_exists('proc_open')) {
            return false;
        }
        return self::$memo['git'][$root] ??= self::git($root, ['--version'])[0] === 0;
    }

    public static function method(string $root = ROOT): string
    {
        return self::gitAvailable($root) ? 'git' : 'zip';
    }

    public static function zipAvailable(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public static function writable(string $root = ROOT): bool
    {
        return is_writable($root . '/app') && is_writable($root . '/views') && is_writable($root . '/themes');
    }

    private static function git(string $root, array $args): array
    {
        $proc = @proc_open(['git', '-C', $root, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['GIT_TERMINAL_PROMPT' => '0', 'PATH' => (string)getenv('PATH'), 'HOME' => (string)(getenv('HOME') ?: sys_get_temp_dir())]);
        if (!is_resource($proc)) {
            return [1, ''];
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), trim($out)];
    }

    private static function stateFile(string $root): string
    {
        return $root . '/storage/update.json';
    }

    public static function state(string $root = ROOT): array
    {
        $f = self::stateFile($root);
        return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }

    private static function saveState(string $root, array $state): void
    {
        @mkdir($root . '/storage', 0750, true);
        file_put_contents(self::stateFile($root), json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    public static function currentCommit(string $root = ROOT): string
    {
        return self::$memo['sha'][$root] ??= self::readCommit($root);
    }

    public static function forgetCommit(): void
    {
        unset(self::$memo['sha']);
    }

    private static function readCommit(string $root): string
    {
        if (self::gitAvailable($root)) {
            [$code, $sha] = self::git($root, ['rev-parse', 'HEAD']);
            if ($code === 0 && preg_match('/^[0-9a-f]{40}$/D', $sha)) {
                return $sha;
            }
        }
        return (string)(self::state($root)['sha'] ?? '');
    }

    public static function latest(bool $force = false): ?array
    {
        $cached = json_decode((string)Settings::get('update_latest', ''), true) ?: null;
        if (!$force && $cached && time() - (int)$cached['checked_at'] < self::CHECK_TTL && ($cached['branch'] ?? '') === self::branch() && ($cached['repo'] ?? '') === self::repo()) {
            return $cached;
        }
        $res = Http::request('GET', 'https://api.github.com/repos/' . self::repo() . '/commits/' . rawurlencode(self::branch()), null, self::headers(), 15);
        if ($res['status'] !== 200 || empty($res['json']['sha'])) {
            return $cached ? $cached + ['error' => self::apiError($res)] : ['error' => self::apiError($res)];
        }
        $c = $res['json'];
        $latest = [
            'sha' => (string)$c['sha'],
            'message' => strtok((string)($c['commit']['message'] ?? ''), "\n") ?: '',
            'date' => (string)($c['commit']['committer']['date'] ?? ''),
            'checked_at' => time(), 'branch' => self::branch(), 'repo' => self::repo(),
        ];
        Settings::set('update_latest', json_encode($latest, JSON_UNESCAPED_UNICODE));
        return $latest;
    }

    private static function apiError(array $res): string
    {
        return match (true) {
            $res['status'] === 404 => __('Repository o branch non trovati (se il repository è privato serve un token).'),
            $res['status'] === 403 || $res['status'] === 429 => __('Limite di richieste GitHub raggiunto: riprova tra qualche minuto.'),
            $res['status'] === 0 => __('Impossibile contattare GitHub: %s', (string)$res['error']),
            default => __('Risposta inattesa da GitHub (%s).', (string)$res['status']),
        };
    }

    public static function changes(string $from, string $to): array
    {
        if (!preg_match('/^[0-9a-f]{40}$/D', $from) || !preg_match('/^[0-9a-f]{40}$/D', $to)) {
            return ['status' => 'unknown', 'commits' => []];
        }
        $res = Http::request('GET', 'https://api.github.com/repos/' . self::repo() . '/compare/' . $from . '...' . $to, null, self::headers(), 15);
        if ($res['status'] !== 200) {
            return ['status' => 'unknown', 'commits' => []];
        }
        $commits = [];
        foreach (array_slice(array_reverse($res['json']['commits'] ?? []), 0, 15) as $c) {
            $commits[] = ['sha' => substr((string)$c['sha'], 0, 7), 'message' => strtok((string)($c['commit']['message'] ?? ''), "\n") ?: ''];
        }
        return ['status' => (string)($res['json']['status'] ?? 'unknown'), 'commits' => $commits];
    }

    public static function status(bool $force = false): array
    {
        $current = self::currentCommit();
        $latest = self::latest($force);
        $out = ['current' => $current, 'latest' => $latest, 'method' => self::method(), 'available' => false, 'changes' => [], 'error' => $latest['error'] ?? null];
        if (empty($latest['sha'])) {
            return $out;
        }
        if ($current === '') {
            $out['available'] = true;
            return $out;
        }
        if ($current !== $latest['sha']) {
            $cmp = self::changes($current, $latest['sha']);
            $out['available'] = !in_array($cmp['status'], ['ahead', 'identical'], true);
            $out['changes'] = $cmp['commits'];
        }
        return $out;
    }

    public static function availableCached(): bool
    {
        $c = json_decode((string)Settings::get('update_latest', ''), true);
        if (empty($c['sha']) || ($c['repo'] ?? '') !== self::repo()) {
            return false;
        }
        $cur = self::currentCommit();
        return $cur !== '' && $cur !== $c['sha'];
    }

    public static function apply(string $root = ROOT): array
    {
        @mkdir($root . '/storage', 0750, true);
        $lock = fopen($root . '/storage/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'message' => __('Un aggiornamento è già in corso.')];
        }
        try {
            if (!self::writable($root)) {
                return ['ok' => false, 'message' => __('I file del sito non sono scrivibili dal server web: sistema i permessi e riprova.')];
            }
            $from = self::currentCommit($root);
            self::backupDatabase($root);
            $res = self::method($root) === 'git' ? self::applyGit($root) : self::applyRemoteZip($root);
            if ($res['ok']) {
                self::finish($root, $from, (string)$res['sha']);
                $res['message'] = __('Aggiornamento completato.');
            }
            self::log($res['ok'] ? 'OK ' . substr($from, 0, 7) . ' -> ' . substr((string)$res['sha'], 0, 7) : 'ERRORE ' . $res['message']);
            return $res;
        } catch (\Throwable $e) {
            self::log('ERRORE ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function backupDatabase(string $root): void
    {
        if ($root !== ROOT) {
            return;
        }
        try {
            [$name, $path] = Backup::databaseFile();
            $dir = ROOT . '/storage/backups';
            @mkdir($dir, 0750, true);
            rename($path, $dir . '/pre-update-' . date('Ymd-His') . '-' . $name);
            $files = glob($dir . '/pre-update-*') ?: [];
            rsort($files);
            foreach (array_slice($files, 3) as $old) {
                @unlink($old);
            }
        } catch (\Throwable $e) {
            self::log('Backup del database non riuscito: ' . $e->getMessage());
        }
    }

    public static function applyGit(string $root): array
    {
        [$code, $dirty] = self::git($root, ['status', '--porcelain', '--untracked-files=no']);
        if ($code !== 0 || $dirty !== '') {
            return ['ok' => false, 'message' => __('Ci sono file del sito modificati a mano: l\'aggiornamento automatico è stato fermato per non perderli.') . ($dirty !== '' ? ' (' . implode(', ', array_slice(array_map(static fn($l) => trim(substr($l, 3)), explode("\n", $dirty)), 0, 5)) . ')' : '')];
        }
        [, $before] = self::git($root, ['rev-parse', 'HEAD']);
        [$code, $out] = self::git($root, ['fetch', '--quiet', 'origin', self::branch()]);
        if ($code !== 0) {
            return ['ok' => false, 'message' => __('Download da GitHub non riuscito: %s', $out)];
        }
        [$code, $out] = self::git($root, ['merge', '--ff-only', 'FETCH_HEAD']);
        if ($code !== 0) {
            return ['ok' => false, 'message' => __('Impossibile aggiornare senza conflitti: %s', $out)];
        }
        [, $after] = self::git($root, ['rev-parse', 'HEAD']);
        if ($before !== $after) {
            $state = self::state($root);
            $state['previous'] = $before;
            self::saveState($root, $state);
        }
        return ['ok' => true, 'sha' => $after, 'message' => ''];
    }

    private static function applyRemoteZip(string $root): array
    {
        if (!self::zipAvailable()) {
            return ['ok' => false, 'message' => __('L\'estensione PHP "zip" non è attiva e Git non è disponibile: non posso aggiornare da qui.')];
        }
        $latest = self::latest(true);
        if (empty($latest['sha'])) {
            return ['ok' => false, 'message' => (string)($latest['error'] ?? __('GitHub non raggiungibile.'))];
        }
        $data = Http::download('https://api.github.com/repos/' . self::repo() . '/zipball/' . $latest['sha'], 80_000_000, self::headers());
        if ($data === null || $data === '') {
            return ['ok' => false, 'message' => __('Download dell\'aggiornamento non riuscito.')];
        }
        @mkdir($root . '/storage/tmp', 0750, true);
        $zip = $root . '/storage/tmp/update-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($zip, $data);
        try {
            self::applyZip($zip, $root, (string)$latest['sha'], substr((string)$latest['sha'], 0, 7));
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            @unlink($zip);
        }
        return ['ok' => true, 'sha' => (string)$latest['sha'], 'message' => ''];
    }

    public static function applyZip(string $zipPath, string $root, string $sha = '', string $expectShort = ''): void
    {
        $za = new \ZipArchive();
        if ($za->open($zipPath) !== true) {
            throw new \RuntimeException(__('Il file di aggiornamento non è valido.'));
        }
        $first = (string)$za->getNameIndex(0);
        $prefix = str_contains($first, '/') ? substr($first, 0, strpos($first, '/') + 1) : '';
        if ($expectShort !== '' && !str_ends_with(rtrim($prefix, '/'), $expectShort)) {
            throw new \RuntimeException(__('Il pacchetto scaricato non corrisponde alla versione richiesta.'));
        }
        $stage = $root . '/storage/tmp/stage-' . bin2hex(random_bytes(4));
        $new = [];
        try {
            for ($i = 0; $i < $za->numFiles; $i++) {
                $name = (string)$za->getNameIndex($i);
                if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                    throw new \RuntimeException(__('Il file di aggiornamento ha una struttura inattesa.'));
                }
                $rel = substr($name, strlen($prefix));
                if ($rel === '' || str_ends_with($rel, '/')) {
                    continue;
                }
                if (str_contains($rel, '..') || str_contains($rel, '\\') || str_starts_with($rel, '/') || str_contains($rel, "\0")) {
                    throw new \RuntimeException(__('Il file di aggiornamento contiene percorsi non sicuri.'));
                }
                if (self::kept($rel)) {
                    continue;
                }
                $target = $stage . '/' . $rel;
                @mkdir(dirname($target), 0755, true);
                $stream = $za->getStream($name);
                $out = fopen($target, 'wb');
                if (!$stream || !$out) {
                    throw new \RuntimeException(__('Impossibile scompattare l\'aggiornamento.'));
                }
                stream_copy_to_stream($stream, $out);
                fclose($stream);
                fclose($out);
                $new[] = $rel;
            }
            $za->close();
            if (!in_array('app/bootstrap.php', $new, true) || !in_array('public/index.php', $new, true)) {
                throw new \RuntimeException(__('Il file di aggiornamento non sembra un pacchetto AlienShop.'));
            }
            $state = self::state($root);
            $obsolete = array_values(array_filter(array_diff((array)($state['files'] ?? []), $new), static fn($f) => !self::kept($f)));
            self::backupCode($root, $new, $obsolete, (string)($state['sha'] ?? ''));
            foreach ($new as $rel) {
                @mkdir(dirname($root . '/' . $rel), 0755, true);
                if (!@copy($stage . '/' . $rel, $root . '/' . $rel)) {
                    throw new \RuntimeException(__('Impossibile scrivere %s: controlla i permessi.', $rel));
                }
            }
            foreach ($obsolete as $rel) {
                @unlink($root . '/' . $rel);
            }
            $state['previous_sha'] = $state['sha'] ?? '';
            $state['sha'] = $sha;
            $state['files'] = $new;
            $state['updated_at'] = date('c');
            self::saveState($root, $state);
        } finally {
            self::rmTree($stage);
        }
    }

    private static function kept(string $rel): bool
    {
        foreach (self::KEEP as $k) {
            if ($rel === $k || str_starts_with($rel, $k)) {
                return true;
            }
        }
        return false;
    }

    private static function backupCode(string $root, array $new, array $obsolete, string $sha): void
    {
        if (!self::zipAvailable()) {
            return;
        }
        $dir = $root . '/storage/backups';
        @mkdir($dir, 0750, true);
        $file = $dir . '/code-' . date('Ymd-His') . '.zip';
        $za = new \ZipArchive();
        if ($za->open($file, \ZipArchive::CREATE) !== true) {
            return;
        }
        $count = 0;
        foreach ([...$new, ...$obsolete] as $rel) {
            if (is_file($root . '/' . $rel)) {
                $za->addFile($root . '/' . $rel, $rel);
                $count++;
            }
        }
        $za->close();
        if ($count === 0) {
            @unlink($file);
        }
        $old = glob($dir . '/code-*.zip') ?: [];
        rsort($old);
        foreach (array_slice($old, 3) as $f) {
            @unlink($f);
        }
    }

    public static function rollback(string $root = ROOT): array
    {
        $state = self::state($root);
        if (self::method($root) === 'git') {
            $prev = (string)($state['previous'] ?? '');
            if (!preg_match('/^[0-9a-f]{40}$/D', $prev)) {
                return ['ok' => false, 'message' => __('Nessuna versione precedente a cui tornare.')];
            }
            [$code, $out] = self::git($root, ['reset', '--hard', $prev]);
            if ($code !== 0) {
                return ['ok' => false, 'message' => $out];
            }
        } else {
            $files = glob($root . '/storage/backups/code-*.zip') ?: [];
            rsort($files);
            if (!$files || !self::zipAvailable()) {
                return ['ok' => false, 'message' => __('Nessuna versione precedente a cui tornare.')];
            }
            $za = new \ZipArchive();
            if ($za->open($files[0]) !== true || !$za->extractTo($root)) {
                return ['ok' => false, 'message' => __('Ripristino non riuscito.')];
            }
            $za->close();
            @unlink($files[0]);
            $state['sha'] = (string)($state['previous_sha'] ?? '');
            self::saveState($root, $state);
        }
        self::finish($root, '', '');
        self::log('ROLLBACK');
        return ['ok' => true, 'message' => __('Tornato alla versione precedente.')];
    }

    private static function finish(string $root, string $from, string $to): void
    {
        if ($root !== ROOT) {
            return;
        }
        self::forgetCommit();
        Settings::set('update_pending', '0');
        Settings::set('update_latest', '');
        Settings::set('update_applied_at', now());
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        Migrator::run();
        Cache::flush();
        Themes::build(\Alien\Core\View::theme());
    }

    public static function cron(): int
    {
        if ((string)Settings::get('update_auto', '0') !== '1') {
            return 0;
        }
        $pending = (string)Settings::get('update_pending', '0') === '1';
        $last = (int)Settings::get('update_last_auto', 0);
        if (!$pending && time() - $last < 86400) {
            return 0;
        }
        Settings::set('update_last_auto', time());
        $st = self::status(true);
        if (!$st['available']) {
            Settings::set('update_pending', '0');
            return 0;
        }
        return self::apply()['ok'] ? 1 : 0;
    }

    private static function log(string $line): void
    {
        @mkdir(ROOT . '/storage/logs', 0750, true);
        @file_put_contents(ROOT . '/storage/logs/update.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
