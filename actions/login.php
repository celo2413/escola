<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')fail('Método não permitido.',405);
validateCsrf();$u=authenticate($_POST);header('Location: '.APP_BASE.(($u['status']==='pendente'||!emailAccessAllowed($u))?'status-matricula.php':$u['cargo'].'/dashboard.php'));exit;
