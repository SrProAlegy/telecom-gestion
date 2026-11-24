<?php

declare(strict_types=1);

$autoload = is_file('/opt/telcom/vendor/autoload.php')
    ? '/opt/telcom/vendor/autoload.php' : __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Telcom\\Soporte\\' => __DIR__ . '/soporte/',
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
