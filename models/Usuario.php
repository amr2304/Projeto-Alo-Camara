<?php
/**
 * Model Usuario — representa o cidadão cadastrado na plataforma.
 * Objeto simples (POJO-like), sem regra de negócio — isso fica no Service.
 */
class Usuario
{
    private ?int $id;
    private string $nome;
    private string $email;
    private ?string $senhaHash;
    private ?string $telefone;
    private ?string $bairro;
    private bool $notifPush;
    private bool $notifEmail;
    private ?string $criadoEm;

    public function __construct(
        ?int $id,
        string $nome,
        string $email,
        ?string $senhaHash = null,
        ?string $telefone = null,
        ?string $bairro = null,
        bool $notifPush = true,
        bool $notifEmail = false,
        ?string $criadoEm = null
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->email = $email;
        $this->senhaHash = $senhaHash;
        $this->telefone = $telefone;
        $this->bairro = $bairro;
        $this->notifPush = $notifPush;
        $this->notifEmail = $notifEmail;
        $this->criadoEm = $criadoEm;
    }

    /** Cria um Usuario a partir de uma linha do banco (array associativo). */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['nome'],
            $row['email'],
            $row['senha_hash'] ?? null,
            $row['telefone'] ?? null,
            $row['bairro'] ?? null,
            (bool) ($row['notif_push'] ?? true),
            (bool) ($row['notif_email'] ?? false),
            $row['criado_em'] ?? null
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getEmail(): string { return $this->email; }
    public function getSenhaHash(): ?string { return $this->senhaHash; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function getBairro(): ?string { return $this->bairro; }
    public function isNotifPush(): bool { return $this->notifPush; }
    public function isNotifEmail(): bool { return $this->notifEmail; }
    public function getCriadoEm(): ?string { return $this->criadoEm; }

    // Setters usados nas atualizações de perfil/preferências
    public function setId(int $id): void { $this->id = $id; }
    public function setNome(string $nome): void { $this->nome = $nome; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setSenhaHash(string $senhaHash): void { $this->senhaHash = $senhaHash; }
    public function setTelefone(?string $telefone): void { $this->telefone = $telefone; }
    public function setBairro(?string $bairro): void { $this->bairro = $bairro; }
    public function setNotifPush(bool $notifPush): void { $this->notifPush = $notifPush; }
    public function setNotifEmail(bool $notifEmail): void { $this->notifEmail = $notifEmail; }

    /** Representação segura para devolver em respostas (sem a senha). */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'nome'        => $this->nome,
            'email'       => $this->email,
            'telefone'    => $this->telefone,
            'bairro'      => $this->bairro,
            'notif_push'  => $this->notifPush,
            'notif_email' => $this->notifEmail,
            'criado_em'   => $this->criadoEm,
        ];
    }
}
