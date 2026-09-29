<?php
declare(strict_types=1);
class HttpError extends RuntimeException {
    public function __construct(string $message, public int $status=422, public array $fields=[]) { parent::__construct($message); }
}
function fail(string $message,int $status=422,array $fields=[]): never { throw new HttpError($message,$status,$fields); }
function query(string $sql,array $args=[]): PDOStatement {
    $stmt=db()->prepare($sql);
    foreach(array_values($args) as $i=>$value) $stmt->bindValue($i+1,$value,is_int($value)?PDO::PARAM_INT:($value===null?PDO::PARAM_NULL:PDO::PARAM_STR));
    $stmt->execute();return $stmt;
}
function rows(string $sql,array $args=[]): array { return query($sql,$args)->fetchAll(); }
function one(string $sql,array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function transaction(callable $fn): mixed {
    db()->beginTransaction();
    try { $result=$fn(); db()->commit(); }
    catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
    return $result;
}
function insert(string $table,array $values): string {
    $columns=array_keys($values);query('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));return db()->lastInsertId();
}
function updateRow(string $table,string $id,array $values): void { query('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($values))).' WHERE id=?',[...array_values($values),$id]); }
function e(mixed $v): string { return htmlspecialchars((string)($v??''), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function iso(?string $v): ?string { return $v ? str_replace(' ','T',$v).(strlen($v)>10?'-03:00':'') : null; }
function jsonResponse(mixed $data,int $status=200): never { if(is_array($data)&&isset($GLOBALS['emailDelivery']))$data['emailDelivery']=$GLOBALS['emailDelivery'];http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit; }
function audit(string $action,string $entity='',?string $id=null,string $description=''): void { insert('logs_auditoria',['usuario_id'=>$_SESSION['usuario_id']??null,'acao'=>$action,'entidade'=>$entity,'entidade_id'=>$id,'descricao'=>$description,'ip'=>$_SERVER['REMOTE_ADDR']??'CLI']); }
function notify(array $ids,string $title,string $route,string $message=''): void {
    foreach(array_unique($ids) as $id)query("INSERT INTO notificacoes (usuario_id,titulo,mensagem,tipo,link) SELECT id,?,?, 'informacao',? FROM usuarios WHERE id=? AND notificacoes=1",[$title,$message,$route,$id]);
}
function managers(): array { return array_column(rows("SELECT id FROM usuarios WHERE cargo='diretor' AND status='ativo'"),'id'); }
function studentUsers(string $student): array { return array_column(rows('SELECT usuario_id id FROM alunos WHERE id=? UNION SELECT r.usuario_id id FROM responsavel_aluno ra JOIN responsaveis r ON r.id=ra.responsavel_id WHERE ra.aluno_id=?',[$student,$student]),'id'); }
function settings(): array { $s=one('SELECT * FROM configuracoes WHERE id=1');return ['name'=>'Colégio Legado','year'=>(int)$s['ano_atual'],'term'=>(int)$s['bimestre_atual'],'minimum'=>(float)$s['media_minima'],'email'=>$s['email'],'address'=>$s['endereco'],'period'=>$s['periodo'],'logo'=>'']; }
