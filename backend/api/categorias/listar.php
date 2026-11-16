
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
            descripcion,
            imagen
         FROM categorias
         WHERE activo = 1
         ORDER BY nombre ASC"
    );

    $consulta->execute();

    $categorias = $consulta->fetchAll();

    echo json_encode([
        'success' => true,
        'total' => count($categorias),
        'data' => $categorias
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener las categorías.'
    ], JSON_UNESCAPED_UNICODE);
}
