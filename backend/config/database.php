
<?php

class Database
{
    private array $config;

    public function __construct()
    {
        $entorno = strtolower((string) (getenv('APP_ENV') ?: ''));
        $hostSolicitud = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $hostSolicitud = preg_replace('/:\\d+$/', '', $hostSolicitud);
        $solicitudLocal = in_array($hostSolicitud, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        $archivoLocal = __DIR__ . '/database.local.php';

        // En localhost se usa la configuración de pruebas; producción conserva DB_*.
        if ($entorno === 'local' || ($entorno !== 'production' && $solicitudLocal && is_file($archivoLocal))) {
            $configuracion = require $archivoLocal;
            if (!is_array($configuracion)) {
                throw new RuntimeException('La configuración local de la base de datos no es válida.');
            }

            $this->config = array_merge([
                'host' => null,
                'port' => '3306',
                'database' => null,
                'username' => null,
                'password' => null
            ], $configuracion);

            foreach ($this->config as $key => $value) {
                // La contraseña local puede estar vacía en XAMPP.
                if ($key === 'password') {
                    continue;
                }

                if ($value === false || $value === null || $value === '') {
                    throw new RuntimeException("Falta la configuración local: {$key}");
                }
            }

            return;
        }

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
