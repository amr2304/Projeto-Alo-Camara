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
                         bairro, endereco, categoria, status, sigiloso, foto_url, localizacao)
                     VALUES
                        (:numero, :usuario_id, :vereador_id, :tipo, :titulo, :descricao,
                         :bairro, :endereco, :categoria, :status, :sigiloso, :foto_url, :localizacao)'
                );

                $stmt->execute([
                    'numero'      => $protocolo->getNumero(),
                    'usuario_id'  => $protocolo->getUsuarioId(),
                    'vereador_id' => $protocolo->getVereadorId(),
                    'tipo'        => $protocolo->getTipo(),
                    'titulo'      => $protocolo->getTitulo(),
                    'descricao'   => $protocolo->getDescricao(),
                    'bairro'      => $protocolo->getBairro(),
                    'endereco'    => $protocolo->getEndereco(),
                    'categoria'   => $protocolo->getCategoria(),
                    'status'      => $protocolo->getStatus(),
                    'sigiloso'    => (int) $protocolo->isSigiloso(),
                    'foto_url'    => $protocolo->getFotoUrl(),
                    'localizacao' => $protocolo->getLocalizacao(),
                ]);

                $protocolo->setId((int) $this->conn->lastInsertId());

                return $protocolo;
            } catch (PDOException $e) {
                // MySQL 1062 = entrada duplicada (numero repetido). Muito raro
                // (só em criações simultâneas), mas tratamos com nova tentativa.
                $codigoMySql = (int) ($e->errorInfo[1] ?? 0);
                if ($codigoMySql === 1062) {
                    $tentativasRestantes--;
                    continue;
                }

                // MySQL 1452 = chave estrangeira inexistente (usuário ou vereador).
                if ($codigoMySql === 1452) {
                    throw new InvalidArgumentException('Usuário ou vereador informado não existe.');
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

        if (!empty($filtros['bairro'])) {
            $condicoes[] = 'bairro = :bairro';
            $parametros['bairro'] = $filtros['bairro'];
        }

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

    /** Registra uma linha na linha do tempo do protocolo (status, resposta, encaminhamento). */
    public function registrarHistorico(int $protocoloId, string $status, ?string $resposta, ?string $encaminhadoPara, ?int $adminId): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO protocolo_historico (protocolo_id, status, resposta, encaminhado_para, admin_id)
             VALUES (:protocolo_id, :status, :resposta, :encaminhado_para, :admin_id)'
        );
        $stmt->execute([
            'protocolo_id'     => $protocoloId,
            'status'           => $status,
            'resposta'         => $resposta,
            'encaminhado_para' => $encaminhadoPara,
            'admin_id'         => $adminId,
        ]);
    }

    public function historico(int $protocoloId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT status, resposta, encaminhado_para, criado_em
             FROM protocolo_historico WHERE protocolo_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $protocoloId]);

        return $stmt->fetchAll();
    }

    /**
     * Listagem do painel admin: junta o nome do cidadão e aceita busca por
     * número/assunto. Devolve arrays (não models) porque inclui campos extras.
     *
     * @param array{status?: ?string, tipo?: ?string, busca?: ?string} $filtros
     */
    public function listarParaAdmin(array $filtros = []): array
    {
        $condicoes = [];
        $parametros = [];

        foreach (['status', 'tipo'] as $campo) {
            if (!empty($filtros[$campo])) {
                $condicoes[] = "p.{$campo} = :{$campo}";
                $parametros[$campo] = $filtros[$campo];
            }
        }

        if (!empty($filtros['busca'])) {
            $condicoes[] = '(p.numero LIKE :busca OR p.titulo LIKE :busca)';
            $parametros['busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql = 'SELECT p.*, u.nome AS usuario_nome
                FROM protocolos p JOIN usuarios u ON u.id = p.usuario_id';
        if ($condicoes) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }
        $sql .= ' ORDER BY p.criado_em DESC, p.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    /** Totais por status, tipo e bairro para o dashboard. */
    public function estatisticas(): array
    {
        $contar = function (string $coluna): array {
            $stmt = $this->conn->query(
                "SELECT COALESCE(NULLIF({$coluna}, ''), 'Não informado') AS chave, COUNT(*) AS total
                 FROM protocolos GROUP BY chave ORDER BY total DESC"
            );
            $mapa = [];
            foreach ($stmt->fetchAll() as $linha) {
                $mapa[$linha['chave']] = (int) $linha['total'];
            }

            return $mapa;
        };

        return [
            'total'      => (int) $this->conn->query('SELECT COUNT(*) FROM protocolos')->fetchColumn(),
            'por_status' => $contar('status'),
            'por_tipo'   => $contar('tipo'),
            'por_bairro' => $contar('bairro'),
        ];
    }
}
