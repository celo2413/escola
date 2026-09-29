<?php
declare(strict_types=1);
function currentUser(): ?array { if(empty($_SESSION['usuario_id']))return null;$u=one('SELECT * FROM usuarios WHERE id=?',[$_SESSION['usuario_id']]);if(!$u||!in_array($u['status'],['ativo','pendente'],true))return null;if(time()-($_SESSION['last_seen']??0)>28800)return null;$_SESSION['last_seen']=time();return $u; }
function requireLogin(bool $pending=false): array { $u=currentUser();if(!$u)fail('Entre para acessar o portal.',401);if(!$pending&&!emailAccessAllowed($u))fail('Confirme seu e-mail antes de acessar o portal.',403);if(!$pending&&$u['status']!=='ativo')fail('Sua matrícula ainda não foi aprovada.',403);return $u; }
function requireRole(array $roles): array { $u=requireLogin();if(!in_array($u['cargo'],$roles,true))fail('Acesso não autorizado.',403);return $u; }
function authenticate(array $b): array {
    required($b,['email','password']);$email=validEmail($b['email'],false);$ip=$_SERVER['REMOTE_ADDR']??'CLI';
    $n=one('SELECT COUNT(*) n FROM tentativas_login WHERE ip=? AND criado_em > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',[$ip]);
    if((int)$n['n']>=30)fail('Muitas tentativas. Aguarde 15 minutos.',429);
    $u=one('SELECT * FROM usuarios WHERE email=?',[$email]);
    if(!$u||!password_verify((string)$b['password'],$u['senha_hash'])||!in_array($u['status'],['ativo','pendente'],true)){insert('tentativas_login',['ip'=>$ip]);fail('E-mail ou senha inválidos.',401);}
    session_regenerate_id(true);$_SESSION=['usuario_id'=>$u['id'],'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    query('UPDATE usuarios SET ultimo_acesso=NOW() WHERE id=?',[$u['id']]);audit('LOGIN','usuarios',(string)$u['id']);return $u;
}
function endSession(): void { if(currentUser())audit('LOGOUT','usuarios',(string)$_SESSION['usuario_id']);$_SESSION=[];session_destroy();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>APP_BASE,'httponly'=>true,'samesite'=>'Lax']); }
