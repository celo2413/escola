<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
define('APP_ROOT', dirname(__DIR__));
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$relative = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'] ?? '', strlen(APP_ROOT)));
define('APP_BASE', PHP_SAPI === 'cli' ? '/' : (str_ends_with($script, $relative) && $relative !== '' ? substr($script, 0, -strlen($relative)).'/' : '/'));
ini_set('display_errors', '0');
error_reporting(E_ALL);
