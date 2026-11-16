
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $conexion = $database->connect();

    $consulta = $conexion->prepare(
        "SELECT
            id,
            nombre,
            slug,
            descripcion
         FROM marcas
         WHERE activo = 1
         ORDER BY nombre ASC"
    );

    $consulta->execute();

    $marcas = $consulta->fetchAll();

    echo json_encode([
        'success' => true,
        'total' => count($marcas),
        'data' => $marcas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener las marcas.'
    ], JSON_UNESCAPED_UNICODE);
}
