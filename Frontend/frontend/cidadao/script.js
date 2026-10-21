const api = criarApi('alo_camara_cidadao');
const $ = (id) => document.getElementById(id);

const PAGINAS_PUBLICAS = ['login'];
const ORDEM_STATUS = ['recebido', 'em_analise', 'respondido', 'finalizado'];

const estado = {
  paginaAtual: 'home',
  protocolos: [],
  vereadores: [],
  regiao: '',
  vereadorAtual: null,   // vereador cujo perfil está aberto
  vereadorAlvo: null,    // vereador vinculado ao protocolo que será enviado
  notificacoes: [],
  filtroNotif: '',
  fotoUrl: null,
  localizacao: null,
};

/* ------------------------------------------------------------------ navegação */

function go(id) {
  if (!PAGINAS_PUBLICAS.includes(id) && !api.token()) id = 'login';

  document.querySelectorAll('.page').forEach((s) => { s.classList.remove('active'); s.style.display = 'none'; });
  const el = $(id);
  el.classList.add('active');
  el.style.display = 'block';
  window.scrollTo({ top: 0, behavior: 'instant' });
  document.querySelectorAll('.navlink').forEach((n) => n.classList.remove('active'));
  const navMatch = document.querySelector('.navlink[data-nav="' + id + '"]');
  if (navMatch) navMatch.classList.add('active');

  estado.paginaAtual = id;
  $('topo').style.display = id === 'login' ? 'none' : '';
  carregarPagina(id);
}

function carregarPagina(id) {
  const carregadores = {
    home: atualizarCabecalho,
    protocolos: carregarProtocolos,
    minhas: carregarProtocolos,
    vereadores: carregarVereadores,
    agenda: carregarAgenda,
    notif: carregarNotificacoes,
    historia: carregarHistoria,
    config: carregarConfig,
    elogio: carregarVereadoresNoSelect,
  };
  if (carregadores[id]) carregadores[id]().catch((e) => console.error(e));
}

function mostrarErro(container, mensagem) {
  const alvo = container.querySelector ? container.querySelector('.msg-erro') || container : container;
  alvo.textContent = mensagem;
  alvo.style.display = mensagem ? 'block' : 'none';
}

function vazio(texto) { return '<p class="vazio">' + esc(texto) + '</p>'; }

/* ---------------------------------------------------------------- autenticação */

function alternarCadastro(mostrar) {
  $('form-login').style.display = mostrar ? 'none' : '';
  $('form-cadastro').style.display = mostrar ? '' : 'none';
  mostrarErro($('login-erro'), '');
}

async function iniciarSessao(dados) {
  api.salvarSessao(dados);
  if (dados.usuario.perfil === 'admin') {
    api.sair();
    throw new Error('Esta conta é da equipe da Câmara. Use o painel do administrador.');
  }
  atualizarCabecalho();
  go('home');
}

async function entrar(ev) {
  ev.preventDefault();
  mostrarErro($('login-erro'), '');
  try {
    const dados = await api.post('/auth/login', { email: $('login-email').value, senha: $('login-senha').value });
    $('login-senha').value = '';
    await iniciarSessao(dados);
  } catch (e) { mostrarErro($('login-erro'), e.message); }
}

async function cadastrar(ev) {
  ev.preventDefault();
  mostrarErro($('login-erro'), '');
  try {
    const dados = await api.post('/auth/cadastro', {
      nome: $('cad-nome').value, email: $('cad-email').value,
      senha: $('cad-senha').value, bairro: $('cad-bairro').value,
    });
    $('cad-senha').value = '';
    await iniciarSessao(dados);
  } catch (e) { mostrarErro($('login-erro'), e.message); }
}

async function loginSocial(provedor) {
  try { await api.post('/auth/' + provedor, {}); } catch (e) { mostrarErro($('login-erro'), e.message); }
}

function sair() {
  api.sair();
  estado.protocolos = []; estado.notificacoes = [];
  go('login');
}

api.aoExpirarSessao = () => { go('login'); mostrarErro($('login-erro'), 'Sua sessão expirou. Entre novamente.'); };

async function atualizarCabecalho() {
  const u = api.usuario();
  if (!u) return;
  $('avatar-mini').textContent = (u.nome || '?').charAt(0).toUpperCase();
  $('home-nome').textContent = (u.nome || 'Cidadão').split(' ')[0];
  try {
    const { notificacoes } = await api.get('/notificacoes');
    const naoLidas = notificacoes.filter((n) => !n.lida).length;
    $('notif-count').textContent = naoLidas;
    $('notif-count').style.display = naoLidas ? '' : 'none';
  } catch (e) { /* contador é opcional */ }
}

/* ----------------------------------------------------------------- protocolos */

function novoProtocolo(tipo, comVereador) {
  estado.vereadorAlvo = comVereador ? estado.vereadorAtual : null;
  estado.fotoUrl = null; estado.localizacao = null;
  document.querySelectorAll('form[data-tipo]').forEach((f) => f.reset());
  $('upload-titulo').textContent = 'Anexar Imagem (opcional)';
  $('gps-switch').classList.remove('on');
  $('gps-sub').textContent = 'Automático via GPS';
  document.querySelectorAll('.vereador-alvo').forEach((p) => {
    p.style.display = estado.vereadorAlvo ? 'block' : 'none';
    p.textContent = estado.vereadorAlvo ? 'Para o vereador: ' + estado.vereadorAlvo.nome : '';
  });
  go(tipo);
}

async function enviarProtocolo(ev) {
  ev.preventDefault();
  const form = ev.target;
  const botao = form.querySelector('button[type=submit]');
  mostrarErro(form, '');

  const dados = Object.fromEntries(new FormData(form).entries());
  dados.tipo = form.dataset.tipo;
  dados.vereador_id = dados.vereador_id || (estado.vereadorAlvo ? estado.vereadorAlvo.id : null);
  if (dados.tipo === 'elogio') {
    dados.foto_url = estado.fotoUrl;
    dados.localizacao = estado.localizacao;
  }

  botao.disabled = true;
  try {
    const { protocolo } = await api.post('/protocolos', dados);
    alert('Enviado com sucesso! Seu número de protocolo é ' + protocolo.numero);
    go('minhas');
  } catch (e) { mostrarErro(form, e.message); }
  botao.disabled = false;
}

async function escolherImagem(input) {
  const arquivo = input.files[0];
  if (!arquivo) return;
  const form = input.closest('form');
  mostrarErro(form, '');
  try {
    const corpo = new FormData();
    corpo.append('arquivo', arquivo);
    const { url } = await api.req('POST', '/upload', corpo);
    estado.fotoUrl = url;
    $('upload-titulo').textContent = 'Imagem anexada: ' + arquivo.name;
  } catch (e) {
    estado.fotoUrl = null;
    input.value = '';
    mostrarErro(form, e.message);
  }
}

function alternarGps(botao) {
  const ligando = !botao.classList.contains('on');
  if (!ligando) {
    botao.classList.remove('on'); estado.localizacao = null; $('gps-sub').textContent = 'Automático via GPS';
    return;
  }
  if (!navigator.geolocation) { $('gps-sub').textContent = 'GPS indisponível neste navegador'; return; }
  $('gps-sub').textContent = 'Obtendo localização...';
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      estado.localizacao = pos.coords.latitude.toFixed(6) + ',' + pos.coords.longitude.toFixed(6);
      botao.classList.add('on');
      $('gps-sub').textContent = 'Localização anexada';
    },
    () => { $('gps-sub').textContent = 'Não foi possível obter a localização'; },
  );
}

async function carregarProtocolos() {
  const { protocolos } = await api.get('/protocolos/meus');
  estado.protocolos = protocolos;
  renderProtocolos();
}

function classeBadge(status) { return 'badge st-' + status; }

function renderProtocolos() {
  const lista = estado.protocolos;
  const conta = (s) => lista.filter((p) => p.status === s).length;
  const stats = '<div class="stat"><b>' + lista.length + '</b><span>Total</span></div>'
    + '<div class="stat"><b style="color:var(--blue);">' + (conta('recebido') + conta('em_analise')) + '</b><span>Em análise</span></div>'
    + '<div class="stat"><b style="color:var(--orange);">' + conta('respondido') + '</b><span>Respondidos</span></div>'
    + '<div class="stat"><b style="color:var(--green);">' + conta('finalizado') + '</b><span>Finalizados</span></div>';
  $('stats-protocolos').innerHTML = stats;
  $('stats-minhas').innerHTML = stats;

  const q = ($('busca-protocolos').value || '').toLowerCase();
  const filtrada = lista.filter((p) => (p.numero + ' ' + p.titulo).toLowerCase().includes(q));

  $('lista-protocolos').innerHTML = filtrada.length ? filtrada.map((p) => `
    <div class="item-card">
      <div class="id">${esc(p.numero)}</div><div class="cat">${esc(ROTULO_TIPO[p.tipo])}</div>
      <div class="title">${esc(p.titulo)}</div>
      <span class="${classeBadge(p.status)}">${esc(ROTULO_STATUS[p.status])}</span>
      <div class="meta"><span>${esc(p.bairro || '')}</span><span>${formatarData(p.criado_em)}</span></div>
      <div class="desc">${esc(p.resposta ? 'Resposta: ' + p.resposta : p.descricao)}</div>
    </div>`).join('') : vazio(lista.length ? 'Nenhum protocolo encontrado para a busca.' : 'Você ainda não abriu nenhum protocolo.');

  $('lista-minhas').innerHTML = lista.length ? lista.map((p) => {
    const atual = ORDEM_STATUS.indexOf(p.status);
    const passos = ORDEM_STATUS.map((s, i) =>
      `<div class="step-row ${i <= atual ? 'done' : ''}"><span class="step-dot"></span>${ROTULO_STATUS[s]}</div>`).join('');
    return `
    <div class="item-card">
      <div class="id">${esc(p.numero)} • ${esc(ROTULO_TIPO[p.tipo])}</div>
      <div class="title">${esc(p.titulo)}</div>
      <div class="meta"><span>${esc(p.bairro || '')}</span><span>${formatarData(p.criado_em)}</span></div>
      <div class="steps">${passos}</div>
      <span class="${classeBadge(p.status)}">${esc(ROTULO_STATUS[p.status])}</span>
      ${p.resposta ? '<div class="desc"><b>Resposta da Câmara:</b> ' + esc(p.resposta) + '</div>' : ''}
    </div>`;
  }).join('') : vazio('Você ainda não abriu nenhum protocolo.');
}

/* ----------------------------------------------------------------- vereadores */

const CORES_AVATAR = ['#7c4dea', '#4b5aa8', '#3d6bf0', '#2c8f6f', '#b0509a'];

async function carregarVereadores() {
  const query = estado.regiao ? '?regiao=' + encodeURIComponent(estado.regiao) : '';
  const { vereadores } = await api.get('/vereadores' + query);
  estado.vereadores = vereadores;
  renderVereadores();
}

function renderVereadores() {
  const q = ($('busca-vereadores').value || '').toLowerCase();
  const lista = estado.vereadores.filter((v) => v.nome.toLowerCase().includes(q));
  $('grid-vereadores').innerHTML = lista.length ? lista.map((v, i) => `
    <div class="vereador-card" onclick="abrirVereador(${v.id})">
      <div class="av" style="background:${CORES_AVATAR[i % CORES_AVATAR.length]};">${esc(v.nome.charAt(0))}</div>
      <strong>${esc(v.nome)}</strong><small>${esc(v.partido || '')} — ${esc(v.regiao || '')}</small>
      <small>⭐ ${Number(v.nota_media).toFixed(1)} (${v.total_avaliacoes})</small>
    </div>`).join('') : vazio('Nenhum vereador encontrado.');
}

async function carregarVereadoresNoSelect() {
  const { vereadores } = await api.get('/vereadores');
  const select = $('elogio-vereador');
  const escolhido = estado.vereadorAlvo ? String(estado.vereadorAlvo.id) : '';
  select.innerHTML = '<option value="">Selecione</option>' + vereadores.map((v) =>
    `<option value="${v.id}" ${String(v.id) === escolhido ? 'selected' : ''}>${esc(v.nome)}</option>`).join('');
}

function preencherVereador(v) {
  $('ver-nome').textContent = $('ver-nome2').textContent = v.nome;
  $('ver-sub').textContent = $('ver-sub2').textContent = (v.partido || '') + ' · ' + (v.regiao || '');
  $('ver-inicial').textContent = v.nome.charAt(0);
  $('ver-nota').textContent = '⭐' + Number(v.nota_media).toFixed(1);
  $('ver-aval').textContent = v.total_avaliacoes;
  $('ver-dem').textContent = v.total_demandas;
  $('ver-taxa').textContent = Number(v.taxa_resposta).toFixed(0) + '% das demandas recebem retorno.';
  $('ver-bio').textContent = v.bio || 'Sem informações cadastradas.';
  $('ver-contato').innerHTML = esc(v.email || 'E-mail não informado') + '<br>' + esc(v.telefone || 'Telefone não informado');
  $('ver-estrelas').innerHTML = [1, 2, 3, 4, 5].map((n) =>
    `<span class="estrela" onclick="avaliarVereador(${v.id}, ${n})" title="${n}">★</span>`).join('');
}

async function abrirVereador(id) {
  const { vereador } = await api.get('/vereadores/' + id);
  estado.vereadorAtual = vereador;
  preencherVereador(vereador);
  go('vereador');
}

async function avaliarVereador(id, nota) {
  try {
    const { vereador } = await api.post('/vereadores/' + id + '/avaliacao', { nota });
    estado.vereadorAtual = vereador;
    preencherVereador(vereador);
    alert('Obrigado pela avaliação!');
  } catch (e) { alert(e.message); }
}

document.querySelectorAll('#filtro-regiao .chip').forEach((chip) => {
  chip.onclick = () => {
    document.querySelectorAll('#filtro-regiao .chip').forEach((c) => c.classList.remove('active'));
    chip.classList.add('active');
    estado.regiao = chip.dataset.regiao;
    carregarVereadores().catch((e) => console.error(e));
  };
});

/* --------------------------------------------------------------------- agenda */

async function carregarAgenda(tipo) {
  if (typeof tipo !== 'string') tipo = (document.querySelector('#filtro-agenda .chip.active') || {}).dataset?.tipo || '';
  const { eventos } = await api.get('/agenda' + (tipo ? '?tipo=' + tipo : ''));
  const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
  $('lista-agenda').innerHTML = eventos.length ? eventos.map((e) => {
    const d = new Date(e.data.replace(' ', 'T'));
    return `
    <div class="event-card">
      <div class="event-date"><span class="d">${d.getDate()}</span><span>${meses[d.getMonth()]}</span></div>
      <div class="event-body"><span class="tag">${esc(ROTULO_EVENTO[e.tipo] || e.tipo)}</span><h4>${esc(e.titulo)}</h4>
        <span class="row">🕐 ${formatarHora(e.data)}</span><span class="row">📍 ${esc(e.local || 'Local a definir')}</span>
        <span class="row">👤 ${e.participantes.length} participantes</span>
        ${e.descricao ? '<p style="color:var(--muted);font-size:13px;margin-top:8px;">' + esc(e.descricao) + '</p>' : ''}</div>
    </div>`;
  }).join('') : vazio('Nenhum evento agendado.');
}

document.querySelectorAll('#filtro-agenda .chip').forEach((chip) => {
  chip.onclick = () => {
    document.querySelectorAll('#filtro-agenda .chip').forEach((c) => c.classList.remove('active'));
    chip.classList.add('active');
    carregarAgenda(chip.dataset.tipo).catch((e) => console.error(e));
  };
});

/* -------------------------------------------------------------- notificações */

async function carregarNotificacoes() {
  const { notificacoes } = await api.get('/notificacoes');
  estado.notificacoes = notificacoes;
  renderNotificacoes();
  const naoLidas = notificacoes.filter((n) => !n.lida).length;
  $('notif-count').textContent = naoLidas;
  $('notif-count').style.display = naoLidas ? '' : 'none';
}

function renderNotificacoes() {
  const lista = estado.notificacoes.filter((n) => !estado.filtroNotif || n.tipo === estado.filtroNotif);
  $('lista-notif').innerHTML = lista.length ? lista.map((n) => `
    <div class="notif-item ${n.tipo === 'Avisos' ? 'warn' : ''}" onclick="lerNotificacao(${n.id})">
      <h4>${esc(n.titulo)} ${n.lida ? '' : '<span class="new-tag">Nova</span>'}</h4>
      <p>${esc(n.mensagem)}</p>
      <div class="foot"><span>${tempoRelativo(n.criada_em)}</span>
        <span style="color:${n.tipo === 'Avisos' ? 'var(--orange)' : 'var(--blue)'};">${esc(n.tipo)}</span></div>
    </div>`).join('') : vazio('Nenhuma notificação por aqui.');
}

async function lerNotificacao(id) {
  const n = estado.notificacoes.find((x) => x.id === id);
  if (!n || n.lida) return;
  try { await api.patch('/notificacoes/' + id + '/lida'); await carregarNotificacoes(); } catch (e) { alert(e.message); }
}

async function marcarTodasLidas() {
  try { await api.patch('/notificacoes/lidas'); await carregarNotificacoes(); } catch (e) { alert(e.message); }
}

document.querySelectorAll('#filtro-notif .chip').forEach((chip) => {
  chip.onclick = () => {
    document.querySelectorAll('#filtro-notif .chip').forEach((c) => c.classList.remove('active'));
    chip.classList.add('active');
    estado.filtroNotif = chip.dataset.tipo;
    renderNotificacoes();
  };
});

/* ------------------------------------------------------------------- história */

async function carregarHistoria() {
  const { historia } = await api.get('/historia');
  $('linha-historia').innerHTML = historia.length ? historia.map((h, i) => `
    <div class="timeline-item"><div class="dot-col"><div class="dt"></div>${i < historia.length - 1 ? '<div class="ln"></div>' : ''}</div>
      <div><div class="yr">${esc(h.ano)}</div><div class="nm">${esc(h.nome)}</div><div class="ds">${esc(h.descricao || '')}</div></div></div>
  `).join('') : vazio('Nenhum registro cadastrado.');
}

/* --------------------------------------------------------------- configurações */

async function carregarConfig() {
  const { usuario } = await api.get('/usuarios/me');
  api.salvarSessao({ ...api.sessao(), usuario });
  $('cfg-inicial').textContent = usuario.nome.charAt(0).toUpperCase();
  $('cfg-nome').textContent = usuario.nome;
  $('cfg-email').textContent = usuario.email;

  const perfil = $('painel-perfil');
  perfil.nome.value = usuario.nome;
  perfil.telefone.value = usuario.telefone || '';
  perfil.bairro.value = usuario.bairro || '';

  const { preferencias } = await api.get('/usuarios/me/preferencias');
  [['pref-push', 'notificar_push'], ['pref-email', 'notificar_email'],
   ['pref-status', 'notificar_mudanca_status'], ['pref-avisos', 'notificar_avisos_gerais']]
    .forEach(([id, campo]) => $(id).classList.toggle('on', !!preferencias[campo]));
}

function alternarPainel(id) {
  const p = $(id);
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
}

async function salvarPerfil(ev) {
  ev.preventDefault();
  const form = ev.target;
  mostrarErro(form, '');
  try {
    const { usuario } = await api.put('/usuarios/me', Object.fromEntries(new FormData(form).entries()));
    api.salvarSessao({ ...api.sessao(), usuario });
    form.style.display = 'none';
    await carregarConfig();
    atualizarCabecalho();
  } catch (e) { mostrarErro(form, e.message); }
}

async function salvarSenha(ev) {
  ev.preventDefault();
  const form = ev.target;
  mostrarErro(form, '');
  try {
    await api.put('/usuarios/me/senha', Object.fromEntries(new FormData(form).entries()));
    form.reset();
    form.style.display = 'none';
    alert('Senha alterada com sucesso.');
  } catch (e) { mostrarErro(form, e.message); }
}

async function alternarPreferencia(botao, campo) {
  const novo = !botao.classList.contains('on');
  botao.classList.toggle('on', novo);
  try { await api.put('/usuarios/me/preferencias', { [campo]: novo }); } catch (e) {
    botao.classList.toggle('on', !novo);
    alert(e.message);
  }
}

/* ---------------------------------------------------------------------- início */

if (api.token()) { go('home'); } else { go('login'); }
