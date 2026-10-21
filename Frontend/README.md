# Alô Câmara

Plataforma para o cidadão enviar solicitações, reclamações, sugestões, denúncias e elogios à Câmara de Vereadores, e para a equipe da Câmara acompanhar e responder tudo em um painel.

## Estrutura

```
alo-camara/
├── docs/
│   └── BACKEND.md          # tabelas, rotas da API e regras para o back-end
└── frontend/
    ├── cidadao/            # site do cidadão
    │   ├── index.html
    │   ├── style.css
    │   └── script.js
    └── admin/              # painel do administrador
        ├── admin.html
        ├── admin.css
        └── admin.js
```

## Como abrir

Baixe o projeto e abra no navegador:

- Site do cidadão: `frontend/cidadao/index.html`
- Painel do administrador: `frontend/admin/admin.html` (o login já vem preenchido; basta clicar em Entrar)

## Status

- [x] Protótipo do site do cidadão (tema claro)
- [x] Protótipo do painel do administrador
- [x] Documento com o que o back-end deve construir
- [ ] Integração com a API do back-end
