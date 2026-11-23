<?php

declare(strict_types=1);

// Comprueba Apache, enrutamiento, PDO y esquema a través de HTTP.
$context = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
$response = @file_get_contents('http://127.0.0.1/health', false, $context);
$data = is_string($response) ? json_decode($response, true) : null;
exit(($data['status'] ?? '') === 'ok' ? 0 : 1);
