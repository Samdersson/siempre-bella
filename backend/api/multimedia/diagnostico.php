
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// 1. Verificar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Permitir únicamente GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    // 3. Conectar con la base de datos
    $database = new Database();
    $conexion = $database->connect();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // 4. Consultar estructura de la tabla
    $columnas = $conexion->query(
        "SHOW COLUMNS FROM recursos_multimedia"
    )->fetchAll(PDO::FETCH_COLUMN);

    // 5. Comprobar columnas necesarias
    $columnasRequeridas = [
        'id',
        'nombre_original',
        'nombre_archivo',
        'ruta_archivo',
        'tipo_mime',
        'extension',
        'tamanio_bytes',
        'texto_alternativo',
        'activo',
        'fecha_creacion',
        'fecha_actualizacion'
    ];

    $columnasFaltantes = array_values(
        array_diff($columnasRequeridas, $columnas)
    );

    // 6. Consultar conexión sin exponer datos del servidor
    $conexionActiva = $conexion->query(
        "SELECT 1"
    )->fetchColumn() !== false;

    // 7. Respuesta
    echo json_encode([
        'success' => true,
        'diagnostico' => [
            'conexion_activa' => $conexionActiva,
            'tabla' => 'recursos_multimedia',
            'tabla_accesible' => true,
            'columnas' => $columnas,
            'columnas_requeridas' => $columnasRequeridas,
            'columnas_faltantes' => $columnasFaltantes,
            'estructura_correcta' => empty($columnasFaltantes),
            'existe_tamanio_bytes' => in_array(
                'tamanio_bytes',
                $columnas,
                true
            )
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        'Error en diagnóstico multimedia: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar la estructura multimedia.'
    ], JSON_UNESCAPED_UNICODE);
}
