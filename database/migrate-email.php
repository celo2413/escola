<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
$pdo = db();
if (!$pdo->query("SELECT GET_LOCK('legado_email_migration', 10)")->fetchColumn()) throw new RuntimeException('Migração em andamento.');
try {
    $columns = array_column($pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(), 'Field');
    $definitions = [
        'email_verificado' => 'BOOLEAN NOT NULL DEFAULT 0',
        // Existing accounts retain access, without falsely claiming email ownership.
        'email_verificacao_exigida' => 'BOOLEAN NOT NULL DEFAULT 0',
        'email_verificado_em' => 'DATETIME NULL',
        'email_pendente' => 'VARCHAR(190) NULL',
        'token_verificacao' => 'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL',
        'token_expira_em' => 'DATETIME NULL',
        'verificacao_solicitada_em' => 'DATETIME NULL',
        'verificacao_enviada_em' => 'DATETIME NULL',
    ];
    foreach ($definitions as $name => $definition) if (!in_array($name, $columns, true)) $pdo->exec("ALTER TABLE usuarios ADD COLUMN $name $definition");
    $pdo->exec('ALTER TABLE usuarios ALTER COLUMN email_verificacao_exigida SET DEFAULT 1');
    $indexes = array_column($pdo->query('SHOW INDEX FROM usuarios')->fetchAll(), 'Key_name');
    if (!in_array('uq_email_token', $indexes, true)) $pdo->exec('ALTER TABLE usuarios ADD UNIQUE INDEX uq_email_token (token_verificacao)');
    if (!in_array('uq_email_pendente', $indexes, true)) $pdo->exec('ALTER TABLE usuarios ADD UNIQUE INDEX uq_email_pendente (email_pendente)');
    $pdo->exec("ALTER TABLE solicitacoes_matricula MODIFY status ENUM('email_nao_verificado','pendente','em_analise','aprovada','reprovada','cancelada') NOT NULL DEFAULT 'pendente'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS limites_email (
        chave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        inicio DATETIME NOT NULL, tentativas INT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_limites_inicio (inicio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Migração de e-mail concluída. Contas e dados existentes preservados.\n";
} finally { $pdo->query("SELECT RELEASE_LOCK('legado_email_migration')"); }
