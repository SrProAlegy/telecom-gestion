<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Telcom\\Controladores\\' => __DIR__ . '/controladores/',
        'Telcom\\Modelos\\' => __DIR__ . '/modelos/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
