<?php
declare(strict_types=1);
function isManager(array $u): bool { return in_array($u['cargo'],['diretor','coordenador'],true); }
function teacherId(array $u): string { return (string)(one('SELECT id FROM professores WHERE usuario_id=?',[$u['id']])['id']??''); }
function permittedStudents(array $u): array {
    if(isManager($u))return array_column(rows('SELECT id FROM alunos'),'id');
    if($u['cargo']==='professor')return array_column(rows("SELECT DISTINCT a.aluno_id id FROM aluno_turma a JOIN professor_turma pt ON pt.turma_id=a.turma_id JOIN professores p ON p.id=pt.professor_id WHERE p.usuario_id=? AND a.status='ativo'",[$u['id']]),'id');
    if($u['cargo']==='aluno')return array_column(rows('SELECT id FROM alunos WHERE usuario_id=?',[$u['id']]),'id');
    return array_column(rows('SELECT ra.aluno_id id FROM responsavel_aluno ra JOIN responsaveis r ON r.id=ra.responsavel_id WHERE r.usuario_id=?',[$u['id']]),'id');
}
function permittedClasses(array $u): array {
    if(isManager($u))return array_column(rows('SELECT id FROM turmas'),'id');
    if($u['cargo']==='professor')return array_column(rows('SELECT DISTINCT turma_id id FROM professor_turma WHERE professor_id=?',[teacherId($u)]),'id');
    $ids=permittedStudents($u);if(!$ids)return [];return array_column(rows("SELECT DISTINCT turma_id id FROM aluno_turma WHERE status='ativo' AND aluno_id IN (".implode(',',array_fill(0,count($ids),'?')).')',$ids),'id');
}
function teachingLink(array $u,string $class,string $subject,?string $teacher=null): string {
    if(!in_array($u['cargo'],['diretor','professor'],true))fail('Ação reservada à direção e ao professor vinculado.',403);
    $teacher=$u['cargo']==='professor'?teacherId($u):$teacher;
    $link=one("SELECT pt.professor_id FROM professor_turma pt JOIN professores p ON p.id=pt.professor_id JOIN turmas t ON t.id=pt.turma_id WHERE pt.turma_id=? AND pt.disciplina_id=? AND p.status='ativo' AND t.status='ativo'".($teacher?' AND pt.professor_id=?':'').' ORDER BY pt.id LIMIT 1', $teacher?[$class,$subject,$teacher]:[$class,$subject]);
    if(!$link)fail('Professor não vinculado a esta turma e disciplina.',403);return (string)$link['professor_id'];
}
function assertStudentClass(string $student,string $class): void { if(!one("SELECT id FROM aluno_turma WHERE aluno_id=? AND turma_id=? AND status='ativo'",[$student,$class]))fail('Aluno não vinculado à turma.',403); }
