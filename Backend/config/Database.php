<?php
/**
 * Conexão única (singleton) com o banco de dados via PDO.
 * Ajuste as constantes abaixo conforme o ambiente de cada máquina/grupo.
 */
class Database
{
    private static ?PDO $instance = null;

    private const HOST   = '127.0.0.1'; // sobrescreva com a variável de ambiente ALO_DB_HOST
    private const DBNAME = 'alo_camara';
    private const USER   = 'root';
    private const PASS   = '';
    private const CHARSET = 'utf8mb4';

    private function __construct()
    {
        // Impede instanciamento direto — use Database::getConnection()
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                getenv('ALO_DB_HOST') ?: self::HOST,
                getenv('ALO_DB_NAME') ?: self::DBNAME,
                self::CHARSET
            );

            self::$instance = new PDO(
                $dsn,
                getenv('ALO_DB_USER') ?: self::USER,
                getenv('ALO_DB_PASS') !== false ? getenv('ALO_DB_PASS') : self::PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return self::$instance;
    }
}
