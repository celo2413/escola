<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $dir=APP_ROOT.'/storage/sessions';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    session_save_path($dir);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_name('LEGADOSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>APP_BASE,'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
    session_start();
}
