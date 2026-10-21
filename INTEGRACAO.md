# Alô Câmara — Backend + Frontend integrados

## Como rodar

1. **Banco (MySQL/MariaDB)** — na pasta `Backend/database`, nesta ordem:
   ```bash
   mysql -u root < schema.sql
   mysql -u root alo_camara < migration_preferencias_conta.sql
   mysql -u root alo_camara < migration_protocolos.sql
   mysql -u root alo_camara < migration_partes_2_3.sql
   mysql -u root alo_camara < migration_integracao_frontend.sql
   ```
2. **Administrador** — na pasta `Backend`: `php database/seed_admin.php`
   (padrão `admin@camara.gov.br` / `123456`; passe e-mail e senha como argumentos para trocar).
3. **API** — na pasta `Backend`: `php -S localhost:8000 index.php`
4. **Frontend** — na pasta `Frontend/frontend`: `php -S localhost:8080`
   - Cidadão: http://localhost:8080/cidadao/index.html
   - Admin: http://localhost:8080/admin/admin.html

Para apontar o frontend para outro endereço de API, defina `window.ALO_API_URL` antes de carregar `api.js`.
Variáveis de ambiente do backend: `ALO_DB_HOST`, `ALO_DB_NAME`, `ALO_DB_USER`, `ALO_DB_PASS` e `ALO_JWT_SECRET`
(**defina o segredo em qualquer ambiente fora do seu computador**).

## O que mudou

**Backend**
- Autenticação por token (`Authorization: Bearer`), perfis `cidadao`/`admin` (`utils/Auth.php`).
- Novas rotas do `docs/BACKEND.md` em `controllers/ApiController.php`: `/auth/*`, `/usuarios/me*`,
  `/protocolos/meus`, `/protocolos/{numero}`, `/vereadores/{id}/avaliacao`, `/historia`, `/upload`,
  `/notificacoes/lidas`, `/notificacoes/{id}/lida` e `/admin/*`.
- O cidadão passa a ser sempre o dono do token: `usuario_id` enviado pelo cliente é ignorado.
- Rotas antigas que aceitavam `id` do corpo sem login agora exigem token (e admin onde cabe).
- Protocolos ganham `endereco`, `categoria` e histórico (`protocolo_historico`); o admin pode avançar
  o status em qualquer etapa (não só a próxima), registrando resposta e encaminhamento.
- CORS e `OPTIONS` tratados em `index.php`.

**Frontend**
- `api.js` (cliente compartilhado), `cidadao/` e `admin/` agora leem e gravam na API em vez de dados fixos.

## Pendências conhecidas
- **Login Google/GOV.BR** responde 501 (o stub antigo aceitava qualquer e-mail sem validar o provedor).
- Valores `tipo`/`status` trafegam como códigos (`em_analise`, `solicitacao`); o frontend traduz para rótulos.
- Não há bloqueio de tentativas de login nem recuperação de senha.
- Removi do site do cidadão o "Modo Escuro" e o botão "Solicitar Reunião com Gabinete", que eram só visuais
  e não têm rota no backend nem no `BACKEND.md`.
