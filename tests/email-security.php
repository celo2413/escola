<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once APP_ROOT.'/services/snapshot.php';
if(databaseConfig()['database']!=='escola_email_test')throw new RuntimeException('Use somente escola_email_test.');
putenv('SMTP_HOST=');
$checks=0;
function verify(bool $condition,string $label): void {global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function rejects(callable $fn,int $status): void {try{$fn();throw new RuntimeException('Operação deveria falhar');}catch(HttpError $error){verify($error->status===$status,'Status esperado');}}
$legacy=one("SELECT * FROM usuarios WHERE email='diretor@gmail.com'");
verify(emailAccessAllowed($legacy)&&!$legacy['email_verificado'],'Conta anterior preservada sem falsa verificação');
$email='legado.test.'.bin2hex(random_bytes(5)).'@gmail.com';
[$uid,$token]=transaction(function()use($email){
    $uid=insert('usuarios',['nome'=>'Teste de confirmação','email'=>$email,'senha_hash'=>password_hash('TesteSenha123!',PASSWORD_DEFAULT),'cargo'=>'coordenador']);
    insert('coordenadores',['usuario_id'=>$uid]);scheduleEmailVerification($uid);
    return [$uid,$GLOBALS['verificationMessages'][0]['token']];
});
$user=one('SELECT * FROM usuarios WHERE id=?',[$uid]);
verify(strlen($token)===64&&ctype_xdigit($token),'Token aleatório');
verify($user['token_verificacao']===hash('sha256',$token)&&$user['token_verificacao']!==$token,'Somente hash no banco');
verify(strtotime($user['token_expira_em'])>time()&&strtotime($user['token_expira_em'])<=time()+86400,'Prazo 24 horas');
verify(!$user['email_verificado']&&!emailAccessAllowed($user),'Novo cadastro bloqueado');
verify(($GLOBALS['emailDelivery']??'')==='unavailable'&&!$user['verificacao_enviada_em'],'Falha de envio não finge entrega');
$_SESSION['usuario_id']=$uid;$_SESSION['last_seen']=time();
rejects(fn()=>requireRole(['coordenador']),403);
verify(snapshot($user)['students']===[]&&snapshot($user)['classes']===[],'Snapshot sem dados acadêmicos');
rejects(fn()=>transaction(fn()=>scheduleEmailVerification($uid,true)),429);
query('UPDATE usuarios SET token_expira_em=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?',[$uid]);
rejects(fn()=>confirmEmailToken($token),422);
query('UPDATE usuarios SET verificacao_solicitada_em=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE id=?',[$uid]);
$newToken=transaction(function()use($uid){scheduleEmailVerification($uid,true);return $GLOBALS['verificationMessages'][0]['token'];});
verify($newToken!==$token,'Reenvio rotaciona token');
rejects(fn()=>confirmEmailToken($token),422);
confirmEmailToken($newToken);
$user=one('SELECT * FROM usuarios WHERE id=?',[$uid]);
verify($user['email_verificado']&&$user['email_verificado_em']&&!$user['token_verificacao']&&!$user['token_expira_em'],'Consumo atômico');
rejects(fn()=>confirmEmailToken($newToken),422);
rejects(fn()=>transaction(fn()=>requestEmailChange($user,'diretor@gmail.com')),422);
query('UPDATE usuarios SET verificacao_solicitada_em=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE id=?',[$uid]);
$newEmail='novo.'.$email;
$changeToken=transaction(function()use($user,$newEmail){requestEmailChange($user,$newEmail);return $GLOBALS['verificationMessages'][0]['token'];});
$pending=one('SELECT * FROM usuarios WHERE id=?',[$uid]);
verify($pending['email']===$email&&$pending['email_pendente']===$newEmail&&emailAccessAllowed($pending),'E-mail anterior e acesso preservados até confirmação');
confirmEmailToken($changeToken);
$confirmed=one('SELECT * FROM usuarios WHERE id=?',[$uid]);
verify($confirmed['email']===$newEmail&&!$confirmed['email_pendente'],'Novo e-mail somente após prova de posse');
query('UPDATE usuarios SET verificacao_solicitada_em=NULL,email_verificado=0 WHERE id=?',[$uid]);
try{transaction(function()use($uid){scheduleEmailVerification($uid);throw new RuntimeException('Rollback de teste');});}catch(RuntimeException){}
verify(($GLOBALS['verificationMessages']??[])===[]&&!one('SELECT token_verificacao FROM usuarios WHERE id=?',[$uid])['token_verificacao'],'Rollback não envia e não deixa token');
transaction(fn()=>emailRateLimit('test-limiter:'.$uid,1));rejects(fn()=>transaction(fn()=>emailRateLimit('test-limiter:'.$uid,1)),429);
// Build the real PHPMailer MIME payload, without opening a socket or sending mail.
putenv('SMTP_HOST=localhost');putenv('SMTP_USER=');putenv('MAIL_FROM=secretaria@example.com');putenv('APP_URL=https://colegio.example.com/escola');
$mail=verificationMailer(['email'=>$newEmail,'name'=>'Teste <aluno>','token'=>str_repeat('a',64)]);
verify($mail->preSend(),'MIME real gerado');
verify(str_contains($mail->Body,'Teste &lt;aluno&gt;')&&str_contains($mail->Body,'#token=')&&str_contains($mail->AltBody,'24 horas'),'Conteúdo seguro, link e validade');
verify($mail->SMTPSecure==='tls'&&$mail->SMTPDebug===0,'TLS e ausência de transcript público');
putenv('APP_URL=http://site-externo.example.com');try{verificationMailer(['email'=>$newEmail,'name'=>'Teste','token'=>$token]);throw new LogicException('URL insegura aceita');}catch(RuntimeException $error){verify($error->getCode()===1001,'HTTPS obrigatório fora de localhost');}
echo "$checks verificações de segurança e conteúdo de e-mail aprovadas. Nenhum e-mail foi enviado.\n";
