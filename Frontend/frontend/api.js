/**
 * Cliente da API do Alô Câmara, compartilhado pelo site do cidadão e pelo painel admin.
 * Para apontar para outro servidor, defina window.ALO_API_URL antes de carregar este arquivo.
 *
 * O token fica no localStorage, em chaves separadas para o cidadão e para o admin,
 * para que os dois painéis possam ficar logados ao mesmo tempo no mesmo navegador.
 */
const API_URL = (window.ALO_API_URL || 'http://localhost:8000').replace(/\/$/, '');

function criarApi(chaveSessao) {
  const ler = () => {
    try { return JSON.parse(localStorage.getItem(chaveSessao)) || null; } catch (e) { return null; }
  };

  const api = {
    sessao: ler,
    token: () => (ler() || {}).token || null,
    usuario: () => (ler() || {}).usuario || null,
    salvarSessao(dados) { localStorage.setItem(chaveSessao, JSON.stringify(dados)); },
    sair() { localStorage.removeItem(chaveSessao); },
    urlArquivo: (caminho) => (caminho ? API_URL + caminho : ''),
    aoExpirarSessao: null,

    async req(metodo, caminho, corpo) {
      const headers = {};
      if (corpo !== undefined && !(corpo instanceof FormData)) headers['Content-Type'] = 'application/json';
      if (api.token()) headers['Authorization'] = 'Bearer ' + api.token();

      let resp;
      try {
        resp = await fetch(API_URL + caminho, {
          method: metodo,
          headers,
          body: corpo === undefined ? undefined : (corpo instanceof FormData ? corpo : JSON.stringify(corpo)),
        });
      } catch (e) {
        throw new Error('Não foi possível conectar ao servidor. Verifique se o backend está rodando em ' + API_URL + '.');
      }

      let dados = {};
      try { dados = await resp.json(); } catch (e) { /* resposta sem corpo */ }

      if (!resp.ok) {
        // 401 fora do login significa sessão expirada ou conta bloqueada.
        if (resp.status === 401 && api.token() && !caminho.startsWith('/auth/') && api.aoExpirarSessao) {
          api.sair();
          api.aoExpirarSessao();
        }
        throw new Error(dados.erro || 'Erro ' + resp.status + ' ao falar com o servidor.');
      }
      return dados;
    },

    get: (c) => api.req('GET', c),
    post: (c, b) => api.req('POST', c, b === undefined ? {} : b),
    put: (c, b) => api.req('PUT', c, b),
    patch: (c, b) => api.req('PATCH', c, b === undefined ? {} : b),
    del: (c) => api.req('DELETE', c),
  };

  return api;
}

/** Escapa texto vindo do servidor antes de colocar em innerHTML. */
function esc(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

const ROTULO_TIPO = {
  solicitacao: 'Solicitação', reclamacao: 'Reclamação', sugestao: 'Sugestão', denuncia: 'Denúncia', elogio: 'Elogio',
};
const ROTULO_STATUS = {
  recebido: 'Recebido', em_analise: 'Em Análise', respondido: 'Respondido', finalizado: 'Finalizado',
};
const ROTULO_EVENTO = { sessao: 'Sessão', comissao: 'Comissão', audiencia: 'Audiência' };

function formatarData(texto) {
  const d = new Date(String(texto || '').replace(' ', 'T'));
  return isNaN(d) ? '' : d.toLocaleDateString('pt-BR');
}

function formatarHora(texto) {
  const d = new Date(String(texto || '').replace(' ', 'T'));
  return isNaN(d) ? '' : d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function tempoRelativo(texto) {
  const d = new Date(String(texto || '').replace(' ', 'T'));
  if (isNaN(d)) return '';
  const min = Math.floor((Date.now() - d.getTime()) / 60000);
  if (min < 1) return 'agora';
  if (min < 60) return 'há ' + min + ' min';
  const h = Math.floor(min / 60);
  if (h < 24) return 'há ' + h + ' h';
  const dias = Math.floor(h / 24);
  return 'há ' + dias + (dias === 1 ? ' dia' : ' dias');
}
