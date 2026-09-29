<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../config/database.php';
$c=databaseConfig();$pdo=new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=$c['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/',$name))throw new RuntimeException('Nome de banco inválido.');
$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');$stmt->execute([$name]);if((int)$stmt->fetchColumn()>0){fwrite(STDERR,"Banco já contém tabelas. Instalação interrompida para preservar dados.\n");exit(1);}
$sql=file_get_contents(__DIR__.'/colegio_legado.sql');$sql=str_replace(['CREATE DATABASE IF NOT EXISTS colegio_legado','USE colegio_legado;'],['CREATE DATABASE IF NOT EXISTS `'.$name.'`','USE `'.$name.'`;'],$sql);$pdo->exec($sql);echo "Banco $name instalado: somente três contas iniciais.\n";
