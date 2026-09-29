$ErrorActionPreference = 'Stop'
$sourcePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$targetPath = [IO.Path]::GetFullPath('C:\xampp\htdocs\escola')
if ($targetPath -ne 'C:\xampp\htdocs\escola') { throw 'Destino inesperado.' }
if (-not (Test-Path -LiteralPath 'C:\xampp\htdocs')) { throw 'htdocs do XAMPP não encontrado.' }
New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
$files = @('index.php','login.php','logout.php','matricula.php','status-matricula.php','confirmar-email.php','.htaccess','README.md','EMAIL-E-PARTICULAS.md')
$folders = @('lib','assets','config','database','includes','services','api','actions','diretor','coordenador','professor','aluno','responsavel')
foreach ($name in $files) { Copy-Item -LiteralPath (Join-Path $sourcePath $name) -Destination (Join-Path $targetPath $name) -Force }
foreach ($name in $folders) {
    $destination = Join-Path $targetPath $name
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
    Get-ChildItem -LiteralPath (Join-Path $sourcePath $name) -Force | Where-Object { $_.Name -ne 'mail.local.json' } | Copy-Item -Destination $destination -Recurse -Force
}
New-Item -ItemType Directory -Path (Join-Path $targetPath 'storage\sessions') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $sourcePath 'storage\.htaccess') -Destination (Join-Path $targetPath 'storage\.htaccess') -Force
Write-Output 'Versão PHP copiada para C:\xampp\htdocs\escola. Sessões de teste e node_modules não foram copiados.'
