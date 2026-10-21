<?php

require_once __DIR__ . '/../dao/EventoDAO.php';

class AgendaService
{
    private EventoDAO $dao;

    public function __construct(?EventoDAO $dao = null)
    {
        $this->dao = $dao ?? new EventoDAO();
    }

    public function salvar(array $dados, ?int $id = null): Evento
    {
        $titulo = trim((string) ($dados['titulo'] ?? ''));
        $tipo = strtolower(trim((string) ($dados['tipo'] ?? '')));
        $data = trim((string) ($dados['data'] ?? ''));
        $tiposValidos = ['sessao', 'comissao', 'audiencia'];

        if ($titulo === '' || !in_array($tipo, $tiposValidos, true) || strtotime($data) === false) {
            throw new InvalidArgumentException('Título, tipo válido e data são obrigatórios.');
        }

        $participantes = $dados['participantes'] ?? [];
        if (!is_array($participantes)) {
            throw new InvalidArgumentException('Participantes deve ser uma lista.');
        }

        $evento = new Evento(
            $id,
            $titulo,
            $tipo,
            $data,
            $dados['local'] ?? null,
            $dados['descricao'] ?? null,
            $participantes
        );

        return $this->dao->save($evento);
    }

    public function listar(): array
    {
        return $this->dao->all();
    }

    public function obter(int $id): ?Evento
    {
        return $this->dao->find($id);
    }

    public function excluir(int $id): bool
    {
        return $this->dao->delete($id);
    }
}
