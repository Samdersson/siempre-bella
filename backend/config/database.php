<?php

class Database
{
    private array $config;

    public function __construct()
    {
        $envConfig = [
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_NAME'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD')
        ];

        $hasEnvConfig = false;

        foreach ($envConfig as $value) {
            if ($value !== false && $value !== '') {
                $hasEnvConfig = true;
                break;
            }
        }

        if ($hasEnvConfig) {
            foreach ($envConfig as $value) {
                if ($value === false || $value === '') {
                    throw new RuntimeException(
                        'La configuración de producción está incompleta.'
                    );
                }
            }

            $this->config = $envConfig;
            return;
        }

        $configFile = __DIR__ . '/database.local.php';

        if (!file_exists($configFile)) {
            throw new RuntimeException(
                'No se encontró la configuración de la base de datos.'
            );
        }

        $config = require $configFile;

        if (!is_array($config)) {
            throw new RuntimeException(
                'El archivo de configuración no es válido.'
            );
        }

        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException(
                    'Falta una configuración de base de datos.'
                );
            }
        }

        $this->config = $config;
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
