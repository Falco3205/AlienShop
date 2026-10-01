<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Settings;
use Alien\Core\Str;

final class Newsletter
{
    public static function subscribe(string $email, string $name = '', string $source = 'footer'): ?string
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('Inserisci un indirizzo email valido.');
        }
        $row = DB::row('SELECT * FROM subscribers WHERE email = ?', [$email]);
        if ($row && $row['status'] === 'confirmed') {
            return null;
        }
        $token = $row['token'] ?? Str::randomToken(16);
        if ($row) {
            DB::update('subscribers', ['status' => 'pending'], 'id = ?', [$row['id']]);
        } else {
            DB::insert('subscribers', ['email' => $email, 'name' => mb_substr($name, 0, 120), 'status' => 'pending', 'token' => $token, 'source' => $source, 'created_at' => now()]);
        }
        $link = url('newsletter/confirm/' . $token);
        Mailer::send($email, __('Conferma la tua iscrizione a %s', Settings::get('store_name', '')), '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto"><p>' . e(__('Conferma la tua iscrizione alla newsletter cliccando qui sotto.')) . '</p><p><a href="' . e($link) . '" style="background:#111;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">' . e(__('Conferma iscrizione')) . '</a></p><p style="color:#777;font-size:12px">' . e(__('Se non sei stato tu, ignora questa email.')) . '</p></div>');
        return null;
    }

    public static function confirm(string $token): bool
    {
        return DB::update('subscribers', ['status' => 'confirmed'], "token = ? AND status <> 'unsubscribed'", [$token]) > 0
            || (bool)DB::val("SELECT 1 FROM subscribers WHERE token = ? AND status = 'confirmed'", [$token]);
    }

    public static function unsubscribe(string $token): bool
    {
        return DB::update('subscribers', ['status' => 'unsubscribed'], 'token = ?', [$token]) > 0;
    }

    public static function counts(): array
    {
        $c = ['confirmed' => 0, 'pending' => 0, 'unsubscribed' => 0];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM subscribers GROUP BY status') as $r) {
            $c[$r['status']] = (int)$r['n'];
        }
        return $c;
    }

    public static function campaign(string $subject, string $html): int
    {
        $campaign = 'c' . date('YmdHis');
        $n = 0;
        foreach (DB::all("SELECT email, token FROM subscribers WHERE status = 'confirmed'") as $s) {
            $unsub = url('newsletter/unsubscribe/' . $s['token']);
            $body = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto">' . Str::sanitizeHtml($html)
                . '<hr style="border:0;border-top:1px solid #ddd;margin:24px 0"><p style="color:#777;font-size:12px">' . e(__('Ricevi questa email perché ti sei iscritto alla newsletter di %s.', Settings::get('store_name', '')))
                . ' <a href="' . e($unsub) . '">' . e(__('Annulla iscrizione')) . '</a></p></div>';
            Cron::queue($s['email'], $subject, $body, $campaign);
            $n++;
        }
        return $n;
    }

    public static function csv(): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['email', 'name', 'status', 'source', 'created_at'], ',', '"', '\\');
        foreach (DB::all('SELECT email, name, status, source, created_at FROM subscribers ORDER BY id') as $r) {
            fputcsv($fh, \Alien\Core\Str::csvRow($r), ',', '"', '\\');
        }
        rewind($fh);
        return (string)stream_get_contents($fh);
    }
}
