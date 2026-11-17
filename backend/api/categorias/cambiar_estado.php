
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Validar sesión administrativa
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
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Validar identificador y estado
$categoriaId = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$activo = filter_var(
    $datos['activo'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $categoriaId === false ||
    $categoriaId === null ||
    $categoriaId < 1
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificador de categoría no válido.'
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

// 6. Actualizar estado
try {

    $database = new Database();
    $conexion = $database->connect();

    // Comprobar que existe la categoría
    $consultaExiste = $conexion->prepare(
        "SELECT id
         FROM categorias
         WHERE id = ?
         LIMIT 1"
    );

    $consultaExiste->execute([$categoriaId]);

    if (!$consultaExiste->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'La categoría no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Actualizar estado
    $consulta = $conexion->prepare(
        "UPDATE categorias
         SET activo = ?
         WHERE id = ?"
    );

    $consulta->execute([
        $activo,
        $categoriaId
    ]);

    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Categoría activada correctamente.'
            : 'Categoría desactivada correctamente.',
        'categoria_id' => $categoriaId,
        'activo' => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al cambiar estado de categoría: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cambiar el estado de la categoría.'
    ], JSON_UNESCAPED_UNICODE);
}
