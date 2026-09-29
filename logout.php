<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')fail('Use o botão Sair dentro do portal.',405);
validateCsrf();endSession();header('Location: '.APP_BASE.'login.php');exit;
