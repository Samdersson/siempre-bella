
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

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Identificador de producto no válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    // Consultar información del producto
    $consultaProducto = $conexion->prepare(
        "SELECT
            id,
            categoria_id,
            marca_id,
            sku,
            nombre,
            slug,
            descripcion,
            precio_minorista,
            precio_mayorista,
            disponibilidad,
            activo
         FROM productos
         WHERE id = ?
         LIMIT 1"
    );

    $consultaProducto->execute([$id]);
    $producto = $consultaProducto->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Producto no encontrado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Consultar todas las fotografías
    $consultaImagenes = $conexion->prepare(
        "SELECT
            id,
            ruta,
            texto_alternativo,
            es_principal,
            orden
         FROM producto_imagenes
         WHERE producto_id = ?
         ORDER BY es_principal DESC, orden ASC, id ASC"
    );

    $consultaImagenes->execute([$id]);
    $imagenes = $consultaImagenes->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'producto' => $producto,
            'imagenes' => $imagenes
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar el producto.'
    ], JSON_UNESCAPED_UNICODE);
}
