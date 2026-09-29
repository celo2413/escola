<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
$u=requireLogin(true);if($u['status']==='ativo'&&emailAccessAllowed($u)){header('Location: '.APP_BASE.$u['cargo'].'/dashboard.php');exit;}
$initialPage='dashboard';require __DIR__.'/includes/portal.php';
