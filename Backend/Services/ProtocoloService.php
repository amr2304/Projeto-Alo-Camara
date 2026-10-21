<?php

require_once __DIR__ . '/../dao/ProtocoloDAO.php';

class ProtocoloService
{
    private ProtocoloDAO $dao;

    public function __construct(?ProtocoloDAO $dao = null)
    {
        $this->dao = $dao ?? new ProtocoloDAO();
    }

    public function criar(array $dados): Protocolo
    {
        $tipo = strtolower(trim((string) ($dados['tipo'] ?? '')));
        $descricao = trim((string) ($dados['descricao'] ?? ''));
        $usuarioId = (int) ($dados['usuario_id'] ?? 0);
        $titulo = trim((string) ($dados['titulo'] ?? $dados['assunto'] ?? ''));

        // Reclamação, denúncia e elogio não têm campo "assunto" na tela: usa o início da descrição.
        if ($titulo === '' && $descricao !== '') {
            $titulo = mb_substr($descricao, 0, 80);
        }

        if (!in_array($tipo, Protocolo::TIPOS, true) || $usuarioId < 1 || $titulo === '' || $descricao === '') {
            throw new InvalidArgumentException('Tipo, usuario_id, título e descrição válidos são obrigatórios.');
        }

        $sigiloso = $tipo === 'denuncia' || (bool) ($dados['sigiloso'] ?? $dados['anonimo'] ?? false);
        $protocolo = new Protocolo(
            null,
            '',
            $usuarioId,
            empty($dados['vereador_id']) ? null : (int) $dados['vereador_id'],
            $tipo,
            mb_substr($titulo, 0, 150),
            $descricao,
            $dados['bairro'] ?? null,
            'recebido',
            $sigiloso,
            $dados['foto_url'] ?? $dados['anexo'] ?? null,
            $dados['localizacao'] ?? null,
            null,
            null,
            null,
            null,
            $dados['endereco'] ?? null,
            $dados['categoria'] ?? null
        );

        $protocolo = $this->dao->create($protocolo);
        $this->dao->registrarHistorico($protocolo->getId(), 'recebido', null, null, null);

        return $protocolo;
    }

    public function listar(array $filtros = []): array
    {
        foreach (['status' => Protocolo::STATUS, 'tipo' => Protocolo::TIPOS] as $campo => $valoresValidos) {
            if (!empty($filtros[$campo]) && !in_array($filtros[$campo], $valoresValidos, true)) {
                throw new InvalidArgumentException("Filtro {$campo} inválido.");
            }
        }

        if (isset($filtros['usuario_id'])) {
            return $this->dao->findByUsuario((int) $filtros['usuario_id'], $filtros);
        }

        return $this->dao->findAll($filtros);
    }

    public function obter(int $id): ?Protocolo
    {
        return $this->dao->findById($id);
    }

    public function mudarStatus(int $id, string $novoStatus, ?string $resposta = null, ?int $respondidoPor = null): Protocolo
    {
        $protocolo = $this->dao->findById($id);
        if ($protocolo === null) {
            throw new OutOfBoundsException('Protocolo não encontrado.');
        }

        $proximosStatus = [
            'recebido' => 'em_analise',
            'em_analise' => 'respondido',
            'respondido' => 'finalizado',
        ];

        if (($proximosStatus[$protocolo->getStatus()] ?? null) !== $novoStatus) {
            throw new InvalidArgumentException('Transição de status inválida.');
        }

        if ($novoStatus === 'respondido' && trim((string) $resposta) === '') {
            throw new InvalidArgumentException('A resposta é obrigatória.');
        }

        $protocolo->setStatus($novoStatus);
        if ($novoStatus === 'respondido') {
            $protocolo->setResposta($resposta);
            $protocolo->setRespondidoPor($respondidoPor);
        }

        $this->dao->update($protocolo);

        require_once __DIR__ . '/NotificacaoService.php';
        try {
            (new NotificacaoService())->gerarPorMudancaProtocolo($protocolo);
        } catch (Throwable $erro) {
            error_log('[notificacao protocolo] ' . $erro->getMessage());
        }

        return $protocolo;
    }

    public function obterPorNumero(string $numero): ?Protocolo
    {
        return $this->dao->findByNumero($numero);
    }

    public function historico(int $protocoloId): array
    {
        return $this->dao->historico($protocoloId);
    }

    public function listarParaAdmin(array $filtros = []): array
    {
        return $this->dao->listarParaAdmin($filtros);
    }

    public function estatisticas(): array
    {
        return $this->dao->estatisticas();
    }

    /**
     * Atualização feita pelo painel admin: o status só pode avançar (ou ficar igual,
     * para registrar uma nova resposta/encaminhamento). Cada alteração grava uma
     * linha em protocolo_historico e, se o status mudou, notifica o cidadão.
     */
    public function atualizarPeloAdmin(int $id, string $novoStatus, ?string $resposta, ?string $encaminhadoPara, int $adminId): Protocolo
    {
        $protocolo = $this->dao->findById($id);
        if ($protocolo === null) {
            throw new OutOfBoundsException('Protocolo não encontrado.');
        }

        $ordem = array_flip(Protocolo::STATUS);
        if (!isset($ordem[$novoStatus])) {
            throw new InvalidArgumentException('Status inválido.');
        }

        $statusAnterior = $protocolo->getStatus();
        if ($ordem[$novoStatus] < $ordem[$statusAnterior]) {
            throw new InvalidArgumentException('Não é possível voltar o protocolo para um status anterior.');
        }

        $resposta = $resposta !== null ? trim($resposta) : null;
        $resposta = $resposta === '' ? null : $resposta;
        $encaminhadoPara = $encaminhadoPara !== null ? trim($encaminhadoPara) : null;
        $encaminhadoPara = $encaminhadoPara === '' ? null : $encaminhadoPara;

        $respostaFinal = $resposta ?? $protocolo->getResposta();
        if ($ordem[$novoStatus] >= $ordem['respondido'] && trim((string) $respostaFinal) === '') {
            throw new InvalidArgumentException('A resposta ao cidadão é obrigatória para responder ou finalizar.');
        }

        $mudouStatus = $novoStatus !== $statusAnterior;
        if (!$mudouStatus && $resposta === null && $encaminhadoPara === null) {
            return $protocolo; // nada a registrar
        }

        $protocolo->setStatus($novoStatus);
        if ($resposta !== null) {
            $protocolo->setResposta($resposta);
            $protocolo->setRespondidoPor($adminId);
        }

        $this->dao->update($protocolo);
        $this->dao->registrarHistorico($id, $novoStatus, $resposta, $encaminhadoPara, $adminId);

        if ($mudouStatus) {
            require_once __DIR__ . '/NotificacaoService.php';
            try {
                (new NotificacaoService())->gerarPorMudancaProtocolo($protocolo);
            } catch (Throwable $erro) {
                error_log('[notificacao protocolo] ' . $erro->getMessage());
            }
        }

        return $this->dao->findById($id) ?? $protocolo;
    }

    public function excluir(int $id): bool
    {
        if ($this->dao->findById($id) === null) {
            throw new OutOfBoundsException('Protocolo não encontrado.');
        }

        return $this->dao->delete($id);
    }
}
