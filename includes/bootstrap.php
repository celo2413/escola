<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/config/database.php';
require_once APP_ROOT.'/config/session.php';
foreach(['functions','validation','csrf','auth','permissions'] as $file)require_once __DIR__."/$file.php";
require_once APP_ROOT.'/services/email.php';
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
set_exception_handler(function(Throwable $error): void {
    $status=$error instanceof HttpError?$error->status:500;
    $message=$error instanceof HttpError?$error->getMessage():'Não foi possível acessar o banco. Verifique o MySQL no XAMPP e a importação do SQL.';
    if($error instanceof PDOException&&$error->getCode()==='23000'){$status=409;$message='Registro duplicado ou vínculo incompatível. Confira os dados informados.';}
    if(APP_DEBUG)error_log((string)$error);
    if(defined('API_REQUEST'))jsonResponse(['message'=>$message,'fields'=>$error instanceof HttpError?$error->fields:[]],$status);
    http_response_code($status);echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Colégio Legado</title><link rel="stylesheet" href="'.e(APP_BASE).'assets/css/global.css"><main class="main"><section class="card card-body"><h1>'.($status===403?'403 — Acesso não autorizado':($status===401?'Entre no portal':'Portal indisponível')).'</h1><p>'.e($message).'</p><a class="btn primary space-top" href="'.e(APP_BASE).'login.php">Voltar ao login</a></section></main></html>';
});
