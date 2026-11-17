
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Verificar sesión administrativa
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    !isset($_SESSION['admin']['id'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado. Inicia sesión nuevamente.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 3. Validar token CSRF
exigirTokenCSRF();

// 4. Recibir datos JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Datos JSON inválidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Validar identificador
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El ID del banner no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Eliminar banner
try {

    $database = new Database();
    $conexion = $database->connect();

    // Verificar que el banner exista
    $consulta = $conexion->prepare("
        SELECT id, nombre
        FROM banners
        WHERE id = :id
        LIMIT 1
    ");

    $consulta->execute([
        ':id' => $id
    ]);

    $banner = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$banner) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'El banner no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Eliminar banner
    $eliminar = $conexion->prepare("
        DELETE FROM banners
        WHERE id = :id
    ");

    $eliminar->execute([
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Banner eliminado correctamente.',
        'banner_id' => $id
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log(
        'Error al eliminar banner: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible eliminar el banner. Verifica si existen restricciones relacionadas.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al eliminar banner: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado al eliminar el banner.'
    ], JSON_UNESCAPED_UNICODE);
}
