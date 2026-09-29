<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
$pdo=db();
$pdo->exec('ALTER TABLE usuarios ALTER COLUMN email_verificacao_exigida SET DEFAULT 0');
$pdo->beginTransaction();
try {
    $pdo->exec('UPDATE usuarios SET email_verificacao_exigida=0,email_pendente=NULL,token_verificacao=NULL,token_expira_em=NULL');
    $count=$pdo->exec("UPDATE solicitacoes_matricula SET status='pendente' WHERE status='email_nao_verificado'");
    $pdo->commit();
    echo "Confirmação desativada. Solicitações liberadas para análise: $count. Dados acadêmicos preservados.\n";
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
