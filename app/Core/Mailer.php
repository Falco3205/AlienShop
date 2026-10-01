<?php
declare(strict_types=1);

namespace Alien\Core;

final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $from = (string)Settings::get('mail_from', Settings::get('store_email', 'noreply@localhost'));
        $fromName = (string)Settings::get('store_name', 'AlienShop');
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</tr>#i', "\n", $html) ?? $html)));
        $boundary = 'b' . Str::randomToken(8);
        $headers = [
            'From: ' . self::encode($fromName) . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--";

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

    private static function encode(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function log(string $to, string $subject, string $text): void
    {
        @file_put_contents(ROOT . '/storage/logs/mail.log', '[' . now() . "] To: $to | $subject\n$text\n\n", FILE_APPEND);
    }

    private static function smtp(string $from, string $to, string $subject, array $headers, string $body): bool
    {
        $host = (string)Settings::get('smtp_host');
        $port = (int)Settings::get('smtp_port', 587);
        $secure = (string)Settings::get('smtp_secure', 'tls');
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
        if (Settings::get('smtp_user')) {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode((string)Settings::get('smtp_user')), '334');
            $cmd(base64_encode((string)Settings::get('smtp_pass')), '235');
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
