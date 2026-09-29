<?php
declare(strict_types=1);
// Configuração única. No XAMPP padrão, root não possui senha.
function databaseConfig(): array {
    return ['host'=>getenv('DB_HOST') ?: '127.0.0.1', 'port'=>getenv('DB_PORT') ?: '3306',
        'database'=>getenv('DB_NAME') ?: 'escola', 'user'=>getenv('DB_USER') ?: 'root',
        'password'=>getenv('DB_PASS') !== false ? getenv('DB_PASS') : '', 'charset'=>'utf8mb4'];
}
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c=databaseConfig();
        $pdo=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset={$c['charset']}", $c['user'], $c['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $pdo->exec("SET time_zone = '-03:00'");
    }
    return $pdo;
}
