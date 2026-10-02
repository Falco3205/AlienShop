<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\DB;
use Alien\Core\Settings;

final class SelfUpdate
{
    public static function run(): array
    {
        try {
            return self::pull();
        } catch (\Throwable $e) {
            @file_put_contents(ROOT . '/storage/logs/error.log', '[' . now() . '] SelfUpdate: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            return ['ok' => false, 'message' => 'Aggiornamento non riuscito: ' . $e->getMessage()];
        }
    }

    public static function runIfRequested(): ?array
    {
        if (!Settings::get('update_requested', '')) {
            return null;
        }
        Settings::set('update_requested', '');
        return self::run();
    }

    private static function pull(): array
    {
        if (!is_dir(REPO . '/.git')) {
            return ['ok' => false, 'message' => 'L\'hub non è stato installato con Git: aggiornalo riscaricando i file.'];
        }
        if (!function_exists('proc_open')) {
            if (PHP_SAPI === 'cli') {
                return ['ok' => false, 'message' => 'proc_open è disabilitata anche in PHP da terminale.'];
            }
            Settings::set('update_requested', now());
            return ['ok' => true, 'message' => 'Aggiornamento in coda: parte entro 5 minuti (PHP-FPM non può eseguire git, lo fa il cron). Oppure: php hub/bin/hub update'];
        }
        $path = (string)getenv('PATH');
        $env = ['GIT_TERMINAL_PROMPT' => '0', 'PATH' => $path !== '' ? $path : '/usr/local/bin:/usr/bin:/bin', 'HOME' => ROOT . '/storage'];
        $proc = @proc_open(['git', '-c', 'safe.directory=' . REPO, '-C', REPO, 'pull', '--ff-only'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['ok' => false, 'message' => 'Git non disponibile.'];
        }
        $out = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
        $code = proc_close($proc);
        if ($code !== 0) {
            return ['ok' => false, 'message' => 'Aggiornamento non riuscito: ' . $out];
        }
        \Alien\Services\Installer::createSchema(DB::pdo(), DB::driver());
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return ['ok' => true, 'message' => 'Hub aggiornato. ' . strtok($out, "\n")];
    }
}
