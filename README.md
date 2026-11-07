# CRM MM Planejados

Cópia independente do CRM da MM Design. O mesmo código pode ser usado para testes locais no XAMPP e na Hostinger, com configurações e bancos separados em cada ambiente. O `config.php` real não é publicado no GitHub.

## Teste local no XAMPP

1. Inicie Apache e MySQL no XAMPP.
2. No phpMyAdmin local (`http://localhost/phpmyadmin`), importe `crm/database/schema.sql`. Esse arquivo cria o banco local `mm_planejados_crm`.
3. O `crm/config.php` local usa o MySQL padrão do XAMPP: banco `mm_planejados_crm`, usuário `root` e senha vazia. Se você definiu uma senha para o `root`, ajuste esse campo.
4. Acesse `http://localhost/mmdesignplanejados/crm/login.php`.

## Publicação na Hostinger

1. Envie os arquivos do CRM para a hospedagem.
2. Na hospedagem, crie `crm/config.php` a partir de `crm/config.example.php` pelo Gerenciador de Arquivos ou SFTP. Esse arquivo é ignorado pelo Git e precisa existir separadamente no servidor.
3. No `config.php` da Hostinger, configure o host MySQL indicado no hPanel, o banco `u432013964_mmplanejados`, o usuário `u432013964_mmplanejados` e a senha MySQL definida na hospedagem.
4. No phpMyAdmin da Hostinger, selecione esse banco e importe `crm/database/schema-hostinger.sql`.
5. Defina `admin_password_hash` para a senha do painel. A senha do painel é diferente da senha MySQL.

Os bancos local e da Hostinger são independentes; leads e demais dados de teste não são sincronizados automaticamente com a produção.

## Projetos grandes enviados pelo cliente

Na conversa do cliente, o vendedor clica em **Gerar link de envio** e compartilha o endereço pelo WhatsApp. O cliente abre a página no navegador e envia um arquivo SKP, ZIP, PDF, DWG ou DXF de até 1 GB. O envio ocorre em blocos de 2 MB e pode ser retomado com o mesmo arquivo. O projeto aparece na conversa para download por usuários com acesso ao lead. O link expira em 7 dias e aceita um único arquivo.

Os arquivos ficam em `crm/data/project-uploads/`, protegidos pela regra `crm/data/.htaccess`, e não entram no Git. Confirme que a hospedagem permite gravar nessa pasta e tem espaço disponível. O banco cria a tabela `project_uploads` automaticamente no primeiro acesso após o deploy.

O prazo de exclusão do arquivo começa no primeiro download: 72 horas, limitado a 7 dias depois do envio. A limpeza também roda ao abrir a caixa de conversas. Para garantir a exclusão mesmo sem acessos ao CRM, configure no hPanel uma tarefa cron horária que execute `php /caminho/absoluto/para/crm/run-project-upload-cleanup.php`, substituindo o caminho pelo diretório real da instalação na Hostinger.
