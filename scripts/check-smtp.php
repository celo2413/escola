<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('APP_ROOT',dirname(__DIR__));
require_once APP_ROOT.'/includes/functions.php';
require_once APP_ROOT.'/services/email.php';
try {
    $config=mailConfig();
    if(!$config['password']){fwrite(STDERR,"Falta inserir a senha de app do Google no configurador local.\n");exit(2);}
    $mail=verificationMailer(['email'=>$config['from'],'name'=>'Colégio Legado','token'=>bin2hex(random_bytes(32))]);
    if(!$mail->smtpConnect())throw new RuntimeException('SMTP indisponível');
    $mail->smtpClose();
    echo "Conexão TLS e autenticação SMTP aprovadas. Nenhuma mensagem enviada.\n";
} catch(Throwable $error) {
    if(isset($mail))$mail->smtpClose();
    fwrite(STDERR,"Não foi possível autenticar no Gmail. Confira a senha de app, a internet e a liberação da porta 587. Nenhuma senha foi gravada pelo teste.\n");
    exit(1);
}
