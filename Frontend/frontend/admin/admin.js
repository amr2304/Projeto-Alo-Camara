const api = criarApi('alo_camara_admin');
const $ = (id) => document.getElementById(id);

const estado = { protocolos: [], vereadores: [], eventos: [], usuarios: [] };
const ORDEM_STATUS = ['recebido', 'em_analise', 'respondido', 'finalizado'];
const ROTULO_BADGE = { recebido: 'b-recebido', em_analise: 'b-analise', respondido: 'b-respondido', finalizado: 'b-finalizado' };

const badge = (s) => `<span class="badge ${ROTULO_BADGE[s]}">${esc(ROTULO_STATUS[s])}</span>`;

function toast(t) {
  const e = $('ts');
  e.textContent = t; e.style.display = 'block';
  clearTimeout(toast.t);
  toast.t = setTimeout(() => (e.style.display = 'none'), 2600);
}

function fecharModal() { $('md').classList.remove('on'); }
function abrirModal(html) { $('mc').innerHTML = html; $('md').classList.add('on'); }
$('md').onclick = (e) => { if (e.target.id === 'md') fecharModal(); };

/* ---------------------------------------------------------------- sessão */

function mostrarPainel() {
  $('login').style.display = 'none';
  $('app').style.display = 'flex';
  carregarTudo();
}

function mostrarLogin(mensagem) {
  $('app').style.display = 'none';
  $('login').style.display = 'flex';
  const erro = $('login-erro');
  erro.textContent = mensagem || '';
  erro.style.display = mensagem ? 'block' : 'none';
}

async function entrar(ev) {
  ev.preventDefault();
  try {
    const dados = await api.post('/auth/login', { email: $('em').value, senha: $('pw').value });
    if (dados.usuario.perfil !== 'admin') throw new Error('Acesso restrito à equipe da Câmara.');
    api.salvarSessao(dados);
    $('pw').value = '';
    mostrarPainel();
  } catch (e) { mostrarLogin(e.message); }
}

function sair() { api.sair(); mostrarLogin(''); }

api.aoExpirarSessao = () => mostrarLogin('Sua sessão expirou. Entre novamente.');

document.querySelectorAll('.nav[data-v]').forEach((b) => b.onclick = () => {
  document.querySelectorAll('.nav').forEach((n) => n.classList.remove('on'));
  b.classList.add('on');
  document.querySelectorAll('.view').forEach((v) => v.classList.remove('on'));
  $(b.dataset.v).classList.add('on');
  carregarTudo();
});

function carregarTudo() {
  const visivel = (document.querySelector('.view.on') || {}).id;
  const carregadores = {
    dash: carregarDashboard, prot: carregarProtocolos, ver: carregarVereadores,
    ag: carregarEventos, usr: carregarUsuarios,
  };
  if (carregadores[visivel]) carregadores[visivel]().catch((e) => toast(e.message));
}

/* ------------------------------------------------------------- dashboard */

function barras(mapa, rotulos) {
  const entradas = Object.entries(mapa);
  if (!entradas.length) return '<p class="meta">Sem dados ainda.</p>';
  const max = Math.max(...entradas.map(([, n]) => n));
  return entradas.map(([k, n]) =>
    `<div class="bar"><span>${esc((rotulos && rotulos[k]) || k)}</span><i style="width:${n / max * 100}%"></i><span>${n}</span></div>`).join('');
}

async function carregarDashboard() {
  const { dashboard: d } = await api.get('/admin/dashboard');
  const s = d.por_status;
  const n = (k) => s[k] || 0;
  $('stats').innerHTML = [
    [d.total, 'Total'], [n('recebido') + n('em_analise'), 'Aguardando ação'],
    [n('respondido'), 'Respondidos'], [n('finalizado'), 'Finalizados'],
  ].map(([a, b]) => `<div class="stat"><b>${a}</b><span>${b}</span></div>`).join('');
  $('byType').innerHTML = barras(d.por_tipo, ROTULO_TIPO);
  $('byBairro').innerHTML = barras(d.por_bairro);
}

/* ------------------------------------------------------------ protocolos */

async function carregarProtocolos() {
  const params = new URLSearchParams();
  if ($('fs').value) params.set('status', $('fs').value);
  if ($('ft').value) params.set('tipo', $('ft').value);
  if ($('q').value.trim()) params.set('busca', $('q').value.trim());
  const { protocolos } = await api.get('/admin/protocolos?' + params);
  estado.protocolos = protocolos;
  $('tb').innerHTML = protocolos.length ? protocolos.map((p) => `
    <tr class="row" onclick="abrirProtocolo(${p.id})"><td><b style="color:var(--blue)">${esc(p.numero)}</b></td>
    <td>${esc(ROTULO_TIPO[p.tipo])}</td><td>${esc(p.titulo)}</td><td>${esc(p.bairro || '')}</td>
    <td>${formatarData(p.criado_em)}</td><td>${badge(p.status)}</td></tr>`).join('')
    : '<tr><td colspan="6">Nenhum protocolo encontrado com esses filtros.</td></tr>';
}

let timerBusca;
function buscarProtocolos() {
  clearTimeout(timerBusca);
  timerBusca = setTimeout(() => carregarProtocolos().catch((e) => toast(e.message)), 300);
}

async function abrirProtocolo(id) {
  let dados;
  try { dados = await api.get('/admin/protocolos/' + id); } catch (e) { return toast(e.message); }
  const p = dados.protocolo;
  const linha = estado.protocolos.find((x) => x.id === id) || {};
  const atual = ORDEM_STATUS.indexOf(p.status);
  const opcoes = ORDEM_STATUS.map((s, i) =>
    `<option value="${s}" ${s === p.status ? 'selected' : ''} ${i < atual ? 'disabled' : ''}>${ROTULO_STATUS[s]}</option>`).join('');
  const hist = (dados.historico || []).map((h) =>
    `<li>${formatarData(h.criado_em)} ${formatarHora(h.criado_em)} — ${esc(ROTULO_STATUS[h.status])}${h.encaminhado_para ? ' · encaminhado para ' + esc(h.encaminhado_para) : ''}</li>`).join('');

  abrirModal(`<h3>${esc(p.numero)} · ${esc(ROTULO_TIPO[p.tipo])}</h3>
    <p class="meta">${esc(linha.cidadao || (p.sigiloso ? 'Anônimo' : ''))} · ${esc(p.bairro || '')} · ${formatarData(p.criado_em)}</p>
    <p><b>${esc(p.titulo)}</b></p><p class="meta">${esc(p.descricao)}</p>
    ${p.endereco ? '<p class="meta">📍 ' + esc(p.endereco) + '</p>' : ''}
    ${p.foto_url ? '<p><a href="' + esc(api.urlArquivo(p.foto_url)) + '" target="_blank" rel="noopener">Ver imagem anexada</a></p>' : ''}
    <ul class="hist">${hist}</ul>
    <div class="f"><label>Status</label><select id="ns">${opcoes}</select></div>
    <div class="f"><label>Encaminhar para</label><select id="enc"><option value="">Não encaminhar</option><option>Secretaria de Obras</option><option>Gabinete da vereadora Maria</option><option>Ouvidoria</option></select></div>
    <div class="f"><label>Resposta ao cidadão</label><textarea id="resp" placeholder="Escreva o retorno que o cidadão vai receber">${esc(p.resposta || '')}</textarea></div>
    <p class="erro" id="md-erro" style="display:none"></p>
    <div class="actions"><button class="btn ghost" onclick="fecharModal()">Cancelar</button><button class="btn" onclick="salvarProtocolo(${p.id})">Salvar alterações</button></div>`);
}

async function salvarProtocolo(id) {
  try {
    await api.patch('/admin/protocolos/' + id, {
      status: $('ns').value, resposta: $('resp').value, encaminhar_para: $('enc').value,
    });
    fecharModal();
    toast('Alterações salvas');
    await carregarProtocolos();
  } catch (e) { erroModal(e.message); }
}

function erroModal(msg) { const e = $('md-erro'); e.textContent = msg; e.style.display = 'block'; }

/* ------------------------------------------------------------ vereadores */

async function carregarVereadores() {
  const { vereadores } = await api.get('/vereadores');
  estado.vereadores = vereadores;
  $('tv').innerHTML = vereadores.length ? vereadores.map((v) => `
    <tr><td>${esc(v.nome)}</td><td>${esc(v.partido || '')}</td><td>${esc(v.regiao || '')}</td>
    <td>⭐ ${Number(v.nota_media).toFixed(1)}</td>
    <td><button class="btn ghost" onclick="formVereador(${v.id})">Editar</button>
        <button class="btn ghost" onclick="excluirVereador(${v.id})">Excluir</button></td></tr>`).join('')
    : '<tr><td colspan="5">Nenhum vereador cadastrado.</td></tr>';
}

function formVereador(id) {
  const v = estado.vereadores.find((x) => x.id === id) || {};
  const regioes = ['Centro', 'Norte', 'Sul', 'Leste', 'Oeste'];
  abrirModal(`<h3>${id ? 'Editar' : 'Novo'} vereador</h3>
    <div class="f"><label>Nome</label><input id="v-nome" value="${esc(v.nome || '')}"></div>
    <div class="f"><label>Partido</label><input id="v-partido" value="${esc(v.partido || '')}"></div>
    <div class="f"><label>Região</label><select id="v-regiao">${regioes.map((r) => `<option ${r === v.regiao ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
    <div class="f"><label>Sobre</label><textarea id="v-bio">${esc(v.bio || '')}</textarea></div>
    <div class="f"><label>E-mail</label><input id="v-email" value="${esc(v.email || '')}"></div>
    <div class="f"><label>Telefone</label><input id="v-tel" value="${esc(v.telefone || '')}"></div>
    <p class="erro" id="md-erro" style="display:none"></p>
    <div class="actions"><button class="btn ghost" onclick="fecharModal()">Cancelar</button><button class="btn" onclick="salvarVereador(${id || 0})">Salvar</button></div>`);
}

async function salvarVereador(id) {
  const corpo = {
    nome: $('v-nome').value, partido: $('v-partido').value, regiao: $('v-regiao').value,
    bio: $('v-bio').value, email: $('v-email').value, telefone: $('v-tel').value,
  };
  try {
    await (id ? api.put('/admin/vereadores/' + id, corpo) : api.post('/admin/vereadores', corpo));
    fecharModal(); toast('Vereador salvo');
    await carregarVereadores();
  } catch (e) { erroModal(e.message); }
}

async function excluirVereador(id) {
  if (!confirm('Excluir este vereador? Protocolos vinculados ficam sem vereador.')) return;
  try { await api.del('/admin/vereadores/' + id); toast('Vereador excluído'); await carregarVereadores(); } catch (e) { toast(e.message); }
}

/* ---------------------------------------------------------------- agenda */

async function carregarEventos() {
  const { eventos } = await api.get('/agenda');
  estado.eventos = eventos;
  $('ta').innerHTML = eventos.length ? eventos.map((e) => `
    <tr><td>${formatarData(e.data)} ${formatarHora(e.data)}</td><td>${esc(ROTULO_EVENTO[e.tipo] || e.tipo)}</td>
    <td>${esc(e.titulo)}</td><td>${esc(e.local || '')}</td>
    <td><button class="btn ghost" onclick="formEvento(${e.id})">Editar</button>
        <button class="btn ghost" onclick="excluirEvento(${e.id})">Excluir</button></td></tr>`).join('')
    : '<tr><td colspan="5">Nenhum evento cadastrado.</td></tr>';
}

function formEvento(id) {
  const e = estado.eventos.find((x) => x.id === id) || {};
  const [dia, hora] = (e.data || '').split(' ');
  abrirModal(`<h3>${id ? 'Editar' : 'Novo'} evento</h3>
    <div class="f"><label>Título</label><input id="e-titulo" value="${esc(e.titulo || '')}"></div>
    <div class="f"><label>Tipo</label><select id="e-tipo">${Object.entries(ROTULO_EVENTO).map(([k, r]) => `<option value="${k}" ${k === e.tipo ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
    <div class="f"><label>Data</label><input id="e-dia" type="date" value="${esc(dia || '')}"></div>
    <div class="f"><label>Hora</label><input id="e-hora" type="time" value="${esc((hora || '').slice(0, 5))}"></div>
    <div class="f"><label>Local</label><input id="e-local" value="${esc(e.local || '')}"></div>
    <div class="f"><label>Descrição</label><textarea id="e-desc">${esc(e.descricao || '')}</textarea></div>
    <div class="f"><label>Participantes (separados por vírgula)</label><input id="e-part" value="${esc((e.participantes || []).join(', '))}"></div>
    <p class="erro" id="md-erro" style="display:none"></p>
    <div class="actions"><button class="btn ghost" onclick="fecharModal()">Cancelar</button><button class="btn" onclick="salvarEvento(${id || 0})">Salvar</button></div>`);
}

async function salvarEvento(id) {
  const corpo = {
    titulo: $('e-titulo').value, tipo: $('e-tipo').value,
    data: $('e-dia').value ? $('e-dia').value + ' ' + ($('e-hora').value || '00:00') + ':00' : '',
    local: $('e-local').value, descricao: $('e-desc').value,
    participantes: $('e-part').value.split(',').map((s) => s.trim()).filter(Boolean),
  };
  try {
    await (id ? api.put('/admin/eventos/' + id, corpo) : api.post('/admin/eventos', corpo));
    fecharModal(); toast('Evento salvo');
    await carregarEventos();
  } catch (e) { erroModal(e.message); }
}

async function excluirEvento(id) {
  if (!confirm('Excluir este evento?')) return;
  try { await api.del('/admin/eventos/' + id); toast('Evento excluído'); await carregarEventos(); } catch (e) { toast(e.message); }
}

/* ----------------------------------------------------------- comunicados */

async function enviarComunicado(ev) {
  ev.preventDefault();
  const corpo = { titulo: $('ct').value, mensagem: $('cm').value };
  if ($('cp').value) corpo.bairro = $('cb').value;
  try {
    const { enviados } = await api.post('/admin/comunicados', corpo);
    ev.target.reset();
    $('cb-wrap').style.display = 'none';
    toast('Comunicado enviado para ' + enviados + (enviados === 1 ? ' cidadão' : ' cidadãos'));
  } catch (e) { toast(e.message); }
}

/* -------------------------------------------------------------- usuários */

async function carregarUsuarios() {
  const { usuarios } = await api.get('/admin/usuarios');
  estado.usuarios = usuarios;
  $('tu').innerHTML = usuarios.length ? usuarios.map((u) => `
    <tr><td>${esc(u.nome)}</td><td>${esc(u.email)}</td><td>${u.total_protocolos}</td>
    <td>${u.ativo ? 'Ativo' : 'Bloqueado'}</td>
    <td><button class="btn ghost" onclick="alternarBloqueio(${u.id}, ${u.ativo})">${u.ativo ? 'Bloquear' : 'Desbloquear'}</button></td></tr>`).join('')
    : '<tr><td colspan="5">Nenhum cidadão cadastrado.</td></tr>';
}

async function alternarBloqueio(id, estaAtivo) {
  try {
    await api.patch('/admin/usuarios/' + id + '/bloquear', { bloqueado: estaAtivo });
    toast(estaAtivo ? 'Usuário bloqueado' : 'Usuário desbloqueado');
    await carregarUsuarios();
  } catch (e) { toast(e.message); }
}

/* ---------------------------------------------------------------- início */

(function iniciar() {
  const u = api.usuario();
  if (api.token() && u && u.perfil === 'admin') mostrarPainel(); else mostrarLogin('');
})();
