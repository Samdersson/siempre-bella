
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

$plataforma = trim($datos['plataforma'] ?? '');
$url = trim($datos['url'] ?? '');
$orden = filter_var(
    $datos['orden'] ?? 0,
    FILTER_VALIDATE_INT
);

if ($plataforma === '' || mb_strlen($plataforma) > 60) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La plataforma es obligatoria y debe tener máximo 60 caracteres.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    $url === '' ||
    mb_strlen($url) > 500 ||
    !filter_var($url, FILTER_VALIDATE_URL) ||
    !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Introduce una URL válida que comience por HTTP o HTTPS.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($orden === false || $orden < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El orden debe ser un número entero igual o mayor que cero.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    $sql = "
        INSERT INTO redes_sociales (
            plataforma,
            url,
            orden,
            activo
        ) VALUES (?, ?, ?, 1)
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        $plataforma,
        $url,
        $orden
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Red social registrada correctamente.',
        'red_id' => (int) $conexion->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al crear red social: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible registrar la red social.'
    ], JSON_UNESCAPED_UNICODE);
}
