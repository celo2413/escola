<?php
declare(strict_types=1);
// Access depends on account status and school approval, without email confirmation.
function emailAccessAllowed(array $user): bool { return true; }
function emailRateLimit(string $scope, int $maximum, int $seconds = 3600): void {
    $key = hash('sha256', $scope);
    query('INSERT IGNORE INTO limites_email (chave,inicio,tentativas) VALUES (?,NOW(),0)', [$key]);
    $row = one('SELECT *,UNIX_TIMESTAMP(inicio) started FROM limites_email WHERE chave=? FOR UPDATE', [$key]);
    $count = time() - (int)$row['started'] >= $seconds ? 0 : (int)$row['tentativas'];
    if ($count >= $maximum) fail('Muitas tentativas. Aguarde um pouco antes de tentar novamente.', 429);
    query('UPDATE limites_email SET inicio=IF(?=0,NOW(),inicio),tentativas=? WHERE chave=?', [$count, $count+1, $key]);
    query('DELETE FROM limites_email WHERE inicio < DATE_SUB(NOW(), INTERVAL 2 DAY) LIMIT 100');
}


function requestEmailChange(array $user, string $email): void {
    if ($email === $user['email']) return;
    if (one('SELECT id FROM usuarios WHERE id<>? AND email=?', [$user['id'],$email]) ||
        one('SELECT id FROM solicitacoes_matricula WHERE usuario_id<>? AND email_acesso=?', [$user['id'],$email])) fail('Este e-mail já está cadastrado.',422,['email'=>'Utilize outro endereço.']);
    updateRow('usuarios',(string)$user['id'],['email'=>$email,'email_pendente'=>null,'email_verificado'=>0,'email_verificado_em'=>null,'token_verificacao'=>null,'token_expira_em'=>null]);
    query('UPDATE alunos SET email=? WHERE usuario_id=?',[$email,$user['id']]);
    query('UPDATE responsaveis SET email=? WHERE usuario_id=?',[$email,$user['id']]);
    query('UPDATE solicitacoes_matricula SET email_acesso=? WHERE usuario_id=?',[$email,$user['id']]);
}
