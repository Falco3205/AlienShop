<?php
declare(strict_types=1);

use Alien\Core\Money;
use Hub\Shops;

function h_money(int $cents, string $currency = 'EUR'): string
{
    return Money::format($cents, $currency);
}

function h_ago(?string $date): string
{
    if (!$date) {
        return 'mai';
    }
    $s = time() - (int)strtotime($date);
    return match (true) {
        $s < 90 => 'adesso',
        $s < 3600 => intdiv($s, 60) . ' min fa',
        $s < 86400 => intdiv($s, 3600) . ' h fa',
        default => intdiv($s, 86400) . ' g fa',
    };
}

function h_status(array $shop): string
{
    if ($shop['status'] === 'active' && Hub\Fleet::offline($shop)) {
        return '<span class="pill bad">Non risponde</span>';
    }
    $cls = ['active' => 'ok', 'error' => 'bad', 'suspended' => 'warn', 'pending' => 'info', 'installing' => 'info'][$shop['status']] ?? '';
    return '<span class="pill ' . $cls . '">' . e(Shops::STATUS[$shop['status']] ?? $shop['status']) . '</span>';
}

function h_spark(array $series, int $w = 120, int $h = 32): string
{
    if (count($series) < 2 || max($series) <= 0) {
        return '<svg width="' . $w . '" height="' . $h . '" aria-hidden="true"></svg>';
    }
    $max = max($series);
    $step = $w / (count($series) - 1);
    $pts = [];
    foreach ($series as $i => $v) {
        $pts[] = round($i * $step, 1) . ',' . round($h - 2 - ($v / $max) * ($h - 4), 1);
    }
    return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><polyline fill="none" stroke="#6c4cf5" stroke-width="2" points="' . implode(' ', $pts) . '"/></svg>';
}

function h_bars(array $daily, string $currency = 'EUR', int $h = 160): string
{
    if (!$daily) {
        return '';
    }
    $max = max(1, max($daily));
    $n = count($daily);
    $w = 720;
    $bw = $w / $n;
    $out = '<svg viewBox="0 0 ' . $w . ' ' . ($h + 18) . '" width="100%" role="img" aria-label="Incassi giornalieri">';
    $i = 0;
    foreach ($daily as $day => $v) {
        $bh = $v > 0 ? max(2, ($v / $max) * $h) : 1;
        $out .= '<rect x="' . round($i * $bw + 1, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw - 2, 1) . '" height="' . round($bh, 1) . '" rx="2" fill="#6c4cf5" opacity=".85"><title>' . e($day . ': ' . h_money((int)$v, $currency)) . '</title></rect>';
        $i++;
    }
    $keys = array_keys($daily);
    $out .= '<text x="0" y="' . ($h + 14) . '" font-size="11" fill="#888">' . e(substr((string)$keys[0], 5)) . '</text><text x="' . $w . '" y="' . ($h + 14) . '" font-size="11" fill="#888" text-anchor="end">' . e(substr((string)end($keys), 5)) . '</text></svg>';
    return $out;
}

function h_trend(int $now, int $prev): string
{
    if ($prev <= 0) {
        return '';
    }
    $p = round(($now - $prev) / $prev * 100);
    return '<small style="color:' . ($p >= 0 ? '#136b3b' : '#9c1c30') . '">' . ($p >= 0 ? '▲ ' : '▼ ') . abs($p) . '% vs 30 g prima</small>';
}
