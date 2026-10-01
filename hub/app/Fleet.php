<?php
declare(strict_types=1);

namespace Hub;

final class Fleet
{
    public static function summary(array $shops): array
    {
        $out = ['active' => 0, 'offline' => 0, 'orders_today' => 0, 'revenue_today' => 0, 'orders_30d' => 0, 'revenue_30d' => 0, 'revenue_prev_30d' => 0, 'to_ship' => 0, 'currency' => 'EUR', 'daily' => []];
        $currencies = [];
        foreach ($shops as $s) {
            if ($s['status'] !== 'active') {
                continue;
            }
            $out['active']++;
            if (self::offline($s)) {
                $out['offline']++;
            }
            $m = Shops::metrics($s);
            if (!$m) {
                continue;
            }
            $cur = (string)($m['currency'] ?? 'EUR');
            $currencies[$cur] = ($currencies[$cur] ?? 0) + 1;
            if ($cur !== ($out['main_currency'] ?? $cur)) {
                continue;
            }
            $out['main_currency'] = $cur;
            foreach (['orders_today', 'revenue_today', 'orders_30d', 'revenue_30d', 'to_ship'] as $k) {
                $out[$k] += (int)($m[$k] ?? 0);
            }
            $out['revenue_prev_30d'] += (int)($m['revenue_prev_30d'] ?? 0);
            foreach ($m['daily'] ?? [] as $d) {
                $out['daily'][$d['d']] = ($out['daily'][$d['d']] ?? 0) + (int)$d['t'];
            }
        }
        arsort($currencies);
        $out['currency'] = (string)(array_key_first($currencies) ?? 'EUR');
        $out['mixed_currencies'] = count($currencies) > 1;
        ksort($out['daily']);
        return $out;
    }

    public static function offline(array $shop): bool
    {
        return $shop['status'] === 'active' && (empty($shop['last_ok']) || strtotime($shop['last_ok']) < time() - 900);
    }

    public static function series(array $shop): array
    {
        return array_map(static fn($d) => (int)$d['t'], Shops::metrics($shop)['daily'] ?? []);
    }

    public static function alerts(array $shops, array $nodes): array
    {
        $a = [];
        foreach ($nodes as $n) {
            if (!Nodes::online($n)) {
                $a[] = ['level' => 'bad', 'shop' => null, 'text' => 'Il server "' . $n['name'] . '" non risponde da più di 5 minuti.'];
            }
        }
        foreach ($shops as $s) {
            $link = $s['id'];
            $name = $s['name'];
            if ($s['status'] === 'error') {
                $a[] = ['level' => 'bad', 'shop' => $link, 'text' => $name . ': installazione non riuscita. ' . $s['last_error']];
                continue;
            }
            if ($s['status'] !== 'active') {
                continue;
            }
            if (self::offline($s)) {
                $a[] = ['level' => 'bad', 'shop' => $link, 'text' => $name . ' non risponde. ' . $s['last_error']];
                continue;
            }
            $m = Shops::metrics($s);
            if (!empty($m['update_available'])) {
                $a[] = ['level' => 'info', 'shop' => $link, 'text' => $name . ': aggiornamento disponibile.'];
            }
            if (($m['errors_24h'] ?? 0) > 0) {
                $a[] = ['level' => 'warn', 'shop' => $link, 'text' => $name . ': ' . $m['errors_24h'] . ' errori nel log delle ultime 24 ore.'];
            }
            if (($m['to_ship'] ?? 0) > 0) {
                $a[] = ['level' => 'info', 'shop' => $link, 'text' => $name . ': ' . $m['to_ship'] . ' ordini da spedire.'];
            }
            if (($m['out_of_stock'] ?? 0) > 0) {
                $a[] = ['level' => 'info', 'shop' => $link, 'text' => $name . ': ' . $m['out_of_stock'] . ' prodotti esauriti.'];
            }
            if ($m && empty($m['payments_on'])) {
                $a[] = ['level' => 'info', 'shop' => $link, 'text' => $name . ': nessun pagamento online attivo (solo bonifico/contrassegno).'];
            }
        }
        $rank = ['bad' => 0, 'warn' => 1, 'info' => 2];
        usort($a, static fn($x, $y) => $rank[$x['level']] <=> $rank[$y['level']]);
        return $a;
    }
}
