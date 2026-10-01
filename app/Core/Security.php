<?php
declare(strict_types=1);

namespace Alien\Core;

final class Security
{
    public static function headers(Request $req, bool $hub = false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: ' . ($hub ? 'DENY' : 'SAMEORIGIN'));
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        header_remove('X-Powered-By');
        if ((string)Config::get('app.csp', 'standard') !== 'off' || $hub) {
            header('Content-Security-Policy: ' . self::csp($req, $hub));
        }
    }

    public static function extraSources(): array
    {
        $out = [];
        foreach ((array)Config::get('app.csp_extra', []) as $h) {
            if (is_string($h) && self::validSource($h)) {
                $out[] = $h;
            }
        }
        return $out;
    }

    public static function validSource(string $s): bool
    {
        return (bool)preg_match('#^https://(\*\.)?[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?\z#', $s);
    }

    public static function csp(Request $req, bool $hub = false): string
    {
        $strict = $hub || str_starts_with($req->path, '/admin') || str_starts_with($req->path, '/install');
        $upgrade = is_https() ? '; upgrade-insecure-requests' : '';
        if ($strict) {
            return "default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors " . ($hub ? "'none'" : "'self'") . "; base-uri 'self'; form-action 'self'; object-src 'none'" . $upgrade;
        }
        $extra = implode(' ', self::extraSources());
        $ga = 'https://www.googletagmanager.com';
        $gaConnect = 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com';
        return "default-src 'self'; script-src 'self' 'unsafe-inline' $ga $extra; style-src 'self' 'unsafe-inline' $extra; img-src 'self' data: https:; font-src 'self' data: $extra; connect-src 'self' $gaConnect $extra; frame-src 'self' $extra; frame-ancestors 'self'; base-uri 'self'; object-src 'none'" . $upgrade;
    }
}
