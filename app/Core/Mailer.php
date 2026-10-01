<?php
declare(strict_types=1);

namespace Alien\Core;

final class Mailer
{
    public static function send(string $to, string $subject, string $html, array $attachments = []): bool
    {
        $from = (string)Settings::get('mail_from', Settings::get('store_email', 'noreply@localhost'));
        $fromName = (string)Settings::get('store_name', 'AlienShop');
        [$text, $headers, $body] = self::compose($from, $fromName, $html, $attachments);

        $driver = (string)Settings::get('mail_driver', 'mail');
        try {
            if ($driver === 'smtp' && Settings::get('smtp_host')) {
                return self::smtp($from, $to, self::encode($subject), $headers, $body);
            }
            if ($driver === 'log') {
                self::log($to, $subject, $text);
                return true;
            }
            return @mail($to, self::encode($subject), $body, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            self::log($to, '[ERRORE] ' . $subject, $e->getMessage());
            return false;
        }
    }

    private static function compose(string $from, string $fromName, string $html, array $attachments): array
    {
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</tr>#i', "\n", $html) ?? $html)));
        $boundary = 'b' . Str::randomToken(8);
        $alt = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--";
        if ($attachments) {
            $outer = 'm' . Str::randomToken(8);
            $body = "--$outer\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $alt . "\r\n";
            foreach ($attachments as $a) {
                $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$a['name']);
                $body .= "--$outer\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n" . chunk_split(base64_encode((string)$a['data']));
            }
            $body .= "--$outer--";
            $contentType = 'multipart/mixed; boundary="' . $outer . '"';
        } else {
            $body = $alt;
            $contentType = 'multipart/alternative; boundary="' . $boundary . '"';
        }
        $headers = [
            'From: ' . self::encode($fromName) . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
        ];
        return [$text, $headers, $body];
    }

    public static function sendSmtp(array $cfg, string $from, string $fromName, string $to, string $subject, string $html, array $attachments = []): void
    {
        [, $headers, $body] = self::compose($from, $fromName, $html, $attachments);
        self::smtp($from, $to, self::encode($subject), $headers, $body, $cfg);
    }

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function log(string $to, string $subject, string $text): void
    {
        @file_put_contents(ROOT . '/storage/logs/mail.log', '[' . now() . "] To: $to | $subject\n$text\n\n", FILE_APPEND);
    }

    private static function smtp(string $from, string $to, string $subject, array $headers, string $body, ?array $cfg = null): bool
    {
        $cfg ??= ['host' => (string)Settings::get('smtp_host'), 'port' => (int)Settings::get('smtp_port', 587), 'secure' => (string)Settings::get('smtp_secure', 'tls'), 'user' => (string)Settings::get('smtp_user'), 'pass' => Secret::open((string)Settings::get('smtp_pass'))];
        $host = (string)$cfg['host'];
        $port = (int)$cfg['port'];
        $secure = (string)$cfg['secure'];
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 12);
        if (!$fp) {
            throw new \RuntimeException("SMTP: $errstr");
        }
        stream_set_timeout($fp, 12);
        $read = static function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $c, string $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) {
                throw new \RuntimeException("SMTP: $r");
            }
            return $r;
        };
        $read();
        $cmd('EHLO ' . (parse_url(Config::baseUrl(), PHP_URL_HOST) ?: 'localhost'), '250');
        if ($secure === 'tls') {
            $cmd('STARTTLS', '220');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO localhost', '250');
        }
        if (($cfg['user'] ?? '') !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode((string)$cfg['user']), '334');
            $cmd(base64_encode((string)$cfg['pass']), '235');
        }
        $cmd('MAIL FROM:<' . $from . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');
        $msg = 'To: <' . $to . ">\r\nSubject: $subject\r\nDate: " . date('r') . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', $msg);
        $cmd($msg . "\r\n.", '250');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
