
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
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(400, [
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ]);
}

// Validar identificador
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    responder(400, [
        'success' => false,
        'message' => 'El identificador no es válido.'
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

$plataforma = trim($datos['plataforma']);
$url = trim($datos['url']);

$orden = filter_var(
    $datos['orden'] ?? null,
    FILTER_VALIDATE_INT
);

// Validar plataforma
if ($plataforma === '' || mb_strlen($plataforma) > 60) {
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
if ($orden === false || $orden === null || $orden < 0) {
    responder(400, [
        'success' => false,
        'message' => 'El orden debe ser un número entero igual o mayor que cero.'
    ]);
}

try {

    $database = new Database();
    $conexion = $database->connect();

    // Comprobar que la red social existe
    $verificar = $conexion->prepare(
        "SELECT id
         FROM redes_sociales
         WHERE id = ?
         LIMIT 1"
    );

    $verificar->execute([$id]);

    if (!$verificar->fetch(PDO::FETCH_ASSOC)) {
        responder(404, [
            'success' => false,
            'message' => 'La red social no existe.'
        ]);
    }

    // Actualizar red social
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

    responder(200, [
        'success' => true,
        'message' => 'Red social actualizada correctamente.'
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al actualizar red social: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'No fue posible actualizar la red social.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al actualizar red social: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
