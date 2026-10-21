# Alô Câmara — O que o Back-end precisa construir

Telas de referência: `index.html` (cidadão) e `admin.html` (administrador).

## 1. Tabelas do banco

| Tabela | Campos principais |
|---|---|
| **usuarios** | id, nome, email, senha_hash, telefone, perfil (`cidadao` / `admin`), ativo, criado_em |
| **vereadores** | id, nome, partido, regiao (Centro/Norte/Sul/Leste/Oeste), foto, sobre, email, telefone |
| **avaliacoes** | id, vereador_id, usuario_id, nota (1–5) |
| **protocolos** | id, numero (ex.: AL20240001), tipo, assunto, bairro, endereco, descricao, anexo, latitude, longitude, anonimo, status, usuario_id, vereador_id (opcional), categoria, criado_em |
| **protocolo_historico** | id, protocolo_id, status, resposta, admin_id, criado_em |
| **eventos** (agenda) | id, tipo (Sessão/Comissão/Audiência), titulo, data, hora, local, participantes |
| **notificacoes** | id, usuario_id, titulo, mensagem, tipo (Protocolos/Avisos), lida, criado_em |
| **historia_camara** | id, ano, nome, descricao |

**Valores fixos**
- `tipo` do protocolo: Solicitação, Reclamação, Sugestão, Denúncia, Elogio
- `status`: Recebido → Em Análise → Respondido → Finalizado

## 2. Rotas da API

### Cidadão (`index.html`)
| Método | Rota | Para quê |
|---|---|---|
| POST | `/auth/login` | Login por e-mail/senha |
| POST | `/auth/cadastro` | Criar conta |
| POST | `/auth/google` e `/auth/govbr` | Login social |
| POST | `/protocolos` | Enviar solicitação, reclamação, sugestão, denúncia ou elogio (gera o número) |
| GET | `/protocolos/meus` | Listar os protocolos do usuário logado |
| GET | `/protocolos/{numero}` | Ver um protocolo e seu histórico |
| GET | `/vereadores?regiao=` | Listar/filtrar vereadores |
| GET | `/vereadores/{id}` | Perfil do vereador |
| POST | `/vereadores/{id}/avaliacao` | Avaliar vereador |
| GET | `/agenda?tipo=` | Listar eventos |
| GET | `/historia` | Linha do tempo |
| GET | `/notificacoes` | Listar notificações |
| PATCH | `/notificacoes/{id}/lida` e `/notificacoes/lidas` | Marcar como lida(s) |
| PUT | `/usuarios/me` e `/usuarios/me/senha` | Editar perfil e trocar senha |
| POST | `/upload` | Enviar imagem (PNG/JPG até 10MB) |

### Administrador (`admin.html`) — todas exigem perfil `admin`
| Método | Rota | Para quê |
|---|---|---|
| GET | `/admin/dashboard` | Totais por status, tipo e bairro |
| GET | `/admin/protocolos?status=&tipo=&busca=` | Listar com filtros |
| PATCH | `/admin/protocolos/{id}` | Mudar status, encaminhar e salvar resposta (grava no histórico e cria notificação para o cidadão) |
| POST / PUT / DELETE | `/admin/vereadores` | Gerenciar vereadores |
| POST / PUT / DELETE | `/admin/eventos` | Gerenciar agenda |
| POST | `/admin/comunicados` | Criar notificação para todos ou por bairro |
| GET | `/admin/usuarios` | Listar cidadãos |
| PATCH | `/admin/usuarios/{id}/bloquear` | Bloquear ou desbloquear |

## 3. Regras de negócio

1. **Login com token (JWT)**. Rotas `/admin/*` só funcionam para `perfil = admin`.
2. **Número do protocolo** é gerado automaticamente no formato `AL` + ano + sequência.
3. **Denúncia é sigilosa**: o cidadão não aparece para o vereador; no admin mostra "Anônimo" se `anonimo = true`.
4. **Toda mudança de status** grava uma linha em `protocolo_historico` e gera uma notificação ao cidadão.
5. **Senha** sempre guardada com hash (bcrypt).
6. **Upload** aceita só PNG/JPG até 10MB.
7. **Cidadão só vê os próprios protocolos**.

## 4. Dúvidas para alinhar com o time
- Login com Google e GOV.BR entra na primeira versão?
- Denúncia anônima exige login ou pode ser enviada sem conta?
- Como o admin vê o vereador responsável? Existe perfil separado para vereador?
