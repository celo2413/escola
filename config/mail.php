<?php
declare(strict_types=1);
function mailConfig(): array {
    $path=__DIR__.'/mail.local.json';
    $local=is_file($path)?json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR):[];
    if(!is_array($local))throw new RuntimeException('Configuração local inválida.');
    $value=static function(string $env,string $key,mixed $default)use($local): mixed {
        $configured=getenv($env);
        return $configured!==false?$configured:($local[$key]??$default);
    };
    return [
        'host' => $value('SMTP_HOST','host','smtp.gmail.com'),
        'port' => (int)$value('SMTP_PORT','port',587),
        'security' => $value('SMTP_SECURITY','security','tls'),
        'username' => $value('SMTP_USER','username','cauajdn2003@gmail.com'),
        'password' => $value('SMTP_PASS','password',''),
        'from' => $value('MAIL_FROM','from','cauajdn2003@gmail.com'),
        'url' => rtrim($value('APP_URL','url','http://localhost/escola'), '/'),
    ];
}
