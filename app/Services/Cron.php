<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Cache;
use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Settings;

final class Cron
{
    private const INTERVAL = 60;

    public static function maybeRun(): void
    {
        $file = ROOT . '/storage/cron.lock';
        $last = is_file($file) ? (int)filemtime($file) : 0;
        if (time() - $last < self::INTERVAL) {
            return;
        }
        if (!@touch($file)) {
            return;
        }
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            flush();
        }
        try {
            self::run();
        } catch (\Throwable $e) {
            @file_put_contents(ROOT . '/storage/logs/cron.log', '[' . now() . '] ' . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    public static function run(): array
    {
        $report = [
            'expired' => self::expireUnpaid(),
            'mails' => self::sendQueue(),
            'reminders' => Modules::on('abandoned_cart') ? AbandonedCarts::sendReminders() : 0,
            'einvoice' => EInvoices::cron(),
            'review_requests' => Modules::on('reviews') ? Reviews::sendRequests() : 0,
        ];
        $report['update'] = Updater::cron();
        Settings::set('cron_last', now());
        return $report;
    }

    public static function expireUnpaid(int $hours = 48): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $hours * 3600);
        $n = 0;
        foreach (DB::all("SELECT id FROM orders WHERE status = 'pending' AND payment_status IN ('unpaid','failed') AND payment_method IN ('stripe','paypal','mollie') AND created_at < ?", [$cutoff]) as $o) {
            Orders::setStatus((int)$o['id'], 'cancelled');
            $n++;
        }
        return $n;
    }

    public static function sendQueue(int $batch = 30): int
    {
        $sent = 0;
        foreach (DB::all('SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < 3 ORDER BY id LIMIT ' . $batch) as $m) {
            DB::exec('UPDATE mail_queue SET attempts = attempts + 1 WHERE id = ?', [$m['id']]);
            if (Mailer::send($m['to_email'], $m['subject'], $m['body'])) {
                DB::update('mail_queue', ['sent_at' => now()], 'id = ?', [$m['id']]);
                $sent++;
            }
        }
        DB::exec('DELETE FROM mail_queue WHERE sent_at IS NOT NULL AND sent_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        return $sent;
    }

    public static function queue(string $to, string $subject, string $body, string $campaign = ''): void
    {
        DB::insert('mail_queue', ['to_email' => $to, 'subject' => $subject, 'body' => $body, 'campaign' => $campaign, 'created_at' => now()]);
    }
}
