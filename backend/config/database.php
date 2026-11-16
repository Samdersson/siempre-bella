<?php

class Database
{
    private array $config;

    public function __construct()
    {
        $configFile = __DIR__ . '/database.local.php';

        if (!file_exists($configFile)) {
            throw new RuntimeException(
                'No se encontró la configuración local de la base de datos.'
            );
        }

        $this->config = require $configFile;
    }

    public function connect(): PDO
    {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset=utf8mb4";

            return new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

        } catch (PDOException $e) {
            throw new RuntimeException(
                'No fue posible conectar con la base de datos.',
                0,
                $e
            );
        }
    }
}