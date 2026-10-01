<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('REPO', dirname(ROOT));
define('ALIEN_VERSION', require REPO . '/app/version.php');

mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    $map = ['Alien\\' => REPO . '/app/', 'Hub\\' => ROOT . '/app/'];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require REPO . '/app/Core/helpers.php';
require REPO . '/app/Controllers/Admin/helpers.php';
require ROOT . '/app/helpers.php';
