# Partículas e confirmação de e-mail

O fundo usa Canvas e JavaScript puro, até 80 partículas em desktop, 48 em tablet e 28 em celular. As conexões consultam apenas células vizinhas. A renderização limita-se a 30 quadros por segundo, pausa com a aba oculta e fica estática com `prefers-reduced-motion`. A resolução considera o DPR, com teto de 2 e 8 milhões de pixels. As cores acompanham o `data-theme` existente. O Canvas não recebe cliques e fica atrás do conteúdo; não aparece na impressão.

## Configuração necessária para envio real

Para o Gmail `cauajdn2003@gmail.com`, o servidor `smtp.gmail.com`, a porta 587 e STARTTLS já estão preparados. Ative a verificação em duas etapas na conta Google, crie uma [senha de app](https://myaccount.google.com/apppasswords) e abra `CONFIGURAR-GMAIL.cmd` na pasta do projeto. Digite a senha somente na janela local. O configurador testa TLS e autenticação sem enviar mensagens e salva a credencial apenas se o teste passar, em `C:\xampp\htdocs\escola\config\mail.local.json`. Esse arquivo é privado ao servidor, bloqueado pelo Apache, ignorado pelo Git e preservado pelo deploy. Não compartilhe esse arquivo. Não é necessário reiniciar o Apache para essa configuração local.

Variáveis de ambiente têm prioridade sobre o arquivo local, inclusive quando explicitamente vazias. O envio só estará ativo após concluir esse procedimento. [Instruções oficiais do Google](https://support.google.com/accounts/answer/1070455?hl=pt-BR).

O envio está implementado com **PHPMailer 7.1.1**, incluído em `lib/phpmailer`, com sua licença. Falta configurar a conta SMTP da escola. Sem essa configuração, o cadastro é preservado como não verificado, a interface informa a falha e permite reenviar depois. Não há confirmação automática, envio simulado em produção ou liberação indevida de matrícula.

Defina estas variáveis no ambiente do processo Apache/PHP, então reinicie o Apache. `config/mail.php` lê o ambiente; arquivos `.env` não são carregados automaticamente.

| Variável | Conteúdo |
|---|---|
| `SMTP_HOST` | Host informado pelo provedor |
| `SMTP_PORT` | Normalmente `587` para STARTTLS ou `465` para TLS implícito |
| `SMTP_SECURITY` | `tls` para STARTTLS ou `ssl` para TLS implícito |
| `SMTP_USER` | Usuário da conta de envio |
| `SMTP_PASS` | Credencial SMTP ou senha de aplicativo do provedor |
| `MAIL_FROM` | Endereço remetente autorizado pelo provedor |
| `APP_URL` | URL completa do portal, sem barra final |

Não grave credenciais no repositório nem as envie pelo chat. Use o método de autenticação SMTP permitido pelo provedor. Em produção, `APP_URL` deve usar HTTPS e ser acessível aos destinatários. O padrão `http://localhost/escola` serve somente para abrir o link neste computador: `localhost` no celular aponta para o próprio celular.

Após configurar, faça uma matrícula com um endereço controlado pela escola, abra o link recebido e clique em **Confirmar meu e-mail**. Em seguida, confira a solicitação no painel do diretor. Os testes automatizados não enviam mensagens a pessoas reais.

## Fluxo e proteção

- Formato validado no JavaScript e no PHP; domínios consultados no DNS com MX e fallback A/AAAA quando não há MX. Null MX é rejeitado. A verificação DNS não prova a existência de uma caixa postal. O login valida o formato, mas não depende da disponibilidade do DNS para contas existentes.
- Matrícula nova: `email_nao_verificado` → confirmação → `pendente` (aguardando aprovação) → decisão da direção. A API bloqueia análise antes da confirmação. O diretor recebe a matrícula na fila e a notificação apenas depois da confirmação.
- Novas contas administrativas, docentes e de responsáveis também precisam confirmar o próprio e-mail. A confirmação do aluno não confirma o endereço do responsável.
- Token de 32 bytes aleatórios, representado por 64 caracteres hexadecimais; somente SHA-256 fica no banco. Validade de 24 horas, rotação no reenvio, consumo único com bloqueio transacional da linha.
- O token chega pelo fragmento da URL e é removido do histórico da página. Não aparece na query string, no Referer ou no log de acesso do Apache. O consumo exige POST e CSRF; visitar o link não altera o banco automaticamente.
- Reenvio autenticado: intervalo mínimo de 60 segundos, até 5 solicitações por usuário/hora e 20 por IP/hora. Confirmações: 60 tentativas/IP/hora. Recuperação: 10 solicitações/IP/hora. Limites persistidos, com locks para concorrência e limpeza de entradas antigas.
- Alteração de e-mail mantém o endereço anterior até confirmar o novo. Alteração pelo próprio usuário exige a senha atual; a direção continua podendo solicitar alterações pelos cadastros autorizados. Endereços pendentes não são expostos como confirmados.
- SMTP ocorre depois do commit. Uma falha não elimina o cadastro nem marca o endereço como confirmado. Logs internos registram o tipo e código da falha, sem senha, token ou conteúdo da mensagem.
- Contas anteriores à migração mantêm acesso por uma exceção explícita de legado (`email_verificacao_exigida=0`), sem marcar `email_verificado=1` indevidamente. Novas contas têm exigência ativa por padrão. Contas iniciais do SQL também mantêm essa exceção para permitir configurar a escola.
- A recuperação de senha mantém a regra existente: a secretaria verifica a identidade e redefine o acesso. Foram acrescentadas validação e limitação de solicitações; ela não foi substituída por redefinição automática.

## Instalação e atualização

Em um banco existente, faça backup e execute **uma vez** (pode ser repetido com segurança):

```powershell
& C:\xampp\php\php.exe database/migrate-email.php
```

A migração verifica colunas e índices antes de criá-los e não apaga registros. `database/colegio_legado.sql` já inclui a estrutura para instalações novas. Não reimporte esse SQL sobre uma instalação existente. O banco é selecionado por `config/database.php` / `DB_NAME`.

`scripts/deploy-xampp.ps1` inclui a página `confirmar-email.php`, os novos assets e o PHPMailer. As pastas de código interno e dependências ficam bloqueadas pelo Apache.

## Verificação automatizada

`tests/email-validation.php`: formato, DNS, Null MX e fallback; `tests/particles-email.cjs`: validação no navegador simulado, densidade, DPI, tema, aba oculta e redução de movimento; `tests/theme-logo.cjs`: regressão das logos.

`tests/integration.php`: matrícula, CSRF, confirmação, aprovação, permissões, notas e frequência. Agora também aceita o banco isolado `escola_email_test`. `tests/email-security.php` exige esse banco e verifica hash, expiração, reenvio, consumo, mudanças de endereço, rollback, limites e construção da mensagem real sem envio. `tests/php-interface.cjs` verifica as telas com a API PHP.

Os testes de integração precisam de uma base de testes recém-instalada e servidor PHP na porta 8089 com `DB_NAME=escola_email_test` e `SMTP_HOST` vazio. Nunca execute a suíte no banco `escola`.

Referências: [PHPMailer oficial](https://github.com/PHPMailer/PHPMailer), [DNS no PHP](https://www.php.net/manual/en/function.dns-get-record.php).
