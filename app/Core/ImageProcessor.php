<?php
declare(strict_types=1);

namespace Alien\Core;

final class ImageProcessor
{
    private const MAX_EDGE = 2000;
    private const SIZES = [400, 800];

    public static function fromUpload(array $file, string $subdir = 'products'): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        return self::fromBinary((string)file_get_contents($file['tmp_name']), $subdir, pathinfo($file['name'], PATHINFO_FILENAME));
    }

    public static function fromBinary(string $data, string $subdir = 'products', string $hint = 'image'): ?string
    {
        $info = @getimagesizefromstring($data);
        if ($info === false) {
            return null;
        }
        $type = $info[2];
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $img = @imagecreatefromstring($data);
        if (!$img) {
            return null;
        }
        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : ($type === IMAGETYPE_PNG ? 'png' : 'jpg');
        $dir = ROOT . '/public/uploads/' . trim($subdir, '/') . '/' . date('Y/m');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            imagedestroy($img);
            return null;
        }
        $base = Str::slug($hint, 50) . '-' . substr(Str::randomToken(4), 0, 6);
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $w = imagesx($img);
        $h = imagesy($img);
        $main = self::resize($img, $w, $h, self::MAX_EDGE);
        self::write($main, $dir . '/' . $base . '.' . $ext, $ext);
        foreach (self::SIZES as $size) {
            if ($w > $size) {
                $thumb = self::resize($img, $w, $h, $size);
                self::write($thumb, $dir . '/' . $base . '-' . $size . '.' . $ext, $ext);
                imagedestroy($thumb);
            }
        }
        if ($main !== $img) {
            imagedestroy($main);
        }
        imagedestroy($img);
        return trim($subdir, '/') . '/' . date('Y/m') . '/' . $base . '.' . $ext;
    }

    public static function fromUrl(string $url, string $subdir = 'products', string $hint = 'image'): ?string
    {
        $data = Http::download($url);
        return $data === null ? null : self::fromBinary($data, $subdir, $hint);
    }

    public static function delete(?string $path): void
    {
        if (!$path || preg_match('#^https?://|\.\.#', $path)) {
            return;
        }
        $info = pathinfo($path);
        $dir = ROOT . '/public/uploads/' . ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '');
        foreach (array_merge([''], array_map(static fn($s) => '-' . $s, self::SIZES)) as $suffix) {
            @unlink($dir . $info['filename'] . $suffix . '.' . $info['extension']);
        }
    }

    private static function resize(\GdImage $img, int $w, int $h, int $max): \GdImage
    {
        if ($w <= $max && $h <= $max) {
            return $img;
        }
        $ratio = $w >= $h ? $max / $w : $max / $h;
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }

    private static function write(\GdImage $img, string $file, string $ext): void
    {
        match ($ext) {
            'webp' => imagewebp($img, $file, 82),
            'png' => imagepng($img, $file, 7),
            default => imagejpeg($img, $file, 84),
        };
    }
}
