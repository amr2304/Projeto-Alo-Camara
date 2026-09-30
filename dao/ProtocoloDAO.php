<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Protocolo.php';

/**
 * DAO de Protocolo — único ponto que fala SQL sobre a tabela `protocolos`.
 * A máquina de estados do status e as regras por tipo (denúncia sigilosa,
 * elogio com imagem/geolocalização) ficam no ProtocoloService — este DAO
 * só persiste o que o Service decidir.
 */
class ProtocoloDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    /**
     * Cria o protocolo, gerando o número automaticamente (ex: AL20240001).
     * Faz algumas tentativas em caso de colisão rara de número (dois
     * protocolos criados no mesmo instante) — cada tentativa recalcula
     * o próximo número disponível.
     *
     * @throws RuntimeException se não conseguir gerar um número único
     *         após todas as tentativas
     */
    public function create(Protocolo $protocolo): Protocolo
    {
        $tentativasRestantes = 5;

        while ($tentativasRestantes > 0) {
            $numero = $this->proximoNumeroDisponivel();
            $protocolo->setNumero($numero);

            try {
                $stmt = $this->conn->prepare(
                    'INSERT INTO protocolos
                        (numero, usuario_id, vereador_id, tipo, titulo, descricao,
                         bairro, status, sigiloso, foto_url, localizacao)
                     VALUES
                        (:numero, :usuario_id, :vereador_id, :tipo, :titulo, :descricao,
                         :bairro, :status, :sigiloso, :foto_url, :localizacao)'
                );

                $stmt->execute([
                    'numero'      => $protocolo->getNumero(),
                    'usuario_id'  => $protocolo->getUsuarioId(),
                    'vereador_id' => $protocolo->getVereadorId(),
                    'tipo'        => $protocolo->getTipo(),
                    'titulo'      => $protocolo->getTitulo(),
                    'descricao'   => $protocolo->getDescricao(),
                    'bairro'      => $protocolo->getBairro(),
                    'status'      => $protocolo->getStatus(),
                    'sigiloso'    => (int) $protocolo->isSigiloso(),
                    'foto_url'    => $protocolo->getFotoUrl(),
                    'localizacao' => $protocolo->getLocalizacao(),
                ]);

                $protocolo->setId((int) $this->conn->lastInsertId());

                return $protocolo;
            } catch (PDOException $e) {
                // 23000 = violação de constraint única (numero duplicado).
                // Muito raro (só em criações simultâneas), mas tratamos.
                if ($e->getCode() === '23000') {
                    $tentativasRestantes--;
                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException('Não foi possível gerar um número de protocolo único. Tente novamente.');
    }

    /**
     * Próximo número disponível para o ano atual, no formato AL + ano + 4
     * dígitos sequenciais (ex: AL20240001, AL20240002, ...).
     */
    private function proximoNumeroDisponivel(): string
    {
        $ano = date('Y');
        $prefixo = "AL{$ano}";

        $stmt = $this->conn->prepare(
            'SELECT numero FROM protocolos
             WHERE numero LIKE :prefixo
             ORDER BY numero DESC
             LIMIT 1'
        );
        $stmt->execute(['prefixo' => $prefixo . '%']);
        $ultimoNumero = $stmt->fetchColumn();

        $proximoSequencial = 1;
        if ($ultimoNumero !== false) {
            $sequencialAtual = (int) substr($ultimoNumero, strlen($prefixo));
            $proximoSequencial = $sequencialAtual + 1;
        }

        return sprintf('%s%04d', $prefixo, $proximoSequencial);
    }

    public function findById(int $id): ?Protocolo
    {
        $stmt = $this->conn->prepare('SELECT * FROM protocolos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Protocolo::fromArray($row) : null;
    }

    public function findByNumero(string $numero): ?Protocolo
    {
        $stmt = $this->conn->prepare('SELECT * FROM protocolos WHERE numero = :numero');
        $stmt->execute(['numero' => $numero]);
        $row = $stmt->fetch();

        return $row ? Protocolo::fromArray($row) : null;
    }

    /**
     * Listagem com filtros opcionais — usada na tela "Protocolos" (visão
     * geral/admin). Filtros não informados (null) são ignorados.
     *
     * @param array{status?: ?string, bairro?: ?string, tipo?: ?string} $filtros
     * @return Protocolo[]
     */
    public function findAll(array $filtros = []): array
    {
        $condicoes = [];
        $parametros = [];

        if (!empty($filtros['status'])) {
            $condicoes[] = 'status = :status';
            $parametros['status'] = $filtros['status'];
        }

        if (!empty($filtros['bairro'])) {
            $condicoes[] = 'bairro = :bairro';
            $parametros['bairro'] = $filtros['bairro'];
        }

        if (!empty($filtros['tipo'])) {
            $condicoes[] = 'tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }

        $sql = 'SELECT * FROM protocolos';
        if (!empty($condicoes)) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }
        $sql .= ' ORDER BY criado_em DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($parametros);

        return array_map(fn (array $row) => Protocolo::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Protocolos abertos por um cidadão específico — usada na tela
     * "Minhas Solicitações". Aceita os mesmos filtros opcionais de findAll().
     *
     * @param array{status?: ?string, tipo?: ?string} $filtros
     * @return Protocolo[]
     */
    public function findByUsuario(int $usuarioId, array $filtros = []): array
    {
        $condicoes = ['usuario_id = :usuario_id'];
        $parametros = ['usuario_id' => $usuarioId];

        if (!empty($filtros['status'])) {
            $condicoes[] = 'status = :status';
            $parametros['status'] = $filtros['status'];
        }

        if (!empty($filtros['tipo'])) {
            $condicoes[] = 'tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }

        $sql = 'SELECT * FROM protocolos WHERE ' . implode(' AND ', $condicoes) . ' ORDER BY criado_em DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($parametros);

        return array_map(fn (array $row) => Protocolo::fromArray($row), $stmt->fetchAll());
    }

    /** Atualiza os campos editáveis do protocolo (não inclui numero, usuario_id, tipo — imutáveis após criação). */
    public function update(Protocolo $protocolo): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE protocolos SET
                vereador_id = :vereador_id,
                status = :status,
                foto_url = :foto_url,
                localizacao = :localizacao,
                resposta = :resposta,
                respondido_por = :respondido_por
             WHERE id = :id'
        );

        return $stmt->execute([
            'vereador_id'    => $protocolo->getVereadorId(),
            'status'         => $protocolo->getStatus(),
            'foto_url'       => $protocolo->getFotoUrl(),
            'localizacao'    => $protocolo->getLocalizacao(),
            'resposta'       => $protocolo->getResposta(),
            'respondido_por' => $protocolo->getRespondidoPor(),
            'id'             => $protocolo->getId(),
        ]);
    }

    /** Atalho usado pela máquina de estados do Service, sem precisar recarregar o objeto inteiro. */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->conn->prepare('UPDATE protocolos SET status = :status WHERE id = :id');

        return $stmt->execute([
            'status' => $status,
            'id'     => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM protocolos WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
