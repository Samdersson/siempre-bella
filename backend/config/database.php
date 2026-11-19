
<?php

class Database
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD')
        ];
        foreach ($this->config as $key => $value) {
            if ($value === false || $value === '') {
                throw new RuntimeException(
                    "Falta la variable de conexión: {$key}"
                );
            }
        }
    }

    public function connect(): PDO
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['database']
            );

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
            error_log('Error PDO MySQL: ' . $e->getMessage());

            throw new RuntimeException(
                'No fue posible conectar con MySQL.',
                0,
                $e
            );
        }
    }
}
