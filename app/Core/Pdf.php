<?php
declare(strict_types=1);

namespace Alien\Core;

final class Pdf
{
    private const REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const BOLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    private array $pages = [];
    private string $buf = '';
    public const W = 595;
    public const H = 842;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->buf !== '') {
            $this->pages[] = $this->buf;
        }
        $this->buf = '';
    }

    private static function enc(string $s): string
    {
        $c = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        return $c === false ? preg_replace('/[^\x20-\x7e]/', '?', $s) : $c;
    }

    public function width(string $s, float $size, bool $bold = false): float
    {
        $t = $bold ? self::BOLD : self::REG;
        $w = 0;
        foreach (str_split(self::enc($s)) as $ch) {
            $o = ord($ch);
            $w += ($o >= 32 && $o <= 126) ? $t[$o - 32] : 556;
        }
        return $w * $size / 1000;
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $align = 'L', array $color = [0, 0, 0]): void
    {
        if ($align === 'R') {
            $x -= $this->width($s, $size, $bold);
        } elseif ($align === 'C') {
            $x -= $this->width($s, $size, $bold) / 2;
        }
        $e = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], self::enc($s));
        $this->buf .= sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color[0], $color[1], $color[2], $x, self::H - $y, $e);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.5, array $color = [0.8, 0.8, 0.8]): void
    {
        $this->buf .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $color[0], $color[1], $color[2], $w, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    public function rect(float $x, float $y, float $w, float $h, array $fill): void
    {
        $this->buf .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $fill[0], $fill[1], $fill[2], $x, self::H - $y - $h, $w, $h);
    }

    public function wrap(string $s, float $maxWidth, float $size, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $s) ?: [''] as $para) {
            $cur = '';
            foreach (preg_split('/\s+/', $para) ?: [] as $word) {
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($cur !== '' && $this->width($try, $size, $bold) > $maxWidth) {
                    $lines[] = $cur;
                    $cur = $word;
                } else {
                    $cur = $try;
                }
            }
            $lines[] = $cur;
        }
        return $lines;
    }

    public function output(): string
    {
        $pages = $this->pages;
        if ($this->buf !== '' || !$pages) {
            $pages[] = $this->buf;
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = count($pages);
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($pages as $i => $content) {
            $pageId = 5 + $i * 2;
            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageId + 1) . ' 0 R >>';
            $objs[$pageId + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }
}
