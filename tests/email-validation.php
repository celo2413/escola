<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/validation.php';
$count=0;
function checkEmail(bool $condition,string $name): void { global $count;if(!$condition)throw new RuntimeException($name);$count++; }
foreach(['usuario@gmail.com','nome.sobrenome@outlook.com','aluno@colegio.com.br','nome+turma@gmail.com'] as $email)checkEmail(validEmail($email,false)===$email,'Formato válido');
foreach(['usuario@','usuario','@gmail.com','usuario@gmail','usuario gmail.com','a..b@gmail.com','.nome@gmail.com','nome.@gmail.com','a@-gmail.com','a@gmail..com',str_repeat('a',65).'@gmail.com'] as $email){try{validEmail($email,false);throw new RuntimeException('Formato aceito: '.$email);}catch(HttpError $e){checkEmail($e->status===422,'Formato rejeitado');}}
checkEmail(emailDomainAcceptsMail('example.org',fn($host,$type)=>$type===DNS_MX?[['target'=>'mx.example.org']]:[]),'MX');
checkEmail(!emailDomainAcceptsMail('example.org',fn($host,$type)=>$type===DNS_MX?[['target'=>'.']]:[['ip'=>'127.0.0.1']]),'Null MX não aceita fallback');
checkEmail(emailDomainAcceptsMail('example.org',fn($host,$type)=>$type===DNS_A?[['ip'=>'192.0.2.1']]:[]),'Fallback A');
checkEmail(emailDomainAcceptsMail('example.org',fn($host,$type)=>$type===DNS_AAAA?[['ipv6'=>'2001:db8::1']]:[]),'Fallback AAAA');
checkEmail(!emailDomainAcceptsMail('example.org',fn()=>[]),'Domínio inexistente');
checkEmail(!emailDomainAcceptsMail('example.org',fn()=>false),'Falha de resolução');
checkEmail(validEmail('teste@gmail.com')==='teste@gmail.com','DNS real Gmail');
try{validEmail('teste@dominio-inexistente.invalid');throw new RuntimeException('Domínio inválido aceito');}catch(HttpError $e){checkEmail($e->status===422,'DNS real inexistente');}
echo "$count verificações de e-mail aprovadas.\n";
