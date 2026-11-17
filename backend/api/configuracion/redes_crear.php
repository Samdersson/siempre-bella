
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

// Validar token CSRF
exigirTokenCSRF();

// Leer datos JSON
$datos = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($datos)) {
    responder(400, [
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ]);
}

// Validar tipos de datos
if (
    !isset($datos['plataforma']) ||
    !is_string($datos['plataforma']) ||
    !isset($datos['url']) ||
    !is_string($datos['url'])
) {
    responder(400, [
        'success' => false,
        'message' => 'Los datos de la red social no son válidos.'
    ]);
}

// Recibir información
$plataforma = trim($datos['plataforma']);
$url = trim($datos['url']);

$orden = filter_var(
    $datos['orden'] ?? 0,
    FILTER_VALIDATE_INT
);

// Validar plataforma
if (
    $plataforma === '' ||
    mb_strlen($plataforma) > 60
) {
    responder(400, [
        'success' => false,
        'message' => 'La plataforma es obligatoria y debe tener máximo 60 caracteres.'
    ]);
}

// Validar URL
if (
    $url === '' ||
    mb_strlen($url) > 500 ||
    !filter_var($url, FILTER_VALIDATE_URL) ||
    !in_array(
        strtolower((string) parse_url($url, PHP_URL_SCHEME)),
        ['http', 'https'],
        true
    )
) {
    responder(400, [
        'success' => false,
        'message' => 'Introduce una URL válida que comience por HTTP o HTTPS.'
    ]);
}

// Validar orden
if (
    $orden === false ||
    $orden === null ||
    $orden < 0
) {
    responder(400, [
        'success' => false,
        'message' => 'El orden debe ser un número entero igual o mayor que cero.'
    ]);
}

try {

    $database = new Database();
    $conexion = $database->connect();

    // Registrar nueva red social
    $sql = "
        INSERT INTO redes_sociales (
            plataforma,
            url,
            orden,
            activo
        ) VALUES (
            :plataforma,
            :url,
            :orden,
            1
        )
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'plataforma' => $plataforma,
        'url' => $url,
        'orden' => $orden
    ]);

    $redId = (int) $conexion->lastInsertId();

    responder(201, [
        'success' => true,
        'message' => 'Red social registrada correctamente.',
        'red_id' => $redId
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al crear red social: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'No fue posible registrar la red social.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al crear red social: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
