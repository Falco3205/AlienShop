<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

const ALIEN_VERSION = '1.0.0';

mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Alien\\')) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/Core/helpers.php';
