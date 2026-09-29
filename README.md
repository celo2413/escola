# Colégio Legado — PHP + MySQL + XAMPP

O portal agora inclui partículas adaptadas ao tema e confirmação de e-mail para novos cadastros. Consulte [configuração SMTP, migração e testes](EMAIL-E-PARTICULAS.md). **O envio real depende das credenciais SMTP da escola.** A confirmação de e-mail é uma etapa anterior à aprovação da matrícula; as contas existentes mantêm seu acesso.

Sistema de gestão escolar com backend PHP 8.2, PDO, MySQL/MariaDB e interface HTML/CSS/JavaScript. Não utiliza Express, SQLite, Firebase nem serviços externos de dados. **Não precisa de Node.js ou npm para funcionar.** A logo oficial, o azul institucional, os componentes responsivos e os temas claro/escuro foram preservados.

Nesta máquina, a versão foi instalada em `C:\xampp\htdocs\escola` e o banco inicial já foi importado. Basta manter Apache/MySQL ligados e abrir o endereço do portal. As instruções abaixo também servem para uma nova instalação; não é necessário reimportar o banco já instalado.

## Instalação local atual

Abra http://localhost/escola/ com Apache e MySQL ligados. Esta instalação usa o banco `escola`; o banco antigo `colegio_legado` foi preservado devido a um erro de tablespace. Não importe o SQL novamente nesta instalação.

## Iniciar no XAMPP

1. Abra o painel do **XAMPP**.
2. Clique em **Start** para **Apache**.
3. Clique em **Start** para **MySQL**.
4. Coloque o projeto em `C:\xampp\htdocs\escola\`. Copie os arquivos PHP, `.htaccess`, `assets`, `config`, `database`, `includes`, `services`, `api`, `actions` e as cinco pastas de perfis. Não copie `node_modules`, sessões locais ou resultados de testes.
5. Abra [phpMyAdmin](http://localhost/phpmyadmin/).
6. Na aba **Importar**, escolha `database/colegio_legado.sql` e execute. O arquivo cria `colegio_legado`, as tabelas e as três contas iniciais. **Importe uma única vez em um banco vazio**. Não reimporte por cima de cadastros existentes.
7. Confira `config/database.php`: host `127.0.0.1`, porta `3306`, banco `colegio_legado`, usuário `root` e senha vazia no XAMPP padrão. Se sua instalação tiver senha, configure-a nesse único arquivo ou use `DB_PASS`.
8. Abra **[http://localhost/escola/](http://localhost/escola/)**.
9. Entre com uma das contas abaixo.

Não abra o antigo `index.html` nem utilize Live Server. A entrada agora é `index.php`, servida pelo Apache.

| Perfil | E-mail inicial | Senha inicial |
|---|---|---|
| Diretor | diretor@gmail.com | 123456 |
| Coordenador | coordenador@gmail.com | 123456 |
| Professor | professor@gmail.com | 123456 |

As credenciais não aparecem na tela de login. O banco guarda somente hashes gerados com `password_hash(PASSWORD_DEFAULT)`. Troque a senha em **Meu perfil → Alterar senha**. Novos usuários administrativos, docentes e responsáveis recebem uma senha temporária de pelo menos oito caracteres e devem trocá-la antes de acessar os módulos.

O banco inicial contém **zero alunos, responsáveis, solicitações, turmas, anos letivos, bimestres, avaliações, notas, chamadas, atividades, ocorrências, comunicados, notificações e pontos**. Existem somente três usuários, seus registros de cargo e uma configuração institucional. Registros de auditoria surgem conforme operações reais, como login, são realizadas.

## Primeira configuração e matrícula

1. Direção ou coordenação cadastra **Anos letivos** e **Bimestres**, com suas datas reais. Bimestres não podem se sobrepor.
2. Cadastra **Disciplinas** e **Turmas**, definindo série, curso, período, ano, sala, capacidade e disciplinas.
3. A direção cadastra outros **Professores**, quando necessário. O professor inicial já está disponível.
4. Em **Vínculos docentes**, vincula explicitamente professor + turma + disciplina. Selecionar o professor responsável da turma não concede automaticamente acesso a todas as disciplinas.
5. Cadastra **Horários**. O sistema impede conflitos de professor ou turma.
6. A direção atualiza o ano, bimestre, média mínima e contato da escola em **Configurações**.
7. O candidato abre **[Solicitar matrícula](http://localhost/escola/matricula.php)** e preenche quatro etapas: aluno/endereço, responsável, dados acadêmicos e acesso.
8. A solicitação cria uma conta pendente. O candidato pode consultar o status, mas não acessar dados acadêmicos.
9. A direção analisa em **Matrículas**, podendo colocar em análise, aprovar, reprovar ou cancelar. Reprovação e cancelamento exigem motivo.
10. Na aprovação, informa matrícula escolar, turma e data de ingresso. A turma determina série, período e ano, que devem coincidir com a solicitação. A capacidade da turma é conferida.
11. Para um **novo responsável**, a direção define uma senha temporária no formulário e a entrega por canal seguro. Não há envio automático de e-mail. Se o responsável já existir, CPF e e-mail precisam corresponder ao mesmo cadastro, e sua senha é preservada.

Toda a aprovação é uma transação: aluno, responsável, vínculos, ativação do usuário, notificações e auditoria. Qualquer falha provoca rollback. Alunos e responsáveis não são criados manualmente na tela Usuários.

## Módulos implementados

- Login PHP, sessões HttpOnly/SameSite, regeneração do identificador, logout, limite de tentativas e alteração de senha.
- Autorização de cada operação e das URLs PHP por cargo; candidato pendente vê somente sua solicitação.
- Direção: cadastros, matrículas, usuários, equipe, parâmetros, relatórios, auditoria e análise de ajustes de ponto.
- Coordenação: consultas acadêmicas, turmas, disciplinas, ano letivo, bimestres, vínculos, horários, comunicados e ocorrências. Não aprova matrícula, altera permissões, lança notas nem analisa ponto.
- Professor: somente turmas/disciplinas vinculadas, alunos dessas turmas, avaliações próprias, notas, chamada, conteúdos, atividades, ocorrências próprias, agenda e ponto.
- Aluno: somente seu cadastro, notas, boletim, frequência, faltas, turma, atividades, ocorrências e comunicados permitidos.
- Responsável: somente filhos vinculados, com seletor de aluno acompanhado.
- Avaliações configuráveis por bimestre, tipo, data e valor máximo; lançamento de notas em transação com validação no PHP e no formulário.
- Boletim: notas convertidas para escala 0–10, média aritmética das avaliações lançadas e recuperação substituindo a média quando superior. Resultado parcial até haver notas nos quatro bimestres.
- Chamada por turma, disciplina, data e número da aula; exige presença de todos os alunos ativos. Correção da mesma chamada não duplica presenças.
- Frequência agregada em consultas SQL. Faltas justificadas continuam no total de ausências.
- Ponto: entrada/saída pelo relógio do servidor em `America/Sao_Paulo`, um registro por professor/dia. Correções exigem solicitação e decisão da direção; valores anteriores ficam na auditoria.
- Atividades, diário de conteúdos, ocorrências, comunicados por público, notificações individuais, perfil e foto de perfil.
- Pesquisa, filtros, relatórios, CSV e impressão do boletim/PDF pela opção **Salvar como PDF** do navegador.
- Inativação de cadastros sem apagar histórico. Horários e vínculos docentes podem ser removidos explicitamente; avaliações com notas preservam seus parâmetros.
- Recuperação de acesso: pedido persistido, notificação e registro de auditoria para a direção. A secretaria verifica a identidade e redefine a senha temporária em Usuários; não há SMTP automático.

Materiais de atividades e conteúdos são referências textuais. Não existe envio de arquivos de atividades. A logo institucional é o arquivo original `assets/images/logo-oficial.jpg`, preservado sem edição.

## Estrutura e arquivos

| Arquivo/pasta | Responsabilidade |
|---|---|
| `index.php`, `login.php`, `logout.php` | Entrada, login e encerramento de sessão |
| `matricula.php`, `status-matricula.php` | Solicitação pública e acompanhamento |
| `config/app.php` | Fuso, caminho base e `APP_DEBUG` |
| `config/database.php` | Configuração centralizada e conexão PDO |
| `config/session.php` | Cookie e armazenamento privado de sessões |
| `includes/bootstrap.php` | Inicialização, cabeçalhos e tratamento de erros |
| `includes/auth.php`, `permissions.php`, `csrf.php`, `validation.php` | Autenticação, autorização e validação |
| `includes/functions.php` | Consultas parametrizadas, transações, auditoria e notificações |
| `includes/header.php`, `footer.php`, `portal.php` | Estrutura visual compartilhada e guarda das páginas |
| `api/index.php` | API interna PHP; Fetch usa `?route=...`, sem exigir mod_rewrite |
| `actions/login.php` | Alternativa de login POST com CSRF e redirecionamento por cargo |
| `services/records.php` | Cadastros, vínculos, validações e inativação |
| `services/enrollments.php` | Matrícula e aprovação transacional |
| `services/academic.php` | Notas e chamada |
| `services/time.php` | Ponto e ajustes |
| `services/snapshot.php` | Consultas MySQL e resposta filtrada por usuário |
| `diretor/`, `coordenador/`, `professor/`, `aluno/`, `responsavel/` | Entradas PHP protegidas para cada tela |
| `assets/js/management.js` | Interface de anos, bimestres, vínculos, horários e avaliações |
| `assets/js/` | Componentes existentes adaptados à API PHP |
| `assets/css/`, `assets/images/` | Aparência e logo preservadas |
| `database/colegio_legado.sql` | Instalação completa com 32 tabelas e contas mínimas |
| `database/reset_database.sql` | Reset destrutivo e recriação das três contas |
| `database/seeds.php` | Seed CLI mínimo; recusa banco que já possui usuários |
| `database/install.php` | Instalação CLI opcional; recusa banco com tabelas existentes |
| `tests/integration.php`, `tests/reset.php` | Testes PHP/MySQL isolados e reset somente da base de testes |
| `tests/php-interface.cjs` | Teste DOM opcional com jsdom, usado durante a migração |
| `.htaccess` | Bloqueio HTTP de SQL, configuração, sessões, testes e arquivos internos |

Foram removidos `server.js`, os módulos Express, repositório/schema SQLite, seeds fictícios, banco `.db`, testes antigos e manifests npm do backend. Os arquivos JS existentes foram adaptados; o CSS e a imagem oficial foram mantidos. O navegador mantém apenas uma cópia temporária da resposta da API: não grava dados acadêmicos em localStorage/sessionStorage. LocalStorage é usado exclusivamente para tema e sidebar.

## Tabelas criadas

Todas utilizam **InnoDB**, **utf8mb4** e **utf8mb4_unicode_ci**:

`usuarios`, `diretores`, `coordenadores`, `professores`, `alunos`, `responsaveis`, `responsavel_aluno`, `solicitacoes_matricula`, `anos_letivos`, `bimestres`, `turmas`, `disciplinas`, `turma_disciplina`, `professor_disciplina`, `professor_turma`, `aluno_turma`, `avaliacoes`, `notas`, `chamadas`, `presencas`, `atividades`, `conteudos_aulas`, `ocorrencias`, `comunicados`, `notificacoes`, `pontos_professores`, `solicitacoes_ajuste_ponto`, `horarios_aulas`, `logs_auditoria`, `configuracoes`, `tentativas_login`, `recuperacoes_acesso`.

Há unicidade de e-mail, matrícula, CPF cadastrado, avaliação/aluno, chamada/aluno e professor/data. As chaves estrangeiras preservam histórico com `RESTRICT`; não há exclusões acadêmicas em cascata.

## Configuração e diagnóstico

Variáveis opcionais: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_DEBUG`. O padrão é a instalação local XAMPP. O modo `APP_DEBUG=true` escreve detalhes no log de erros do PHP, mas não expõe credenciais ou stack trace na página. Erros de banco apresentam mensagem amigável.

Se o portal não abrir, confira Apache/MySQL no painel, a porta utilizada, a pasta `htdocs/escola`, a importação do SQL e o cadastro em `config/database.php`. O Apache deve permitir `.htaccess` (`AllowOverride`, habilitado normalmente no XAMPP). A pasta `storage/sessions` é criada automaticamente e precisa ser gravável pelo PHP.

## Backup e reset

**Backup:** no phpMyAdmin, selecione `colegio_legado` → **Exportar** → formato **SQL** → exporte estrutura e dados de todas as tabelas. Guarde também uma cópia dos arquivos do projeto e da configuração. Para restaurar, importe o backup em uma base vazia.

**ATENÇÃO: `database/reset_database.sql` apaga definitivamente todos os dados acadêmicos, todos os usuários e todo o histórico de auditoria.** Exporte um backup antes. Depois, no phpMyAdmin, selecione `colegio_legado` → **Importar** → escolha esse arquivo → execute. Ele recria somente as três contas iniciais e a configuração institucional. A estrutura das tabelas permanece.

Antes de reabrir o portal após um reset, encerre as sessões: com o Apache parado, apague somente os arquivos `sess_*` dentro de `escola/storage/sessions` e inicie o Apache novamente. Isso impede sessões antigas de coincidirem com IDs recriados.

## Validação da migração

Resultado: **91 verificações de integração PHP/MySQL e 76 telas/estados DOM aprovados**. No Apache, login e logo retornaram HTTP 200; SQL e configuração retornaram HTTP 403. A base da escola permaneceu com três contas e zero dados acadêmicos. O SHA-256 da logo original e da cópia instalada é idêntico.

Os testes usam a base separada `colegio_legado_test`; seus registros de integração não fazem parte do SQL inicial e nunca são inseridos em `colegio_legado`.

Em um terminal PowerShell na pasta do projeto:

```powershell
$env:DB_NAME='colegio_legado_test'
& C:\xampp\php\php.exe database/install.php
& C:\xampp\php\php.exe -S 127.0.0.1:8089 -t .
```

Em outro terminal:

```powershell
$env:DB_NAME='colegio_legado_test'
& C:\xampp\php\php.exe tests/reset.php
& C:\xampp\php\php.exe tests/integration.php
```

O reset de teste recusa qualquer outro nome de banco. Feche o servidor de testes depois de usar. O servidor PHP embutido serve apenas à suíte local; a instalação normal usa Apache, que aplica as proteções `.htaccess`.

O teste DOM opcional `tests/php-interface.cjs` precisa de Node/jsdom apenas na máquina de desenvolvimento. O sistema PHP em si não utiliza essas ferramentas. Testes DOM verificam renderização, navegação e formulários, mas não substituem inspeção visual em navegador real.
