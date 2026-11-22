<?php

declare(strict_types=1);

// Use Composer's autoloader when dependencies are installed, otherwise map the
// package namespace directly so the suite runs without `composer install`.
$composer = __DIR__ . '/../vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Shortn\\Sdk\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

require_once __DIR__ . '/Harness.php';
