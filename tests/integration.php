<?php
declare(strict_types=1);
// Executar somente contra a instância PHP configurada com DB_NAME=colegio_legado_test.
require_once __DIR__.'/../config/database.php';
if(!in_array(databaseConfig()['database'],['colegio_legado_test','escola_email_test','escola_repair_test'],true))throw new RuntimeException('Use uma base isolada de testes. Não execute na base da escola.');
define('TEST_BASE',getenv('TEST_BASE') ?: 'http://127.0.0.1:8089');
class Client {
    private CurlHandle $curl;public string $csrf='';
    function __construct(){ $this->curl=curl_init();curl_setopt_array($this->curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20]);$this->request('/public'); }
    function request(string $route,string $method='GET',?array $body=null,bool $csrf=true): array {
        curl_setopt_array($this->curl,[CURLOPT_URL=>TEST_BASE.'/api/index.php?route='.rawurlencode($route),CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_POSTFIELDS=>$body===null?null:json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json',...($csrf?['X-CSRF-Token: '.$this->csrf]:[])]]);
        $raw=curl_exec($this->curl);if($raw===false)throw new RuntimeException(curl_error($this->curl));$data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Resposta não JSON: '.$raw);if(isset($data['csrf']))$this->csrf=$data['csrf'];return ['status'=>curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE),'data'=>$data];
    }
    function login(string $role,string $password='123456'): array {return $this->request('/auth/login','POST',['email'=>str_contains($role,'@')?$role:$role.'@gmail.com','password'=>$password]);}
    function page(string $path): int {curl_setopt_array($this->curl,[CURLOPT_URL=>TEST_BASE.$path,CURLOPT_CUSTOMREQUEST=>'GET',CURLOPT_POSTFIELDS=>null,CURLOPT_HTTPHEADER=>[]]);curl_exec($this->curl);return curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE);}
}
$checks=0;
function check(bool $condition,string $label): void {global $checks;if(!$condition)throw new RuntimeException('FALHOU: '.$label);$checks++;echo "OK $checks — $label\n";}
function ok(array $r,string $label,int $status=200): array {check($r['status']===$status,$label.' ['.$r['status'].'] '.($r['data']['message']??''));return $r['data'];}
function create(Client $c,string $type,array $body): string {return ok($c->request('/records/'.$type,'POST',$body),'Cadastrar '.$type,201)['id'];}
function sql(string $query): array {return db()->query($query)->fetchAll();}
check((int)sql('SELECT COUNT(*) n FROM usuarios')[0]['n']===3,'Somente três usuários iniciais');
foreach(['alunos','responsaveis','solicitacoes_matricula','notas','presencas','chamadas','atividades','ocorrencias','comunicados','notificacoes','pontos_professores','turmas'] as $table)check((int)sql("SELECT COUNT(*) n FROM $table")[0]['n']===0,'Base vazia: '.$table);
$d=new Client();ok($d->login('diretor'),'Login diretor');$teacher=new Client();ok($teacher->login('professor'),'Login professor');$coord=new Client();ok($coord->login('coordenador'),'Login coordenação');
$initial=ok($d->request('/bootstrap'),'Dashboard inicial');check($initial['metrics']['students']===0&&$initial['metrics']['teachers']===1&&$initial['metrics']['classes']===0,'Cards iniciais obtidos por SELECT COUNT');
check($teacher->page('/diretor/dashboard.php')===403,'Professor bloqueado na página PHP da direção');check($d->page('/diretor/dashboard.php')===200,'Diretor acessa página protegida');
ok($teacher->request('/records/users','POST',[]),'Professor não cria usuários',403);ok($coord->request('/records/users','POST',[]),'Coordenação não cria usuários críticos',403);
ok($d->request('/records/subjects','POST',['name'=>'Sem CSRF'],false),'CSRF obrigatório',403);
$year=(int)date('Y');$today=date('Y-m-d');
$y=create($d,'years',['year'=>$year,'start'=>"$year-01-01",'end'=>"$year-12-31",'status'=>'ativo']);
$term=create($d,'terms',['yearId'=>$y,'number'=>1,'start'=>"$year-01-01",'end'=>"$year-12-31",'status'=>'ativo']);
$subject=create($d,'subjects',['name'=>'Disciplina de integração','workload'=>80]);
$class=create($coord,'classes',['name'=>'Turma de integração','series'=>'2º Ensino Médio','course'=>'Ensino Médio','period'=>'Manhã','year'=>$year,'room'=>'1','capacity'=>30,'teacherId'=>'1','subjectIds'=>[$subject],'status'=>'Ativa']);
create($d,'links',['teacherId'=>'1','classId'=>$class,'subjectId'=>$subject]);
create($coord,'schedules',['teacherId'=>'1','classId'=>$class,'subjectId'=>$subject,'weekday'=>1,'start'=>'08:00','end'=>'09:00','room'=>'1']);
$anotherClass=create($d,'classes',['name'=>'Turma restrita','series'=>'3º Ensino Médio','course'=>'Ensino Médio','period'=>'Tarde','year'=>$year,'room'=>'2','subjectIds'=>[$subject],'status'=>'Ativa']);
check($teacher->page('/professor/turma.php?id='.$anotherClass)===403,'ID de turma de outro professor bloqueado');
$visitor=new Client();$enrollment=['name'=>'Estudante de integração','birth'=>'2010-05-12','cpf'=>'529.982.247-25','rg'=>'1234567','phone'=>'11999998877','email'=>'estudante.integracao@gmail.com','cep'=>'01310100','street'=>'Endereço de integração','number'=>'100','district'=>'Centro','city'=>'São Paulo','state'=>'SP','guardianName'=>'Responsável de integração','guardianCpf'=>'390.533.447-05','kinship'=>'Mãe','guardianPhone'=>'11988887766','guardianEmail'=>'responsavel.integracao@gmail.com','requestedGrade'=>'2º Ensino Médio','requestedPeriod'=>'Manhã','year'=>$year,'loginEmail'=>'estudante.integracao@gmail.com','password'=>'Integracao123!','confirm'=>'Integracao123!'];
$invalid=$enrollment;$invalid['loginEmail']='teste@dominio-inexistente.invalid';ok($visitor->request('/enrollments','POST',$invalid),'Domínio sem e-mail rejeitado',422);
$e=ok($visitor->request('/enrollments','POST',$enrollment),'Solicitação pública',201)['id'];
check((int)sql('SELECT COUNT(*) n FROM alunos')[0]['n']===0,'Solicitação não cria aluno antes da aprovação');$student=new Client();ok($student->login($enrollment['loginEmail'],$enrollment['password']),'Login pendente');$pending=ok($student->request('/bootstrap'),'Snapshot pendente');check($pending['user']['status']==='Pendente'&&count($pending['students'])===0,'Pendente vê apenas solicitação');check($student->page('/aluno/dashboard.php')===403,'Pendente não acessa dashboard PHP');
check($pending['user']['emailVerificationRequired']===false,'Acesso sem confirmação por e-mail');
check(in_array($e,array_column(ok($d->request('/bootstrap'),'Fila da direção')['enrollments'],'id')),'Matrícula aparece imediatamente para a direção');
ok($student->request('/auth/resend-verification','POST',[]),'Confirmação desativada',410);
ok($coord->request('/enrollments/'.$e.'/review','POST',['status'=>'Aprovada']),'Coordenação não aprova matrícula',403);
ok($d->request('/enrollments/'.$e.'/review','POST',['status'=>'Aprovada','registration'=>'INTEGRACAO001','classId'=>$class,'entry'=>$today]),'Aprovação sem senha de novo responsável falha',422);
check((int)sql('SELECT COUNT(*) n FROM alunos')[0]['n']===0,'Rollback remove aluno criado durante falha');
$approval=ok($d->request('/enrollments/'.$e.'/review','POST',['status'=>'Aprovada','registration'=>'INTEGRACAO001','classId'=>$class,'entry'=>$today,'guardianPassword'=>'Responsavel123!']),'Aprovação transacional');$sid=$approval['studentId'];
check((int)sql('SELECT COUNT(*) n FROM responsavel_aluno')[0]['n']===1,'Responsável e vínculo persistidos');check($student->page('/aluno/dashboard.php')===200,'Acesso liberado após aprovação');
ok($d->request('/enrollments/'.$e.'/review','POST',['status'=>'Aprovada']),'Impedir aprovação duplicada',409);
$ev=create($teacher,'evaluations',['title'=>'Avaliação de integração','classId'=>$class,'subjectId'=>$subject,'termId'=>$term,'type'=>'prova','maximum'=>20,'date'=>$today]);
ok($teacher->request('/academic/grades','POST',['evaluationId'=>$ev,'records'=>[['studentId'=>$sid,'value'=>21]]]),'Nota acima do máximo rejeitada',422);
ok($teacher->request('/academic/grades','POST',['evaluationId'=>$ev,'records'=>[['studentId'=>$sid,'value'=>16]]]),'Nota válida persistida');check((float)sql('SELECT nota FROM notas')[0]['nota']===16.0,'Nota conferida diretamente no MySQL');
ok($teacher->request('/academic/attendance','POST',['records'=>[['studentId'=>$sid,'classId'=>$class,'subjectId'=>$subject,'date'=>$today,'lesson'=>'1','status'=>'F','note'=>'Registro de integração']]]),'Chamada persistida');
check(sql('SELECT status FROM presencas')[0]['status']==='falta','Falta conferida diretamente no MySQL');
$snap=ok($student->request('/bootstrap'),'Boletim do aluno');check(count($snap['students'])===1&&$snap['grades'][0]['values'][0]==8&&$snap['frequencies'][0]['absent']===1,'Aluno recebe nota normalizada e frequência SQL');check($student->page('/diretor/dashboard.php')===403,'Aluno bloqueado na direção');
ok($student->request('/academic/grades','POST',['evaluationId'=>$ev,'records'=>[['studentId'=>$sid,'value'=>20]]]),'Aluno não altera nota',403);
$parent=new Client();ok($parent->login('responsavel.integracao@gmail.com','Responsavel123!'),'Login responsável');ok($parent->request('/auth/password','POST',['current'=>'Responsavel123!','password'=>'ResponsavelNova123!','confirm'=>'ResponsavelNova123!']),'Alterar senha temporária');$p=ok($parent->request('/bootstrap'),'Dados do responsável');check(count($p['students'])===1&&$p['students'][0]['id']===$sid,'Responsável vê somente aluno vinculado');
create($teacher,'activities',['title'=>'Atividade de integração','classId'=>$class,'subjectId'=>$subject,'description'=>'Descrição persistida','created'=>$today,'due'=>$today,'value'=>10,'status'=>'Disponível','type'=>'Atividade']);
create($teacher,'contents',['title'=>'Conteúdo de integração','classId'=>$class,'subjectId'=>$subject,'description'=>'Conteúdo persistido','date'=>$today,'lesson'=>1,'objective'=>'Objetivo pedagógico','methodology'=>'Metodologia da aula']);
create($teacher,'occurrences',['studentId'=>$sid,'classId'=>$class,'category'=>'Atraso','description'=>'Descrição da ocorrência','status'=>'Registrada','date'=>$today]);
create($coord,'announcements',['title'=>'Comunicado de integração','description'=>'Mensagem persistida','audience'=>'Todos','date'=>$today]);
ok($teacher->request('/time/entry','POST',[]),'Entrada de ponto');ok($teacher->request('/time/entry','POST',[]),'Entrada duplicada impedida',409);ok($teacher->request('/time/exit','POST',[]),'Saída de ponto');check(sql('SELECT saida FROM pontos_professores')[0]['saida']!==null,'Ponto conferido no MySQL');
$adjust=ok($teacher->request('/time/adjustments','POST',['date'=>$today,'entry'=>'08:00','exit'=>'12:00','reason'=>'Correção de integração']),'Solicitar ajuste',201)['id'];ok($d->request('/time/adjustments/'.$adjust.'/review','POST',['status'=>'Aprovada','reason'=>'Conferido']),'Aprovar ajuste');check(sql('SELECT status FROM pontos_professores')[0]['status']==='Ajustado','Ajuste aplicado');
$fresh=new Client();ok($fresh->login($enrollment['loginEmail'],$enrollment['password']),'Nova sessão após fechar cliente');$again=ok($fresh->request('/bootstrap'),'Persistência em nova sessão');check(count($again['grades'])===1&&count($again['attendance'])===1,'Notas e chamada sobrevivem a nova sessão');
ok($d->request('/records/students','POST',['name'=>'Criação indevida']),'Impedir criação direta de aluno',422);
check(count(sql('SELECT id FROM logs_auditoria'))>10,'Auditoria persistida');
ok($teacher->request('/academic/grades','POST',['evaluationId'=>$ev,'records'=>[['studentId'=>$sid,'value'=>19],['studentId'=>'999999','value'=>10]]]),'Lote com aluno indevido rejeitado',403);
check((float)sql('SELECT nota FROM notas')[0]['nota']===16.0,'Rollback integral de lote de notas');
ok($teacher->request('/academic/attendance','POST',['records'=>[['studentId'=>$sid,'classId'=>$class,'subjectId'=>$subject,'date'=>$today,'lesson'=>'1','status'=>'P'],['studentId'=>'999999','classId'=>$class,'subjectId'=>$subject,'date'=>$today,'lesson'=>'1','status'=>'F']]]),'Lote de chamada indevido rejeitado',403);
check(sql('SELECT status FROM presencas')[0]['status']==='falta','Rollback integral da chamada');
$e2=$enrollment;$e2['name']='Segundo estudante de integração';$e2['cpf']='11144477735';$e2['loginEmail']='segundo.integracao@gmail.com';$e2['email']=$e2['loginEmail'];$e2['guardianEmail']='segundo.responsavel@gmail.com';$e2['guardianCpf']='12345678909';$e2['requestedGrade']='3º Ensino Médio';$e2['requestedPeriod']='Tarde';
$second=ok($visitor->request('/enrollments','POST',$e2),'Outra solicitação para isolamento',201)['id'];
$secondStudent=ok($d->request('/enrollments/'.$second.'/review','POST',['status'=>'Aprovada','registration'=>'INTEGRACAO002','classId'=>$anotherClass,'entry'=>$today,'guardianPassword'=>'OutroResponsavel123!']),'Segunda aprovação')['studentId'];
$tdata=ok($teacher->request('/bootstrap'),'Escopo docente');check(count($tdata['students'])===1&&$tdata['students'][0]['id']===$sid,'Professor não recebe aluno de outra turma');
check($parent->page('/responsavel/aluno.php?id='.$secondStudent)===403,'Responsável não acessa filho de outra família por URL');
$sdata=ok($student->request('/bootstrap'),'Escopo individual');check(count($sdata['students'])===1,'Aluno não recebe cadastro de outro aluno');
ok($teacher->request('/records/evaluations','POST',['title'=>'Acesso indevido','classId'=>$anotherClass,'subjectId'=>$subject,'termId'=>$term,'type'=>'prova','maximum'=>10,'date'=>$today]),'Professor não cria avaliação em turma alheia',403);
ok($d->request('/records/classes/'.$anotherClass,'DELETE',[]),'Inativação de turma');check((int)sql('SELECT COUNT(*) n FROM aluno_turma')[0]['n']===2,'Inativar turma preserva vínculos históricos');
ok($coord->request('/academic/grades','POST',['evaluationId'=>$ev,'records'=>[['studentId'=>$sid,'value'=>20]]]),'Coordenação não lança notas',403);
ok($student->request('/auth/logout','POST',[]),'Logout');ok($student->request('/bootstrap'),'Sessão encerrada deixa de acessar dados',401);
echo "\n$checks verificações concluídas. Somente a base isolada de testes recebeu registros.\n";
