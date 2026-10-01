<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class Imap
{
    /** @var resource|null */
    private $fp = null;
    private int $tag = 0;

    public function __construct(private readonly array $cfg)
    {
    }

    public function connect(): void
    {
        $secure = (string)($this->cfg['secure'] ?? 'ssl');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $this->cfg['host'] . ':' . (int)$this->cfg['port'];
        $this->fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$this->fp) {
            throw new \RuntimeException('IMAP: ' . $errstr);
        }
        stream_set_timeout($this->fp, 30);
        $greeting = (string)fgets($this->fp);
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new \RuntimeException('IMAP: ' . trim($greeting));
        }
        if ($secure === 'tls') {
            $this->command('STARTTLS');
            stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        $this->command('LOGIN ' . $this->quote((string)$this->cfg['user']) . ' ' . $this->quote((string)$this->cfg['pass']));
        $this->command('SELECT INBOX');
    }

    private function quote(string $s): string
    {
        return '"' . addcslashes($s, "\\\"") . '"';
    }

    private function command(string $cmd): array
    {
        $tag = 'a' . ++$this->tag;
        fwrite($this->fp, "$tag $cmd\r\n");
        $lines = [];
        $literals = [];
        while (($line = fgets($this->fp)) !== false) {
            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $size = (int)$m[1];
                $data = '';
                while (strlen($data) < $size) {
                    $chunk = fread($this->fp, $size - strlen($data));
                    if ($chunk === false || $chunk === '') {
                        throw new \RuntimeException('IMAP: connessione interrotta');
                    }
                    $data .= $chunk;
                }
                $literals[] = $data;
                $lines[] = $line;
                continue;
            }
            if (str_starts_with($line, $tag . ' ')) {
                if (!preg_match('/^' . $tag . ' OK/i', $line)) {
                    throw new \RuntimeException('IMAP: ' . trim($line));
                }
                return ['lines' => $lines, 'literals' => $literals];
            }
            $lines[] = $line;
        }
        throw new \RuntimeException('IMAP: connessione interrotta');
    }

    public function unseen(int $limit = 50): array
    {
        $res = $this->command('UID SEARCH UNSEEN');
        foreach ($res['lines'] as $l) {
            if (preg_match('/^\* SEARCH ?(.*)$/', trim($l), $m)) {
                $uids = array_values(array_filter(array_map('intval', explode(' ', trim($m[1])))));
                return array_slice($uids, 0, $limit);
            }
        }
        return [];
    }

    public function fetch(int $uid): string
    {
        $res = $this->command("UID FETCH $uid BODY.PEEK[]");
        return $res['literals'][0] ?? '';
    }

    public function markSeen(int $uid): void
    {
        $this->command("UID STORE $uid +FLAGS (\\Seen)");
    }

    public function close(): void
    {
        if ($this->fp) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
            }
            @fclose($this->fp);
            $this->fp = null;
        }
    }
}
