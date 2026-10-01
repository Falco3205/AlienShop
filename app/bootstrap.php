<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

define('ALIEN_VERSION', require __DIR__ . '/version.php');

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
