<?php
declare(strict_types=1);
define('API_REQUEST',true);
require_once __DIR__.'/../includes/bootstrap.php';
foreach(['snapshot','records','enrollments','academic','time'] as $file)require_once APP_ROOT.'/services/'.$file.'.php';
$route=$_GET['route']??'/public';$method=$_SERVER['REQUEST_METHOD'];$body=[];
if(!in_array($method,['GET','HEAD'])){
    validateCsrf();
    if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))fail('Envie JSON.',415);
    $raw=file_get_contents('php://input');if(strlen($raw)>2000000)fail('Requisição muito grande.',413);
    try{$body=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){fail('JSON inválido.');}
    if(!is_array($body))fail('Dados inválidos.');
}
if($method==='GET'&&$route==='/public')jsonResponse(['settings'=>settings(),'csrf'=>csrfToken()]);
if($method==='POST'&&in_array($route,['/auth/confirm-email','/auth/resend-verification'],true))fail('A confirmação por e-mail foi desativada. Entre no portal para acompanhar sua matrícula.',410);
if($method==='POST'&&$route==='/auth/login'){$u=authenticate($body);jsonResponse(['user'=>userView($u),'csrf'=>csrfToken(),'redirect'=>APP_BASE.(($u['status']==='pendente'||!emailAccessAllowed($u))?'status-matricula.php':$u['cargo'].'/dashboard.php')]);}
if($method==='POST'&&$route==='/auth/forgot'){
    $email=validEmail($body['email']??'',false);transaction(fn()=>emailRateLimit('forgot-ip:'.($_SERVER['REMOTE_ADDR']??'CLI'),10));$email=validEmail($email);transaction(function()use($email){if(!one("SELECT id FROM recuperacoes_acesso WHERE email=? AND status='pendente'",[$email])){insert('recuperacoes_acesso',['email'=>$email]);notify(managers(),'Solicitação de recuperação de acesso','users',$email);audit('RECUPERACAO_SOLICITADA','recuperacoes_acesso',null,$email);}});jsonResponse(['message'=>'A secretaria receberá a solicitação e verificará sua identidade antes de redefinir o acesso.']);
}
if($method==='POST'&&$route==='/enrollments')jsonResponse(submitEnrollment($body),201);
$u=requireLogin(true);

if($method==='GET'&&$route==='/auth/session')jsonResponse(['user'=>userView($u),'csrf'=>csrfToken()]);
if($method==='POST'&&$route==='/auth/logout'){endSession();jsonResponse(['ok'=>true]);}
if($method==='GET'&&$route==='/bootstrap')jsonResponse(snapshot($u));
if($method==='POST'&&$route==='/auth/password'){
    required($body,['current','password','confirm']);if(!password_verify((string)$body['current'],$u['senha_hash']))fail('Senha atual incorreta.');$pass=validPassword($body['password']);if($pass!==$body['confirm'])fail('Confirmação diferente da nova senha.');if(password_verify($pass,$u['senha_hash']))fail('Escolha uma senha diferente da atual.');
    transaction(function()use($pass,$u){updateRow('usuarios',(string)$u['id'],['senha_hash'=>password_hash($pass,PASSWORD_DEFAULT),'primeiro_acesso'=>0]);audit('SENHA_ALTERADA','usuarios',(string)$u['id']);});session_regenerate_id(true);jsonResponse(['ok'=>true]);
}
if(!emailAccessAllowed($u))fail('Confirme seu e-mail antes de acessar o portal.',403);
if($u['status']!=='ativo'||$u['primeiro_acesso'])fail('Conclua a liberação do seu acesso antes de usar o portal.',403);
if(preg_match('#^/records/([a-zA-Z]+)(?:/([1-9][0-9]*))?$#',$route,$m)){
    $type=$m[1];$id=$m[2]??null;
    if($method==='GET'){if(!isset(RECORD_TABLES[$type]))fail('Recurso não encontrado.',404);$data=snapshot($u);jsonResponse($data[$type]??[]);}
    if($method==='POST'&&!$id||$method==='PUT'&&$id)jsonResponse(saveRecord($u,$type,$body,$id),$id?200:201);
    if($method==='DELETE'&&$id)jsonResponse(removeRecord($u,$type,$id));
}
if($method==='POST'&&preg_match('#^/enrollments/([1-9][0-9]*)/review$#',$route,$m))jsonResponse(reviewEnrollment($u,$m[1],$body));
if($method==='POST'&&$route==='/academic/grades')jsonResponse(saveGrades($u,$body));
if($method==='POST'&&$route==='/academic/attendance')jsonResponse(saveAttendance($u,$body));
if($method==='POST'&&preg_match('#^/time/(entry|exit)$#',$route,$m))jsonResponse(registerTime($u,$m[1]));
if($method==='POST'&&$route==='/time/adjustments')jsonResponse(requestAdjustment($u,$body),201);
if($method==='POST'&&preg_match('#^/time/adjustments/([1-9][0-9]*)/review$#',$route,$m))jsonResponse(reviewAdjustment($u,$m[1],$body));
if($method==='POST'&&$route==='/notifications/read'){query('UPDATE notificacoes SET lida=1 WHERE usuario_id=?',[$u['id']]);jsonResponse(['ok'=>true]);}
if($method==='PUT'&&$route==='/profile'){
    required($body,['name','email']);$email=validEmail($body['email']);
    transaction(function()use($u,$body,$email){
        $locked=one('SELECT * FROM usuarios WHERE id=? FOR UPDATE',[$u['id']]);
        if($email!==$locked['email']&&!password_verify((string)($body['emailPassword']??''),$locked['senha_hash']))fail('Informe sua senha atual para alterar o e-mail.',422,['emailPassword'=>'Confirme sua senha atual.']);
        requestEmailChange($locked,$email);
        $v=['nome'=>textValue($body['name'],200),'telefone'=>phone($body['phone']??'',true),'foto'=>imageValue($body['photo']??''),'notificacoes'=>($body['notifications']??true)?1:0];updateRow('usuarios',(string)$u['id'],$v);
        if($u['cargo']==='aluno')query('UPDATE alunos SET nome=?,telefone=? WHERE usuario_id=?',[$v['nome'],$v['telefone'],$u['id']]);
        if($u['cargo']==='responsavel')query('UPDATE responsaveis SET nome=?,telefone=? WHERE usuario_id=?',[$v['nome'],$v['telefone'],$u['id']]);
        audit('PERFIL_ATUALIZADO','usuarios',(string)$u['id']);
    });jsonResponse(['ok'=>true,'message'=>'Perfil atualizado.']);
}
if($method==='PUT'&&$route==='/settings'){
    requireRole(['diretor']);if(($body['name']??'')!=='Colégio Legado')fail('Preserve o nome institucional.');$year=(int)numberValue($body['year']??0,2000,2100);if(!one('SELECT id FROM anos_letivos WHERE ano=?',[$year]))fail('Cadastre o ano letivo antes de selecioná-lo.');transaction(function()use($body,$year){updateRow('configuracoes','1',['email'=>validEmail($body['email']??''),'endereco'=>textValue($body['address']??'',500),'ano_atual'=>$year,'bimestre_atual'=>(int)numberValue($body['term']??0,1,4),'media_minima'=>numberValue($body['minimum']??0,0,10),'periodo'=>choice($body['period']??'', ['Manhã','Tarde','Noite'])]);audit('CONFIGURACOES_ATUALIZADAS','configuracoes','1');});jsonResponse(['ok'=>true]);
}
fail('Recurso não encontrado.',404);
