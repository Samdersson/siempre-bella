
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// 1. Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Validar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 3. Recibir datos
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$categoriaId = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$activo = filter_var(
    $datos['activo'] ?? null,
    FILTER_VALIDATE_INT
);

// 4. Validar identificador y estado
if (!$categoriaId || $categoriaId < 1) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificador de categoría no válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!in_array($activo, [0, 1], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El estado debe ser 0 o 1.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    // 5. Comprobar que existe la categoría
    $consultaExiste = $conexion->prepare(
        "SELECT id FROM categorias WHERE id = ?"
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

    // 6. Actualizar estado
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
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cambiar el estado de la categoría.'
    ], JSON_UNESCAPED_UNICODE);
}
