<?php
declare(strict_types=1);
function required(array $b,array $keys): void { foreach($keys as $k)if(!isset($b[$k]) || !is_scalar($b[$k]) || trim((string)$b[$k])==='')fail('Preencha os campos obrigatórios.',422,[$k=>'Campo obrigatório.']); }
function textValue(mixed $v,int $max=5000): string { if(!is_scalar($v)&&$v!==null)fail('Formato de campo inválido.');$s=trim((string)$v);if(mb_strlen($s)>$max)fail('Texto excede o tamanho permitido.');return $s; }
function emailDomainAcceptsMail(string $domain, ?callable $resolver=null): bool {
    $resolver ??= static fn(string $host,int $type)=>@dns_get_record($host,$type);
    $mx=$resolver($domain,DNS_MX);
    if($mx===false)return false; // A resolver failure is not proof of a valid domain.
    if($mx){foreach($mx as $record)if(trim($record['target']??'','.')!=='')return true;return false;} // Null MX rejects mail.
    return (bool)($resolver($domain,DNS_A)?:$resolver($domain,DNS_AAAA));
}
function validEmail(mixed $v,bool $domainCheck=true,string $field='email'): string {
    $s=strtolower(textValue($v,190));$parts=explode('@',$s);$domain=$parts[1]??'';
    if(!filter_var($s,FILTER_VALIDATE_EMAIL)||count($parts)!==2||strlen($parts[0])>64||!preg_match('/^[a-z0-9.!#$%&\x27*+\/=?^_`{|}~-]+$/iD',$parts[0])||!str_contains($domain,'.')||!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/iD',$domain))fail('Digite um endereço de e-mail válido.',422,[$field=>'Digite um endereço de e-mail válido.']);
    if($domainCheck){static $cache=[];$cache[$domain]??=emailDomainAcceptsMail($domain);if(!$cache[$domain])fail('Não foi possível validar o domínio deste e-mail. Verifique se o endereço foi digitado corretamente.',422,[$field=>'Verifique o domínio do e-mail.']);}
    return $s;
}
function validId(mixed $v): string { if(!is_scalar($v)||!preg_match('/^[1-9][0-9]*$/',(string)$v))fail('Identificador inválido.');return (string)$v; }
function validDate(mixed $v): string { $s=textValue($v,10);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)fail('Data inválida.');return $s; }
function validPassword(mixed $v): string { $s=(string)$v;if(strlen($s)<8||strlen($s)>72)fail('A senha deve ter entre 8 e 72 caracteres.');return $s; }
function choice(mixed $v,array $choices): string { if(!in_array($v,$choices,true))fail('Opção inválida.');return $v; }
function numberValue(mixed $v,float $min,float $max): float { if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max)fail("Valor deve estar entre $min e $max.");return (float)$v; }
function integerValue(mixed $v,int $min,int $max): int { $n=numberValue($v,$min,$max);if(floor($n)!==$n)fail('Informe um número inteiro.');return (int)$n; }
function cpf(mixed $v,bool $optional=false): ?string { $s=preg_replace('/\D/','',textValue($v,20));if($optional&&$s==='')return null;if(strlen($s)!==11||preg_match('/^(\d)\1{10}$/',$s))fail('CPF inválido.');for($t=9;$t<11;$t++){for($d=0,$c=0;$c<$t;$c++)$d+=(int)$s[$c]*(($t+1)-$c);$d=((10*$d)%11)%10;if((int)$s[$c]!==$d)fail('CPF inválido.');}return $s; }
function phone(mixed $v,bool $optional=false): string { $s=preg_replace('/\D/','',textValue($v,25));if($optional&&$s==='')return '';if(!preg_match('/^\d{10,11}$/',$s))fail('Telefone deve ter DDD e 10 ou 11 dígitos.');return $s; }
function imageValue(mixed $v): string { $s=(string)$v;if($s==='')return '';if(strlen($s)>1450000||!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#',$s,$m))fail('Imagem inválida.');$bytes=base64_decode($m[2],true);if(!$bytes||!@getimagesizefromstring($bytes))fail('Imagem inválida.');return $s; }
