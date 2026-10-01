<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class P7m
{
    public static function xml(string $data): ?string
    {
        $trim = ltrim($data, "\xEF\xBB\xBF \t\r\n");
        if (str_starts_with($trim, '<')) {
            return $trim;
        }
        $der = preg_match('/^[A-Za-z0-9+\/=\r\n]+$/D', $trim) && strlen($trim) > 100 ? (string)base64_decode($trim, true) : $data;
        if ($der === '') {
            $der = $data;
        }
        if (function_exists('openssl_pkcs7_verify')) {
            $dir = ROOT . '/storage/tmp';
            @mkdir($dir, 0750, true);
            $in = $dir . '/p7-' . bin2hex(random_bytes(4)) . '.pem';
            $out = $in . '.out';
            file_put_contents($in, "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PKCS7-----\n");
            @openssl_pkcs7_verify($in, PKCS7_NOVERIFY | PKCS7_NOSIGS, null, [], null, $out);
            $content = is_file($out) ? (string)file_get_contents($out) : '';
            @unlink($in);
            @unlink($out);
            if (str_contains($content, '<') && str_contains($content, 'FatturaElettronica')) {
                return ltrim($content, "\xEF\xBB\xBF \t\r\n");
            }
        }
        $start = strpos($der, '<?xml');
        $startTag = $start === false ? strpos($der, '<p:FatturaElettronica') : $start;
        $end = strrpos($der, 'FatturaElettronica>');
        if ($startTag === false || $end === false) {
            return null;
        }
        $xml = substr($der, $startTag, $end + strlen('FatturaElettronica>') - $startTag);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml);
    }
}
