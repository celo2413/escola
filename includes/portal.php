<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
if(isset($requiredRole)){
    $u=requireRole([$requiredRole]);
    if(isset($_GET['id'])){
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)fail('Identificador inválido.');
        if(in_array($initialPage??'',['classes','myclass'])&&!in_array((string)$id,array_map('strval',permittedClasses($u)),true))fail('Turma não autorizada.',403);
        if(($initialPage??'')==='student'&&!in_array((string)$id,array_map('strval',permittedStudents($u)),true))fail('Aluno não autorizado.',403);
        if(($initialPage??'')==='classes')$initialPage.='/'.$id;
    }
}
require __DIR__.'/header.php';
?>
<div id="app"><div class="loading">Preparando seu portal acadêmico…</div></div>
<?php require __DIR__.'/footer.php'; ?>
