<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
$u=currentUser();header('Location: '.APP_BASE.($u?(($u['status']==='pendente'||!emailAccessAllowed($u))?'status-matricula.php':$u['cargo'].'/dashboard.php'):'login.php'));exit;
