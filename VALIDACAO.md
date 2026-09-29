# Validação — migração PHP/MySQL

Executada em 22/09/2026, com PHP 8.2.12 e MySQL/MariaDB do XAMPP.

- 91 verificações HTTP/PDO aprovadas em `tests/integration.php`.
- 76 telas e estados do DOM aprovados em `tests/php-interface.cjs`, utilizando a API PHP real.
- Sintaxe PHP e JavaScript conferida.
- Login dos perfis administrativos e docente; criação e acesso real de aluno e responsável após matrícula.
- CSRF obrigatório, acesso direto às páginas PHP protegido, aluno pendente bloqueado, isolamento entre turmas e famílias.
- Aprovação duplicada impedida; aprovação com falha desfaz aluno e vínculos.
- Nota acima do máximo rejeitada; lotes inválidos de notas/chamada desfeitos integralmente.
- Notas e faltas conferidas diretamente no MySQL e no boletim do aluno.
- Ponto com entrada/saída, prevenção de duplicidade e ajuste aprovado pela direção.
- Persistência em uma nova sessão; logout revoga o acesso.
- Inativação preserva os vínculos históricos.
- Banco `colegio_legado`: 32 tabelas InnoDB/utf8mb4_unicode_ci, três usuários e zero registros acadêmicos iniciais.
- Testes foram isolados em `colegio_legado_test`.
- Apache: `/colegio-legado/login.php` e a logo retornam 200; SQL e configuração retornam 403.
- Caminho base verificado como `/colegio-legado/`, incluindo os arquivos JavaScript.
- LocalStorage encontrado somente em tema e sidebar; nenhum sessionStorage acadêmico.
- Logo oficial preservada: SHA-256 `DBF0526F437A419AFD28DBD5779A671E1C34602E6B103E95ED133B4E10CF3612`.

Os testes DOM não constituem inspeção visual em navegador real. Não foi realizada publicação em servidor externo.
