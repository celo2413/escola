<?php
declare(strict_types=1);
function submitEnrollment(array $b): array {
    required($b,['name','birth','cpf','rg','phone','email','cep','street','number','district','city','state','guardianName','guardianCpf','kinship','guardianPhone','guardianEmail','requestedGrade','requestedPeriod','year','loginEmail','password','confirm']);
    $email=validEmail($b['loginEmail'],true,'loginEmail');$guardianEmail=validEmail($b['guardianEmail'],true,'guardianEmail');if($email===$guardianEmail)fail('Aluno e responsável devem ter e-mails de acesso diferentes.');
    $password=validPassword($b['password']);if($password!==$b['confirm'])fail('As senhas não coincidem.',422,['confirm'=>'Confirme a mesma senha.']);
    $birth=validDate($b['birth']);if($birth>date('Y-m-d'))fail('Data de nascimento futura.');$cep=preg_replace('/\D/','',(string)$b['cep']);if(strlen($cep)!==8)fail('CEP inválido.');
    $v=['nome_aluno'=>textValue($b['name'],200),'cpf_aluno'=>cpf($b['cpf']),'rg_aluno'=>textValue($b['rg']??'',30),'data_nascimento'=>$birth,'telefone_aluno'=>phone($b['phone']),'email_aluno'=>validEmail($b['email']),'cep'=>$cep,'logradouro'=>textValue($b['street'],200),'numero'=>textValue($b['number'],20),'complemento'=>textValue($b['complement']??'',150),'bairro'=>textValue($b['district'],100),'cidade'=>textValue($b['city'],100),'estado'=>choice($b['state'],explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO')),'nome_responsavel'=>textValue($b['guardianName'],200),'cpf_responsavel'=>cpf($b['guardianCpf']),'parentesco'=>choice($b['kinship'],['Mãe','Pai','Avó','Avô','Tutor','Outro']),'telefone_responsavel'=>phone($b['guardianPhone']),'email_responsavel'=>$guardianEmail,'serie_pretendida'=>textValue($b['requestedGrade'],100),'periodo'=>choice($b['requestedPeriod'],['Manhã','Tarde','Noite']),'ano_letivo'=>integerValue($b['year'],2000,2100),'escola_anterior'=>textValue($b['previousSchool']??'',200),'observacoes'=>textValue($b['note']??''),'email_acesso'=>$email,'senha_hash'=>password_hash($password,PASSWORD_DEFAULT)];
    return transaction(function()use($v,$email){
        if(one('SELECT id FROM usuarios WHERE email=? OR email_pendente=?',[$email,$email]))fail('Este e-mail já está cadastrado.',422,['loginEmail'=>'Utilize outro e-mail.']);
        if(one('SELECT id FROM alunos WHERE cpf=?',[$v['cpf_aluno']])||one("SELECT id FROM solicitacoes_matricula WHERE cpf_aluno=? AND status IN ('email_nao_verificado','pendente','em_analise','aprovada')",[$v['cpf_aluno']]))fail('Já existe uma matrícula ou solicitação para esse CPF.');
        $uid=insert('usuarios',['nome'=>$v['nome_aluno'],'email'=>$email,'senha_hash'=>$v['senha_hash'],'cargo'=>'aluno','status'=>'pendente','email_verificacao_exigida'=>0,'email_verificado'=>0]);
        $id=insert('solicitacoes_matricula',['usuario_id'=>$uid,...$v,'status'=>'pendente']);audit('MATRICULA_SOLICITADA','solicitacoes_matricula',$id);notify(managers(),'Nova solicitação de matrícula','enrollments');return ['id'=>$id,'createdAt'=>date(DATE_ATOM),'status'=>'Pendente'];
    });
}
function reviewEnrollment(array $u,string $id,array $b): array {
    if($u['cargo']!=='diretor')fail('Somente a direção pode analisar matrículas.',403);$id=validId($id);$status=['Em análise'=>'em_analise','Aprovada'=>'aprovada','Reprovada'=>'reprovada','Cancelada'=>'cancelada'][choice($b['status']??'',['Em análise','Aprovada','Reprovada','Cancelada'])];
    return transaction(function()use($u,$id,$b,$status){
        $r=one('SELECT * FROM solicitacoes_matricula WHERE id=? FOR UPDATE',[$id]);if(!$r)fail('Solicitação não encontrada.',404);
        if(!in_array($r['status'],['email_nao_verificado','pendente','em_analise']))fail('Esta solicitação já foi concluída.',409);
        $v=['status'=>$status,'analisado_em'=>date('Y-m-d H:i:s'),'analisado_por'=>$u['id']];$result=['ok'=>true];
        if(in_array($status,['reprovada','cancelada'])){required($b,['reason']);$v['motivo_reprovacao']=textValue($b['reason']);}
        if($status==='aprovada'){
            required($b,['registration','classId','entry']);$class=one("SELECT * FROM turmas WHERE id=? AND status='ativo' FOR UPDATE",[validId($b['classId'])]);if(!$class)fail('Selecione uma turma ativa.');
            if($class['serie']!==$r['serie_pretendida']||$class['periodo']!==$r['periodo']||$class['ano_letivo']!=$r['ano_letivo'])fail('Turma deve corresponder à série, período e ano solicitados.');
            $count=one("SELECT COUNT(*) n FROM aluno_turma WHERE turma_id=? AND status='ativo'",[$class['id']]);if((int)$count['n']>=(int)$class['capacidade'])fail('A turma atingiu sua capacidade.');
            $entry=validDate($b['entry']);if(substr($entry,0,4)!=(string)$class['ano_letivo'])fail('Ingresso deve estar no ano letivo da turma.');
            $sv=['usuario_id'=>$r['usuario_id'],'matricula'=>textValue($b['registration'],40),'nome'=>$r['nome_aluno'],'cpf'=>$r['cpf_aluno'],'rg'=>$r['rg_aluno'],'data_nascimento'=>$r['data_nascimento'],'telefone'=>$r['telefone_aluno'],'email'=>$r['email_aluno'],'data_ingresso'=>$entry];foreach(['cep','logradouro','numero','complemento','bairro','cidade','estado'] as $k)$sv[$k]=$r[$k];$sid=insert('alunos',$sv);
            $guardian=one('SELECT * FROM responsaveis WHERE cpf=? OR email=? FOR UPDATE',[$r['cpf_responsavel'],$r['email_responsavel']]);
            if($guardian){if($guardian['cpf']!==$r['cpf_responsavel']||$guardian['email']!==$r['email_responsavel'])fail('CPF e e-mail do responsável não correspondem ao cadastro existente.');$gid=(string)$guardian['id'];}
            else {
                if(one('SELECT id FROM usuarios WHERE email=?',[$r['email_responsavel']]))fail('E-mail do responsável já pertence a outro perfil.');
                required($b,['guardianPassword']);$gpass=validPassword($b['guardianPassword']);
                $guid=insert('usuarios',['nome'=>$r['nome_responsavel'],'email'=>$r['email_responsavel'],'senha_hash'=>password_hash($gpass,PASSWORD_DEFAULT),'cargo'=>'responsavel','primeiro_acesso'=>1,'email_verificacao_exigida'=>0]);
                
                $gid=insert('responsaveis',['usuario_id'=>$guid,'nome'=>$r['nome_responsavel'],'cpf'=>$r['cpf_responsavel'],'telefone'=>$r['telefone_responsavel'],'email'=>$r['email_responsavel']]);
            }
            insert('responsavel_aluno',['responsavel_id'=>$gid,'aluno_id'=>$sid,'parentesco'=>$r['parentesco']]);insert('aluno_turma',['aluno_id'=>$sid,'turma_id'=>$class['id'],'data_inicio'=>$entry]);
            updateRow('usuarios',(string)$r['usuario_id'],['status'=>'ativo']);$v['aluno_id']=$sid;$result['studentId']=$sid;
            notify(studentUsers($sid),'Matrícula aprovada','dashboard');audit('ALUNO_CADASTRADO','alunos',$sid);
        }
        updateRow('solicitacoes_matricula',$id,$v);audit('MATRICULA_'.strtoupper($status),'solicitacoes_matricula',$id,$v['motivo_reprovacao']??'');return $result;
    });
}
