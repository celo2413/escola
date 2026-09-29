<?php
declare(strict_types=1);
const RECORD_TABLES=['students'=>'alunos','teachers'=>'professores','classes'=>'turmas','subjects'=>'disciplinas','activities'=>'atividades','contents'=>'conteudos_aulas','occurrences'=>'ocorrencias','announcements'=>'comunicados','users'=>'usuarios','years'=>'anos_letivos','terms'=>'bimestres','evaluations'=>'avaliacoes','schedules'=>'horarios_aulas','links'=>'professor_turma'];
function recordPermission(array $u,string $type,?array $old=null): void {
    if(!isset(RECORD_TABLES[$type]))fail('Recurso inexistente.',404);
    if($u['cargo']==='diretor')return;
    if($u['cargo']==='coordenador'&&in_array($type,['classes','subjects','years','terms','links','schedules','announcements','occurrences']))return;
    if($u['cargo']==='professor'&&in_array($type,['activities','contents','occurrences','evaluations'])){
        if($old&&($type==='occurrences'?(string)$old['registrado_por']!==(string)$u['id']:(string)$old['professor_id']!==teacherId($u)))fail('Registro de outro professor.',403);return;
    }
    fail('Ação não autorizada.',403);
}
function saveRecord(array $u,string $type,array $b,?string $id=null): array {
    if(!isset(RECORD_TABLES[$type]))fail('Recurso inexistente.',404);$table=RECORD_TABLES[$type];$old=$id?one("SELECT * FROM $table WHERE id=?",[validId($id)]):null;if($id&&!$old)fail('Registro inexistente.',404);recordPermission($u,$type,$old);
    return transaction(function()use($u,$type,$b,$id,$old,$table){
        $v=[];$active=fn($s)=>['Ativo'=>'ativo','Inativo'=>'inativo','Ativa'=>'ativo','Inativa'=>'inativo','ativo'=>'ativo','inativo'=>'inativo'][choice($s,['Ativo','Inativo','Ativa','Inativa','ativo','inativo'])];
        if(in_array($type,['students','teachers','classes','subjects','users']))required($b,['name']);
        if($type==='students'){
            if(!$id)fail('Alunos são criados somente pela aprovação da matrícula.');required($b,['registration','classId','birth','entry','guardianName','guardianEmail','guardianPhone']);
            $v=['nome'=>textValue($b['name'],200),'matricula'=>textValue($b['registration'],40),'data_nascimento'=>validDate($b['birth']),'cpf'=>cpf($b['cpf']??'',true),'email'=>validEmail($b['email']??''),'telefone'=>phone($b['phone']??'',true),'endereco_formatado'=>textValue($b['address']??'',500),'foto'=>imageValue($b['photo']??''),'data_ingresso'=>validDate($b['entry']),'status'=>$active($b['status'])];
            if($v['data_nascimento']>date('Y-m-d'))fail('Nascimento não pode ser futuro.');$class=validId($b['classId']);if(!one("SELECT id FROM turmas WHERE id=? AND status='ativo'",[$class]))fail('Turma inválida.');
            $link=one("SELECT * FROM aluno_turma WHERE aluno_id=? AND status='ativo'",[$id]);if(!$link||(string)$link['turma_id']!==$class){query("UPDATE aluno_turma SET status='inativo',data_fim=CURRENT_DATE WHERE aluno_id=? AND status='ativo'",[$id]);query("INSERT INTO aluno_turma (aluno_id,turma_id,data_inicio,status) VALUES (?,?,?,'ativo') ON DUPLICATE KEY UPDATE status='ativo',data_fim=NULL",[$id,$class,$v['data_ingresso']]);}
            query('UPDATE usuarios SET nome=?,status=? WHERE id=?',[$v['nome'],$v['status'],$old['usuario_id']]);
            $guardian=one('SELECT responsavel_id FROM responsavel_aluno WHERE aluno_id=? AND responsavel_principal=1',[$id]);if($guardian){$gv=['nome'=>textValue($b['guardianName'],200),'email'=>validEmail($b['guardianEmail']),'telefone'=>phone($b['guardianPhone'])];updateRow('responsaveis',(string)$guardian['responsavel_id'],$gv);query('UPDATE responsavel_aluno SET parentesco=? WHERE aluno_id=? AND responsavel_id=?',[textValue($b['kinship']??'Outro',40),$id,$guardian['responsavel_id']]);}
        }
        if($type==='teachers'){
            $email=validEmail($b['email']??'');$v=['matricula'=>textValue($b['registration']??'',40)?:null,'cpf'=>cpf($b['cpf']??'',true),'telefone'=>phone($b['phone']??'',true),'data_nascimento'=>empty($b['birth'])?null:validDate($b['birth']),'carga_horaria'=>numberValue($b['workload']??0,0,80),'horario_previsto'=>empty($b['startTime'])?null:validTime($b['startTime']),'status'=>$active($b['status']??'Ativo')];
            if($old){$account=one('SELECT * FROM usuarios WHERE id=? FOR UPDATE',[$old['usuario_id']]);requestEmailChange($account,$email);updateRow('usuarios',(string)$old['usuario_id'],['nome'=>textValue($b['name'],200),'status'=>$v['status']]);}
            else {if(one('SELECT id FROM usuarios WHERE email=? OR email_pendente=?',[$email,$email]))fail('Este e-mail já está cadastrado.');$v['usuario_id']=insert('usuarios',['nome'=>textValue($b['name'],200),'email'=>$email,'senha_hash'=>password_hash(validPassword($b['password']??''),PASSWORD_DEFAULT),'cargo'=>'professor','primeiro_acesso'=>1,'email_verificacao_exigida'=>0]);}
        }
        if($type==='classes'){
            required($b,['series','course','period','year']);$year=integerValue($b['year'],2000,2100);if(!one("SELECT id FROM anos_letivos WHERE ano=? AND status='ativo'",[$year]))fail('Cadastre o ano letivo antes da turma.');
            $v=['nome'=>textValue($b['name'],150),'serie'=>textValue($b['series'],100),'curso'=>textValue($b['course'],100),'periodo'=>choice($b['period'],['Manhã','Tarde','Noite']),'ano_letivo'=>$year,'sala'=>textValue($b['room']??'',40),'capacidade'=>integerValue($b['capacity']??40,1,500),'professor_responsavel_id'=>empty($b['teacherId'])?null:validId($b['teacherId']),'status'=>$active($b['status']??'Ativa')];
            if($old&&(int)$old['ano_letivo']!==$year&&one('SELECT id FROM aluno_turma WHERE turma_id=? LIMIT 1',[$id]))fail('Turma com histórico não pode mudar de ano letivo.');
        }
        if($type==='subjects')$v=['nome'=>textValue($b['name'],120),'codigo'=>textValue($b['code']??'',40)?:null,'carga_horaria'=>numberValue($b['workload']??0,1,3000),'status'=>$active($b['status']??'Ativo')];
        if(in_array($type,['activities','contents','evaluations'])){
            required($b,['title','classId','subjectId']);$class=validId($b['classId']);$subject=validId($b['subjectId']);$teacher=teachingLink($u,$class,$subject,empty($b['teacherId'])?null:validId($b['teacherId']));
            $v=['titulo'=>textValue($b['title'],200),'turma_id'=>$class,'disciplina_id'=>$subject,'professor_id'=>$teacher];
            if($old&&($old['turma_id']!=$class||$old['disciplina_id']!=$subject||$old['professor_id']!=$teacher))fail('Crie outro registro para mudar os vínculos acadêmicos.');
            if($type==='activities'){required($b,['description','created','due']);$v+=['descricao'=>textValue($b['description']),'tipo'=>choice($b['type']??'Atividade',['Atividade','Trabalho','Projeto','Avaliação']),'valor'=>numberValue($b['value']??10,0,10000),'data_publicacao'=>validDate($b['created']),'data_entrega'=>validDate($b['due']),'status'=>choice($b['status'],['Disponível','Em andamento','Encerrada']),'material'=>textValue($b['attachment']??'',1000)];if($v['data_entrega']<$v['data_publicacao'])fail('Entrega anterior à publicação.');}
            if($type==='contents'){required($b,['description','objective','methodology','date']);$v+=['descricao'=>textValue($b['description']),'data_aula'=>validDate($b['date']),'numero_aula'=>integerValue($b['lesson']??1,1,20),'objetivo'=>textValue($b['objective']),'metodologia'=>textValue($b['methodology']),'material'=>textValue($b['material']??'',1000),'observacao'=>textValue($b['note']??'')];}
            if($type==='evaluations'){$term=one('SELECT b.*,a.ano FROM bimestres b JOIN anos_letivos a ON a.id=b.ano_letivo_id WHERE b.id=?',[validId($b['termId']??'')]);$c=one('SELECT ano_letivo FROM turmas WHERE id=?',[$class]);if(!$term||$term['ano']!=$c['ano_letivo'])fail('Bimestre incompatível com o ano da turma.');$v+=['bimestre_id'=>$term['id'],'tipo'=>choice($b['type'],['atividade','trabalho','prova','projeto','recuperacao']),'valor_maximo'=>numberValue($b['maximum'],0.01,10000),'data_avaliacao'=>validDate($b['date'])];if($v['data_avaliacao']<$term['data_inicio']||$v['data_avaliacao']>$term['data_fim'])fail('A avaliação deve estar dentro do bimestre.');if($old&&one('SELECT id FROM notas WHERE avaliacao_id=? LIMIT 1',[$id])&&($old['valor_maximo']!=$v['valor_maximo']||$old['bimestre_id']!=$v['bimestre_id']||$old['tipo']!==$v['tipo']))fail('Avaliação com notas: preserve valor máximo, tipo e bimestre.');}
        }
        if($type==='occurrences'){
            required($b,['description','category','classId','studentId','date']);$class=validId($b['classId']);$student=validId($b['studentId']);assertStudentClass($student,$class);if($u['cargo']==='professor'&&!in_array($class,array_map('strval',permittedClasses($u)),true))fail('Turma não autorizada.',403);
            $v=['aluno_id'=>$student,'turma_id'=>$class,'registrado_por'=>$old['registrado_por']??$u['id'],'professor_id'=>$u['cargo']==='professor'?teacherId($u):(empty($b['teacherId'])?null:validId($b['teacherId'])),'categoria'=>choice($b['category'],['Comportamento','Atraso','Falta','Uso inadequado de equipamento','Uso de celular','Desrespeito','Dano ao patrimônio','Outros']),'descricao'=>textValue($b['description']),'observacao'=>textValue($b['note']??''),'status'=>choice($b['status'],['Registrada','Em análise','Resolvida']),'data_ocorrencia'=>validDate($b['date'])];
        }
        if($type==='announcements'){
            required($b,['title','description','audience','date']);$aud=['Todos'=>'todos','Diretores'=>'diretores','Coordenadores'=>'coordenadores','Professores'=>'professores','Alunos'=>'alunos','Responsáveis'=>'responsaveis','Turma específica'=>'turma'];$public=$aud[choice($b['audience'],array_keys($aud))];$v=['autor_id'=>$old['autor_id']??$u['id'],'titulo'=>textValue($b['title'],200),'mensagem'=>textValue($b['description']),'publico'=>$public,'turma_id'=>$public==='turma'?validId($b['classId']??''):null,'data_publicacao'=>validDate($b['date']),'destaque'=>filter_var($b['pinned']??false,FILTER_VALIDATE_BOOLEAN)?1:0];
        }
        if($type==='users'){
            $role=choice($b['role']??'', ['diretor','coordenador','professor','aluno','responsavel']);$st=['Ativo'=>'ativo','Inativo'=>'inativo','Bloqueado'=>'bloqueado','Pendente'=>'pendente'][choice($b['status']??'Ativo',['Ativo','Inativo','Bloqueado','Pendente'])];
            if(!$old&&!in_array($role,['diretor','coordenador']))fail('Cadastre professores na equipe docente; alunos e responsáveis surgem pela matrícula.');if($old&&$role!==$old['cargo'])fail('O cargo de uma conta com vínculos não pode ser alterado.');if($id===(string)$u['id']&&$st!=='ativo')fail('Mantenha seu acesso ativo.');if($old&&$old['status']==='pendente'&&$st==='ativo')fail('Ative o aluno aprovando sua matrícula.');
            $v=['nome'=>textValue($b['name'],200),'email'=>validEmail($b['email']??''),'cargo'=>$role,'status'=>$st];if(!empty($b['password']))$v+=['senha_hash'=>password_hash(validPassword($b['password']),PASSWORD_DEFAULT),'primeiro_acesso'=>1];elseif(!$old)fail('Informe a senha temporária.');
        }
        if($type==='years'){$year=integerValue($b['year']??0,2000,2100);$start=validDate($b['start']??'');$end=validDate($b['end']??'');if($start>$end||substr($start,0,4)!=(string)$year||substr($end,0,4)!=(string)$year)fail('Datas devem estar dentro do ano letivo.');$v=['ano'=>$year,'data_inicio'=>$start,'data_fim'=>$end,'status'=>choice($b['status']??'ativo',['ativo','inativo'])];}
        if($type==='terms'){$year=one('SELECT * FROM anos_letivos WHERE id=?',[validId($b['yearId']??'')]);if(!$year)fail('Ano inexistente.');$start=validDate($b['start']??'');$end=validDate($b['end']??'');if($start>$end||$start<$year['data_inicio']||$end>$year['data_fim'])fail('Bimestre fora do ano letivo.');if(one('SELECT id FROM bimestres WHERE ano_letivo_id=? AND id<>? AND data_inicio<=? AND data_fim>=?',[$year['id'],$id??0,$end,$start]))fail('Bimestres não podem se sobrepor.');$n=integerValue($b['number']??0,1,4);$v=['ano_letivo_id'=>$year['id'],'numero'=>$n,'nome'=>$n.'º bimestre','data_inicio'=>$start,'data_fim'=>$end,'status'=>choice($b['status']??'ativo',['ativo','inativo'])];}
        if(in_array($type,['links','schedules'])){
            $teacher=validId($b['teacherId']??'');$class=validId($b['classId']??'');$subject=validId($b['subjectId']??'');if(!one('SELECT id FROM turma_disciplina WHERE turma_id=? AND disciplina_id=?',[$class,$subject]))fail('Inclua a disciplina no cadastro da turma.');
            $v=['professor_id'=>$teacher,'turma_id'=>$class,'disciplina_id'=>$subject];
            if($type==='links'){if($old)fail('Remova o vínculo e cadastre o novo.');query('INSERT IGNORE INTO professor_disciplina (professor_id,disciplina_id) VALUES (?,?)',[$teacher,$subject]);}
            else {if(!one('SELECT id FROM professor_turma WHERE professor_id=? AND turma_id=? AND disciplina_id=?',[$teacher,$class,$subject]))fail('Cadastre primeiro o vínculo do professor.');$start=validTime($b['start']??'');$end=validTime($b['end']??'');if($end<=$start)fail('Fim deve ser posterior ao início.');$day=integerValue($b['weekday']??0,1,6);if(one('SELECT id FROM horarios_aulas WHERE dia_semana=? AND id<>? AND (professor_id=? OR turma_id=?) AND hora_inicio<? AND hora_fim>?',[$day,$id??0,$teacher,$class,$end,$start]))fail('Existe conflito de horário da turma ou professor.');$v+=['dia_semana'=>$day,'hora_inicio'=>$start,'hora_fim'=>$end,'sala'=>textValue($b['room']??'',40)];}
        }
        if($type==='users'){
            if($old){$locked=one('SELECT * FROM usuarios WHERE id=? FOR UPDATE',[$id]);requestEmailChange($locked,$v['email']);}
            else {if(one('SELECT id FROM usuarios WHERE email=? OR email_pendente=?',[$v['email'],$v['email']]))fail('Este e-mail já está cadastrado.');$v['email_verificacao_exigida']=0;}
        }
        if($id)updateRow($table,$id,$v);else $id=insert($table,$v);
        
        if($type==='users'&&!$old)insert($v['cargo']==='diretor'?'diretores':'coordenadores',['usuario_id'=>$id]);
        if($type==='classes'){
            $subjects=array_values(array_unique(array_map('validId',$b['subjectIds']??[])));$existing=array_column(rows('SELECT disciplina_id FROM turma_disciplina WHERE turma_id=?',[$id]),'disciplina_id');
            foreach(array_diff($existing,$subjects) as $subject){if(one('SELECT id FROM professor_turma WHERE turma_id=? AND disciplina_id=?',[$id,$subject])||one('SELECT id FROM avaliacoes WHERE turma_id=? AND disciplina_id=?',[$id,$subject]))fail('Disciplina vinculada possui registros; preserve o vínculo.');query('DELETE FROM turma_disciplina WHERE turma_id=? AND disciplina_id=?',[$id,$subject]);}
            foreach($subjects as $subject)query('INSERT IGNORE INTO turma_disciplina (turma_id,disciplina_id) VALUES (?,?)',[$id,$subject]);
        }
        if($type==='teachers'){
            foreach(array_values(array_unique(array_map('validId',$b['subjectIds']??[]))) as $subject)query('INSERT IGNORE INTO professor_disciplina (professor_id,disciplina_id) VALUES (?,?)',[$id,$subject]);
            // Os vínculos de turma/disciplina são explícitos na tela Vínculos.
        }
        audit($old?'REGISTRO_ATUALIZADO':'REGISTRO_CADASTRADO',$table,$id);
        if($type==='occurrences')notify([...studentUsers($v['aluno_id']),...managers()],'Ocorrência registrada','occurrences');
        if($type==='activities')foreach(rows("SELECT aluno_id FROM aluno_turma WHERE turma_id=? AND status='ativo'",[$v['turma_id']]) as $s)notify(studentUsers((string)$s['aluno_id']),'Atividade: '.$v['titulo'],'activities');
        if($type==='announcements'){
            $ids=[];foreach(rows("SELECT id,cargo FROM usuarios WHERE status='ativo'") as $a){$plural=['diretor'=>'diretores','coordenador'=>'coordenadores','professor'=>'professores','aluno'=>'alunos','responsavel'=>'responsaveis'][$a['cargo']];if($v['publico']==='todos'||$v['publico']===$plural||isManager($a)||$v['publico']==='turma'&&in_array((string)$v['turma_id'],array_map('strval',permittedClasses($a))))$ids[]=$a['id'];}notify($ids,$v['titulo'],'announcements');
        }
        return ['id'=>$id,'ok'=>true];
    });
}
function removeRecord(array $u,string $type,string $id): array {
    $table=RECORD_TABLES[$type]??null;if(!$table)fail('Recurso inexistente.',404);$id=validId($id);$old=one("SELECT * FROM $table WHERE id=?",[$id]);if(!$old)fail('Registro inexistente.',404);recordPermission($u,$type,$old);
    return transaction(function()use($u,$type,$table,$id,$old){
        if($type==='users'&&$id===(string)$u['id'])fail('Você não pode inativar seu próprio acesso.');
        if(in_array($type,['evaluations','years','terms']))fail('Preserve o histórico. Edite o cadastro ou inative o ano/bimestre.');
        if($type==='links'){if(one('SELECT id FROM horarios_aulas WHERE professor_id=? AND turma_id=? AND disciplina_id=?',[$old['professor_id'],$old['turma_id'],$old['disciplina_id']]))fail('Remova primeiro os horários futuros vinculados.');query('DELETE FROM professor_turma WHERE id=?',[$id]);}
        elseif($type==='schedules')query('DELETE FROM horarios_aulas WHERE id=?',[$id]);
        else {updateRow($table,$id,['status'=>'inativo']);if(in_array($type,['teachers','students']))updateRow('usuarios',(string)$old['usuario_id'],['status'=>'inativo']);}
        audit('REGISTRO_INATIVADO',$table,$id);return ['ok'=>true];
    });
}
function validTime(mixed $v): string { $s=textValue($v,8);if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$s))fail('Horário inválido.');return strlen($s)===5?$s.':00':$s; }
