
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión administrativa
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    empty($_SESSION['admin']['id'])
) {
    responder(401, [
        'success' => false,
        'message' => 'No autorizado.'
    ]);
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

try {

    $database = new Database();
    $pdo = $database->connect();

    // Obtener únicamente productos activos
    $sql = "
        SELECT
            id,
            nombre,
            precio_minorista,
            precio_mayorista
        FROM productos
        WHERE activo = 1
        ORDER BY nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mantener estructura compatible con el frontend
    foreach ($productos as &$producto) {
        $producto['variantes'] = [];
    }

    unset($producto);

    responder(200, [
        'success' => true,
        'total' => count($productos),
        'data' => $productos
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al consultar productos para promociones: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Error al consultar los productos disponibles.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al consultar productos para promociones: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
