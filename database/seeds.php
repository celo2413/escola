<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../config/app.php';require_once __DIR__.'/../config/database.php';
$pdo=db();
if((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()!==0){fwrite(STDERR,"O banco já possui usuários; nenhuma alteração realizada.\n");exit(1);}
$pdo->beginTransaction();
try {
    $stmt=$pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash,cargo,email_verificacao_exigida) VALUES (?,?,?,?,0)');
    foreach(['diretor'=>'Diretor','coordenador'=>'Coordenador','professor'=>'Professor'] as $role=>$name){$stmt->execute([$name,$role.'@gmail.com',password_hash('123456',PASSWORD_DEFAULT),$role]);$id=$pdo->lastInsertId();$table=['diretor'=>'diretores','coordenador'=>'coordenadores','professor'=>'professores'][$role];$pdo->prepare("INSERT INTO $table (usuario_id) VALUES (?)")->execute([$id]);}
    $pdo->exec('INSERT IGNORE INTO configuracoes (id,ano_atual) VALUES (1,YEAR(CURRENT_DATE))');$pdo->commit();echo "Somente três contas iniciais criadas.\n";
}catch(Throwable $e){$pdo->rollBack();throw $e;}
