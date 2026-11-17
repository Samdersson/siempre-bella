
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

// 5. Validar identificadores
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$activo = filter_var(
    $datos['activo'] ?? null,
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

if (
    $activo === false ||
    $activo === null ||
    !in_array($activo, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El estado debe ser 0 o 1.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Cambiar estado del banner
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

    // Actualizar estado
    $actualizar = $conexion->prepare("
        UPDATE banners
        SET activo = :activo,
            fecha_actualizacion = NOW()
        WHERE id = :id
    ");

    $actualizar->execute([
        ':activo' => $activo,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Banner activado correctamente.'
            : 'Banner desactivado correctamente.',
        'banner_id' => $id,
        'activo' => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al cambiar estado del banner: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al cambiar el estado del banner.'
    ], JSON_UNESCAPED_UNICODE);
}
