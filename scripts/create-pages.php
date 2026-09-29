<?php
declare(strict_types=1);
// Entradas PHP executam autorização antes de entregar a interface compartilhada.
$common=['dashboard'=>'dashboard','perfil'=>'profile','comunicados'=>'announcements','notificacoes'=>'notifications'];
$roles=[
'diretor'=>['matriculas'=>'enrollments','matricula'=>'enrollments','alunos'=>'students','professores'=>'teachers','coordenadores'=>'coordinators','turmas'=>'classes','disciplinas'=>'subjects','pontos'=>'time','usuarios'=>'users','relatorios'=>'reports','notas'=>'grades','frequencia'=>'attendance','chamada'=>'diary','conteudos'=>'contents','atividades'=>'activities','ocorrencias'=>'occurrences','configuracoes'=>'settings','auditoria'=>'audit','anos-letivos'=>'years','bimestres'=>'terms','vinculos'=>'links','horarios'=>'schedules','avaliacoes'=>'evaluations'],
'coordenador'=>['alunos'=>'students','turmas'=>'classes','professores'=>'teachers','notas'=>'grades','frequencia'=>'attendance','ocorrencias'=>'occurrences','disciplinas'=>'subjects','anos-letivos'=>'years','bimestres'=>'terms','vinculos'=>'links','horarios'=>'schedules','atividades'=>'activities','relatorios'=>'reports'],
'professor'=>['ponto'=>'time','agenda'=>'agenda','turmas'=>'classes','turma'=>'classes','chamada'=>'diary','diario'=>'contents','notas'=>'grades','avaliacoes'=>'evaluations','atividades'=>'activities','ocorrencias'=>'occurrences'],
'aluno'=>['boletim'=>'reportcard','notas'=>'mygrades','frequencia'=>'attendance','faltas'=>'absences','atividades'=>'activities','turma'=>'myclass','professores'=>'teachers','ocorrencias'=>'occurrences','agenda'=>'agenda'],
'responsavel'=>['aluno'=>'student','boletim'=>'reportcard','notas'=>'mygrades','frequencia'=>'attendance','faltas'=>'absences','atividades'=>'activities','ocorrencias'=>'occurrences','agenda'=>'agenda']];
foreach($roles as $role=>$pages){$dir=dirname(__DIR__).'/'.$role;if(!is_dir($dir))mkdir($dir);foreach($pages+$common as $file=>$page)file_put_contents($dir.'/'.$file.'.php',"<?php\ndeclare(strict_types=1);\n\$requiredRole='$role';\n\$initialPage='$page';\nrequire __DIR__.'/../includes/portal.php';\n");}
foreach(['config','database','includes','services','scripts','tests','storage'] as $name){$dir=dirname(__DIR__).'/'.$name;if(!is_dir($dir))mkdir($dir,0700,true);file_put_contents($dir.'/.htaccess',"Require all denied\n");}
echo "Entradas protegidas criadas.\n";
