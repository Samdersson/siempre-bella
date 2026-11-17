
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// Verificar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Permitir únicamente POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar token CSRF
exigirTokenCSRF();

// Leer y validar JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar identificador
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
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

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // Verificar que la red social exista
    $verificar = $conexion->prepare("
        SELECT id
        FROM redes_sociales
        WHERE id = ?
        LIMIT 1
    ");

    $verificar->execute([$id]);

    if (!$verificar->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'La red social no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Eliminar registro
    $eliminar = $conexion->prepare("
        DELETE FROM redes_sociales
        WHERE id = ?
    ");

    $eliminar->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Red social eliminada correctamente.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
