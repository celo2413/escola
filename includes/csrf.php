<?php
declare(strict_types=1);
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function validateCsrf(): void { $value=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';if(!is_string($value)||!hash_equals(csrfToken(),$value))fail('Sessão de formulário expirada. Atualize a página.',403); }
