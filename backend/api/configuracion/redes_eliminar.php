
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El identificador no es válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    $verificar = $conexion->prepare(
        "SELECT id FROM redes_sociales WHERE id = ? LIMIT 1"
    );

    $verificar->execute([$id]);

    if (!$verificar->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La red social no existe.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $eliminar = $conexion->prepare(
        "DELETE FROM redes_sociales WHERE id = ?"
    );

    $eliminar->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Red social eliminada correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al eliminar red social: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible eliminar la red social.'
    ], JSON_UNESCAPED_UNICODE);
}
