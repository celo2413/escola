<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../config/database.php';
if(databaseConfig()['database']!=='colegio_legado_test')throw new RuntimeException('Reset permitido somente em colegio_legado_test.');
$sql=str_replace('USE colegio_legado;','USE colegio_legado_test;',file_get_contents(__DIR__.'/../database/reset_database.sql'));
db()->exec($sql);echo "Base de testes reinicializada; base da escola preservada.\n";
