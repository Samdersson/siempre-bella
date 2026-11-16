
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    ], JSON_UNESCAPED_UNICODE);

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
