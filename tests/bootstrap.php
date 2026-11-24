<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$setEnvironment = static function (string $key, string $value): void {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

$isCi = filter_var(getenv('CI') ?: false, FILTER_VALIDATE_BOOLEAN);

if (! $isCi) {
    // Docker Compose injects the development MySQL connection into the app
    // container. PHPUnit must never inherit that connection for local tests.
    $setEnvironment('DB_CONNECTION', 'sqlite');
    $setEnvironment('DB_DATABASE', ':memory:');
    $setEnvironment('DB_URL', '');
}

$connection = getenv('DB_CONNECTION') ?: '';
$database = getenv('DB_DATABASE') ?: '';

$isSafeTestDatabase = $isCi
    ? $connection === 'mysql' && $database === 'kisan_khata_test'
    : $connection === 'sqlite' && $database === ':memory:';

if (! $isSafeTestDatabase) {
    throw new RuntimeException(sprintf(
        'Unsafe PHPUnit database configuration: DB_CONNECTION=%s, DB_DATABASE=%s. '
        .'Local tests require SQLite :memory:; CI tests require the dedicated kisan_khata_test database.',
        $connection,
        $database,
    ));
}

// Account deactivation explicitly requires persisted database sessions so
// session revocation remains atomic with the user state change.
$setEnvironment('SESSION_DRIVER', 'database');
