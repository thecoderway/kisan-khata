<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root.'/.env';
$examplePath = $root.'/.env.example';

if (! file_exists($envPath)) {
    if (! copy($examplePath, $envPath)) {
        fwrite(STDERR, "ERROR: Could not create .env\n");
        exit(1);
    }

    echo ".env created from .env.example.\n";
}

$content = file_get_contents($envPath);

if ($content === false) {
    fwrite(STDERR, "ERROR: Could not read .env\n");
    exit(1);
}

$hasKey = preg_match('/^APP_KEY=(.*)$/m', $content, $matches) === 1
    && trim($matches[1]) !== '';

if ($hasKey) {
    echo "Existing APP_KEY preserved.\n";
    exit(0);
}

echo "APP_KEY is missing. Generating a new key...\n";

chdir($root);
passthru(escapeshellarg(PHP_BINARY).' artisan key:generate', $exitCode);

exit($exitCode);
