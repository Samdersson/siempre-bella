
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

// Leer datos JSON
$contenido = file_get_contents('php://input');
$datos = json_decode($contenido, true);

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

// Validar estado
$activo = filter_var(
    $datos['activo'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0 ||
    !in_array($activo, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El identificador o estado no son válidos.'
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

    // Actualizar estado
    $actualizar = $conexion->prepare("
        UPDATE redes_sociales
        SET activo = ?
        WHERE id = ?
    ");

    $actualizar->execute([$activo, $id]);

    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Red social activada correctamente.'
            : 'Red social desactivada correctamente.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        'Error al cambiar estado de red social: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cambiar el estado de la red social.'
    ], JSON_UNESCAPED_UNICODE);
}
