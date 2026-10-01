<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\DB;

final class SelfUpdate
{
    public static function run(): array
    {
        if (!is_dir(REPO . '/.git')) {
            return ['ok' => false, 'message' => 'L\'hub non è stato installato con Git: aggiornalo riscaricando i file.'];
        }
        $proc = @proc_open(['git', '-C', REPO, 'pull', '--ff-only'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['GIT_TERMINAL_PROMPT' => '0', 'PATH' => (string)getenv('PATH'), 'HOME' => (string)(getenv('HOME') ?: sys_get_temp_dir())]);
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
