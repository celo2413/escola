<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#123B66"><meta name="description" content="Colégio Legado — Sistema de Gestão Escolar">
<meta name="app-base" content="<?= e(APP_BASE) ?>"><meta name="initial-page" content="<?= e($initialPage??'dashboard') ?>">
<script src="<?= e(APP_BASE) ?>assets/js/theme.js?v=<?= filemtime(APP_ROOT.'/assets/js/theme.js') ?>"></script>
<title>Portal Colégio Legado</title>
<link rel="icon" type="image/jpeg" href="<?= e(APP_BASE) ?>assets/images/logo-oficial.jpg">
<?php foreach(['global','login','dashboard','responsive','portal','brand','particles'] as $css): ?>
<link rel="stylesheet" href="<?= e(APP_BASE) ?>assets/css/<?= e($css) ?>.css?v=<?= filemtime(APP_ROOT.'/assets/css/'.$css.'.css') ?>">
<?php endforeach; ?>
<?php foreach($pageScripts??['particles','email-validation','database','auth','ui','entities','academic','dashboard','workflows','management','app'] as $js): ?>
<script defer src="<?= e(APP_BASE) ?>assets/js/<?= e($js) ?>.js?v=<?= filemtime(APP_ROOT.'/assets/js/'.$js.'.js') ?>"></script>
<?php endforeach; ?>
</head><body>
<canvas id="particle-network" aria-hidden="true"></canvas>
<a href="#main" class="skip-link">Pular para o conteúdo</a>
