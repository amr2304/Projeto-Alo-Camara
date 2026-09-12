# Sistema de Relacionamento Cidadão–Vereador

Plataforma para que cidadãos acompanhem vereadores, abram protocolos (solicitações, reclamações, sugestões, denúncias e elogios) e recebam notificações sobre o andamento de suas demandas, além de consultar agenda de sessões, comissões e audiências.

## Entidades principais

- **Usuario (cidadão)** — login, cadastro, perfil, configurações
- **Vereador** — perfil público, avaliações, taxa de resposta
- **Protocolo** — tipo (Solicitação, Reclamação, Sugestão, Denúncia, Elogio), status (Análise / Andamento / Respondido / Resolvido), etapas (steps)
- **Evento/Agenda** — Sessão, Comissão, Audiência
- **Notificacao** — vinculada a protocolos ou a avisos gerais

## Estrutura do projeto

O desenvolvimento está dividido em três partes, para permitir trabalho isolado de cada módulo (via Postman/Insomnia) antes da integração final.

### Parte 1 — Usuários & Autenticação

- Model/DAO/Service de `Usuario` (cidadão) e dados básicos de `Vereador`
- Login (email/senha e, futuramente, OAuth Google/GOV.BR — ao menos como stub)
- Cadastro, edição de perfil, alteração de senha
- Preferências de notificação (push/e-mail) e configurações de conta

**API:** `/usuarios`

### Parte 2 — Protocolos (núcleo do sistema)

- Model `Protocolo` com tipo (enum: solicitação/reclamação/sugestão/denúncia/elogio) e status
- DAO: CRUD completo + geração de número de protocolo (ex: `AL2024000X`)
- Service: máquina de estados do status (Recebido → Em Análise → Respondido → Finalizado), com regras por tipo:
  - Denúncia é sigilosa
  - Elogio pode anexar imagem/geolocalização
- Endpoints de listagem/filtro por status, bairro e tipo — usados nas telas "Protocolos" e "Minhas Solicitações"

**API:** `/protocolos`

### Parte 3 — Vereadores, Agenda & Notificações

- Model `Vereador` completo (partido, região, avaliações, taxa de resposta, biografia)
- Model `Evento` (sessão/comissão/audiência) com data, local e participantes
- Model `Notificacao` — gerada automaticamente quando um protocolo muda de status (integração com a Parte 2) ou manualmente, para avisos gerais

**APIs:** `/vereadores`, `/agenda`, `/notificacoes`

## Integração entre as partes

- A **Parte 2 (Protocolos)** referencia `Usuario` (quem abriu o protocolo) e `Vereador` (opcional, quando vinculado)
- A **Parte 3 (Notificações)** escuta mudanças de status vindas da Parte 2 para notificar o cidadão
- Cada módulo deve ser testável isoladamente (Postman/Insomnia) antes da integração completa

## APIs REST (visão geral)

| Módulo | Rota base |
|---|---|
| Usuários | `/usuarios` |
| Protocolos | `/protocolos` |
| Vereadores | `/vereadores` |
| Agenda | `/agenda` |
| Notificações | `/notificacoes` |
