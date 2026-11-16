
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

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
    $pdo = $database->connect();

    // Obtener productos activos
    $sqlProductos = "
        SELECT
            id,
            nombre,
            precio_minorista,
            precio_mayorista
        FROM productos
        WHERE activo = 1
        ORDER BY nombre ASC
    ";

    $stmtProductos = $pdo->prepare($sqlProductos);
    $stmtProductos->execute();

    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

    // Preparar estructura de productos
    foreach ($productos as &$producto) {
        $producto['variantes'] = [];
    }
    unset($producto);

    // Obtener variantes activas
    $sqlVariantes = "
        SELECT
            id,
            producto_id,
            sku,
            nombre,
            atributos,
            precio_minorista,
            precio_mayorista
        FROM variantes
        WHERE activo = 1
        ORDER BY nombre ASC
    ";

    $stmtVariantes = $pdo->prepare($sqlVariantes);
    $stmtVariantes->execute();

    $variantes = $stmtVariantes->fetchAll(PDO::FETCH_ASSOC);

    // Asociar variantes con sus productos
    $indiceProductos = [];

    foreach ($productos as $indice => $producto) {
        $indiceProductos[$producto['id']] = $indice;
    }

    foreach ($variantes as $variante) {
        $productoId = $variante['producto_id'];

        if (isset($indiceProductos[$productoId])) {
            $indice = $indiceProductos[$productoId];

            $productos[$indice]['variantes'][] = $variante;
        }
    }

    echo json_encode([
        'success' => true,
        'total' => count($productos),
        'data' => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar productos y variantes.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
