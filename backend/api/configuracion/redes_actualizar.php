
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
$plataforma = trim($datos['plataforma'] ?? '');
$url = trim($datos['url'] ?? '');
$orden = filter_var($datos['orden'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El identificador no es válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

if ($orden === false || $orden === null || $orden < 0) {
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
        UPDATE redes_sociales
        SET
            plataforma = ?,
            url = ?,
            orden = ?
        WHERE id = ?
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        $plataforma,
        $url,
        $orden,
        $id
    ]);

    if ($consulta->rowCount() === 0) {

        $verificar = $conexion->prepare(
            "SELECT id FROM redes_sociales WHERE id = ?"
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
    }

    echo json_encode([
        'success' => true,
        'message' => 'Red social actualizada correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al actualizar red social: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar la red social.'
    ], JSON_UNESCAPED_UNICODE);
}