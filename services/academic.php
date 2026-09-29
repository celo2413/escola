<?php
declare(strict_types=1);
function saveGrades(array $u,array $b): array {
    $evaluation=one('SELECT * FROM avaliacoes WHERE id=?',[validId($b['evaluationId']??'')]);if(!$evaluation)fail('Avaliação inexistente.',404);
    teachingLink($u,(string)$evaluation['turma_id'],(string)$evaluation['disciplina_id'],(string)$evaluation['professor_id']);
    if($u['cargo']==='professor'&&(string)$evaluation['professor_id']!==teacherId($u))fail('Avaliação de outro professor.',403);
    $records=$b['records']??[];if(!is_array($records)||!count($records)||count($records)>500)fail('Informe as notas da avaliação.');
    return transaction(function()use($u,$evaluation,$records){
        foreach($records as $r){$sid=validId($r['studentId']??'');assertStudentClass($sid,(string)$evaluation['turma_id']);$grade=numberValue($r['value']??'',0,(float)$evaluation['valor_maximo']);$previous=one('SELECT nota FROM notas WHERE avaliacao_id=? AND aluno_id=? FOR UPDATE',[$evaluation['id'],$sid]);query('INSERT INTO notas (avaliacao_id,aluno_id,nota,observacao) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nota=VALUES(nota),observacao=VALUES(observacao)',[$evaluation['id'],$sid,$grade,textValue($r['note']??'')]);audit('NOTA_ALTERADA','notas',$sid,json_encode(['avaliacao'=>$evaluation['id'],'anterior'=>$previous['nota']??null,'nova'=>$grade]));notify(studentUsers($sid),'Nota atualizada: '.$evaluation['titulo'],'mygrades');}
        return ['ok'=>true];
    });
}
function saveAttendance(array $u,array $b): array {
    $records=$b['records']??[];if(!is_array($records)||!count($records)||count($records)>500)fail('Selecione uma turma com alunos.');$first=$records[0];$class=validId($first['classId']??'');$subject=validId($first['subjectId']??'');$teacher=teachingLink($u,$class,$subject);$date=validDate($first['date']??'');$lesson=integerValue($first['lesson']??0,1,20);if($date>date('Y-m-d'))fail('Não é permitido registrar presença futura.');
    return transaction(function()use($records,$class,$subject,$teacher,$date,$lesson,$u){
        $c=one('SELECT * FROM chamadas WHERE turma_id=? AND disciplina_id=? AND data_aula=? AND numero_aula=? FOR UPDATE',[$class,$subject,$date,$lesson]);
        if($c&&$u['cargo']==='professor'&&(string)$c['professor_id']!==$teacher)fail('Chamada registrada por outro professor.',403);
        $cid=$c?(string)$c['id']:insert('chamadas',['turma_id'=>$class,'disciplina_id'=>$subject,'professor_id'=>$teacher,'data_aula'=>$date,'numero_aula'=>$lesson]);$seen=[];
        foreach($records as $r){if((string)($r['classId']??'')!==$class||(string)($r['subjectId']??'')!==$subject||($r['date']??'')!==$date||(int)($r['lesson']??0)!==$lesson)fail('Os registros devem pertencer à mesma chamada.');$sid=validId($r['studentId']??'');if(in_array($sid,$seen,true))fail('Aluno repetido na chamada.');$seen[]=$sid;assertStudentClass($sid,$class);$status=['P'=>'presente','F'=>'falta','J'=>'justificada'][choice($r['status']??'',['P','F','J'])];query('INSERT INTO presencas (chamada_id,aluno_id,status,observacao) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),observacao=VALUES(observacao)',[$cid,$sid,$status,textValue($r['note']??'',500)]);if($status!=='presente')notify(studentUsers($sid),'Ausência registrada em '.$date,'attendance');}
        $expected=array_map('strval',array_column(rows("SELECT a.id FROM alunos a JOIN aluno_turma at ON at.aluno_id=a.id WHERE at.turma_id=? AND at.status='ativo' AND a.status='ativo'",[$class]),'id'));sort($expected);sort($seen);if($expected!==$seen)fail('Registre a presença de todos os alunos ativos.');audit('CHAMADA_REGISTRADA','chamadas',$cid);return ['id'=>$cid,'ok'=>true];
    });
}
