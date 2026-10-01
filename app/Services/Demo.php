<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\ImageProcessor;

final class Demo
{
    public static function seed(string $locale): void
    {
        $en = $locale === 'en';
        $cats = [
            'abbigliamento' => [$en ? 'Clothing' : 'Abbigliamento', 'f4a261'],
            'accessori' => [$en ? 'Accessories' : 'Accessori', '2a9d8f'],
            'casa' => [$en ? 'Home' : 'Casa', 'e76f51'],
        ];
        $ids = [];
        foreach ($cats as $slug => [$name, $color]) {
            $ids[$slug] = Catalog::saveCategory(['name' => $name, 'slug' => $slug, 'is_active' => 1, 'show_in_menu' => 1, 'image' => self::image($name, $color, 'categories')]);
        }
        $products = [
            ['T-shirt Essential', 'abbigliamento', 2490, 0, '264653', 'variable', [['name' => $en ? 'Size' : 'Taglia', 'values' => "S\nM\nL\nXL|+2.00"], ['name' => $en ? 'Color' : 'Colore', 'values' => ($en ? "Black\nWhite" : "Nero\nBianco")]], 1],
            ['Felpa Comfort', 'abbigliamento', 4990, 5990, '3d5a80', 'variable', [['name' => $en ? 'Size' : 'Taglia', 'values' => "S\nM\nL|+0\nXL|+3.00"]], 1],
            ['Cappellino Urban', 'accessori', 1990, 0, 'e9c46a', 'simple', [], 0],
            ['Zaino Daily', 'accessori', 5900, 0, '2a9d8f', 'simple', [], 1],
            ['Borraccia Steel', 'casa', 1790, 2290, '457b9d', 'simple', [], 0],
            ['Candela Profumata', 'casa', 1490, 0, 'e76f51', 'variable', [['name' => $en ? 'Scent' : 'Profumo', 'values' => ($en ? "Vanilla\nLavender\nAmber|+1.50" : "Vaniglia\nLavanda\nAmbra|+1.50")]], 0],
            ['Tazza Ceramica', 'casa', 1290, 0, 'bc6c25', 'simple', [], 0],
            ['Sciarpa Lana', 'abbigliamento', 3490, 0, '9b5de5', 'simple', [], 0],
        ];
        $lorem = $en
            ? '<p>Carefully designed and made with quality materials. A demo product to show off your theme: replace it with your own catalogue.</p><ul><li>Premium quality</li><li>Fast shipping</li><li>14-day returns</li></ul>'
            : '<p>Progettato con cura e realizzato con materiali di qualità. Un prodotto dimostrativo per mostrare il tuo tema: sostituiscilo con il tuo catalogo.</p><ul><li>Qualità premium</li><li>Spedizione rapida</li><li>Reso entro 14 giorni</li></ul>';
        foreach ($products as $i => [$name, $cat, $price, $compare, $color, $type, $attrs, $featured]) {
            $id = Catalog::save([
                'name' => $name, 'type' => $type, 'status' => 'active', 'price' => $price, 'compare_price' => $compare,
                'sku' => 'DEMO-' . (100 + $i), 'description' => $lorem, 'short_description' => $en ? 'A great everyday essential.' : 'Un indispensabile per ogni giorno.',
                'category_ids' => [$ids[$cat]], 'featured' => $featured, 'manage_stock' => 1, 'stock_qty' => 50, 'vendor' => 'AlienShop',
                'attributes' => array_map(static fn($a) => ['name' => $a['name'], 'values' => Catalog::parseAttributeLines($a['values'])], $attrs),
            ]);
            if ($type === 'variable') {
                \Alien\Core\DB::exec('UPDATE variants SET stock = 25 WHERE product_id = ?', [$id]);
                Catalog::refreshDerived($id);
            }
            if ($img = self::image($name, $color, 'products')) {
                Catalog::addImage($id, $img, $name);
            }
        }
        \Alien\Core\DB::insert('coupons', ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 0, 'max_uses' => 0, 'used' => 0, 'free_shipping' => 0, 'active' => 1]);
    }

    private static function image(string $label, string $hex, string $dir): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $w = 900;
        $img = imagecreatetruecolor($w, $w);
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        for ($y = 0; $y < $w; $y++) {
            $t = $y / $w;
            $c = imagecolorallocate($img, (int)($r + (255 - $r) * $t * 0.45), (int)($g + (255 - $g) * $t * 0.45), (int)($b + (255 - $b) * $t * 0.45));
            imageline($img, 0, $y, $w, $y, $c);
        }
        $white = imagecolorallocatealpha($img, 255, 255, 255, 70);
        imagefilledellipse($img, (int)($w * 0.5), (int)($w * 0.46), (int)($w * 0.5), (int)($w * 0.5), $white);
        $text = imagecolorallocate($img, 255, 255, 255);
        $font = 5;
        $tw = imagefontwidth($font) * strlen($label);
        imagestring($img, $font, (int)(($w - $tw) / 2), (int)($w * 0.82), $label, $text);
        ob_start();
        imagepng($img);
        $png = (string)ob_get_clean();
        imagedestroy($img);
        return ImageProcessor::fromBinary($png, $dir, $label);
    }
}
