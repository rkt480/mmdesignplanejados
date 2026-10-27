# CRM MM Planejados

Cópia independente do CRM da MM Design, preparada para a operação da MM Planejados. A aplicação está em `crm/` e usa um banco MySQL separado (`mm_planejados_crm`). Leads, usuários, configurações e integrações do CRM original não foram copiados.

## Configuração local

1. Em hospedagens com banco já criado no painel (como Hostinger), selecione o banco no phpMyAdmin e importe `crm/database/schema-hostinger.sql`. Para desenvolvimento local, importe `crm/database/schema.sql`, que cria o banco `mm_planejados_crm`.
2. Edite `crm/config.php` com as credenciais locais ou configure as variáveis de ambiente `MMPLANEJADOS_*` descritas em `crm/config.example.php`.
3. Defina `MMPLANEJADOS_ADMIN_PASSWORD_HASH` com um hash criado no PHP usando `password_hash()`. Não use uma senha em texto aberto no arquivo.
4. Abra `/crm/login.php` no servidor local.

A chave `MMPLANEJADOS_SETTINGS_ENCRYPTION_KEY` deve ser exclusiva para esta instalação. Configure domínio, WhatsApp, e-mail, integrações e canais de contato da política de privacidade da MM Planejados antes de publicar.
