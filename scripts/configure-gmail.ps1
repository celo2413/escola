$ErrorActionPreference = 'Stop'
$projectPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$targetPath = 'C:\xampp\htdocs\escola\config\mail.local.json'
if (-not (Test-Path -LiteralPath 'C:\xampp\htdocs\escola\config\.htaccess')) { throw 'Instalacao do portal nao encontrada.' }
Write-Host 'Configurar Gmail: cauajdn2003@gmail.com'
Write-Host 'Crie uma senha de app em https://myaccount.google.com/apppasswords'
Write-Host 'A verificacao em duas etapas precisa estar ativada na conta Google.'
Write-Host 'Digite somente a senha de app. Nao use sua senha normal do Gmail.'
$secret = Read-Host 'Senha de app (entrada oculta)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) -replace '\s',''
    if ($password -notmatch '^[a-zA-Z]{16}$') { throw 'A senha de app deve ter 16 letras. Os espacos sao removidos automaticamente.' }
    $env:SMTP_HOST='smtp.gmail.com'; $env:SMTP_PORT='587'; $env:SMTP_SECURITY='tls'
    $env:SMTP_USER='cauajdn2003@gmail.com'; $env:MAIL_FROM='cauajdn2003@gmail.com'
    $env:SMTP_PASS=$password; $env:APP_URL='http://localhost/escola'
    Write-Host 'Testando conexao protegida e autenticacao, sem enviar mensagens...'
    & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'check-smtp.php')
    if ($LASTEXITCODE -ne 0) { throw 'Configuracao nao salva. Confira a senha de app e tente novamente.' }
    $settings=@{host=$env:SMTP_HOST;port=587;security='tls';username=$env:SMTP_USER;password=$password;from=$env:MAIL_FROM;url=$env:APP_URL}
    [IO.File]::WriteAllText($targetPath,($settings | ConvertTo-Json),[Text.UTF8Encoding]::new($false))
    Write-Host 'SMTP ativado no portal. Entre em localhost/escola e use Reenviar e-mail de confirmacao.'
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $secret.Dispose(); $password=$null; $settings=$null
    Remove-Item Env:\SMTP_PASS -ErrorAction SilentlyContinue
}
