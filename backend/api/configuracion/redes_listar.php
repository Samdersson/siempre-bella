
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Permitir únicamente solicitudes GET
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

    $database = new Database();
    $conexion = $database->connect();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // Consultar todas las redes sociales
    $sql = "
        SELECT
            id,
            plataforma,
            url,
            orden,
            activo,
            creado_en,
            actualizado_en
        FROM redes_sociales
        ORDER BY orden ASC, id ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $redes = $consulta->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($redes),
        'data' => $redes
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        'Error al listar redes sociales: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar las redes sociales.'
    ], JSON_UNESCAPED_UNICODE);
}
