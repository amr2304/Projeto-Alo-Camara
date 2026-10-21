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
        $titulo = trim((string) ($dados['titulo'] ?? ''));
        $descricao = trim((string) ($dados['descricao'] ?? ''));
        $usuarioId = (int) ($dados['usuario_id'] ?? 0);

        if (!in_array($tipo, Protocolo::TIPOS, true) || $usuarioId < 1 || $titulo === '' || $descricao === '') {
            throw new InvalidArgumentException('Tipo, usuario_id, título e descrição válidos são obrigatórios.');
        }

        $sigiloso = $tipo === 'denuncia' || (bool) ($dados['sigiloso'] ?? false);
        $protocolo = new Protocolo(
            null,
            '',
            $usuarioId,
            empty($dados['vereador_id']) ? null : (int) $dados['vereador_id'],
            $tipo,
            $titulo,
            $descricao,
            $dados['bairro'] ?? null,
            'recebido',
            $sigiloso,
            $dados['foto_url'] ?? null,
            $dados['localizacao'] ?? null
        );

        return $this->dao->create($protocolo);
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

    public function excluir(int $id): bool
    {
        if ($this->dao->findById($id) === null) {
            throw new OutOfBoundsException('Protocolo não encontrado.');
        }

        return $this->dao->delete($id);
    }
}
