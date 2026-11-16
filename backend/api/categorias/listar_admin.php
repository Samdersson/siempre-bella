
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    $consulta = $conexion->prepare(
        "SELECT
            id,
            nombre,
            slug,
            descripcion,
            activo,
            imagen,
            creado_en,
            actualizado_en
         FROM categorias
         ORDER BY nombre ASC"
    );

    $consulta->execute();

    $categorias = $consulta->fetchAll(PDO::FETCH_ASSOC);

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
