
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// Comprobar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);

    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    $consulta = $conexion->prepare(
        "SELECT
            p.id,
            p.categoria_id,
            p.marca_id,
            p.sku,
            p.nombre,
            p.slug,
            p.descripcion,
            p.precio_minorista,
            p.precio_mayorista,
            p.disponibilidad,
            p.activo,
            c.nombre AS categoria,
            m.nombre AS marca,
            (
                SELECT pi.ruta
                FROM producto_imagenes pi
                WHERE pi.producto_id = p.id
                ORDER BY pi.es_principal DESC, pi.orden ASC
                LIMIT 1
            ) AS imagen
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas m ON m.id = p.marca_id
         ORDER BY p.creado_en DESC"
    );

    $consulta->execute();

    $productos = $consulta->fetchAll();

    echo json_encode([
        'success' => true,
        'total' => count($productos),
        'data' => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener los productos.'
    ], JSON_UNESCAPED_UNICODE);
}
