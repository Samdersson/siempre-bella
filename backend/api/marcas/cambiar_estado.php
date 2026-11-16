
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->connect();

    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ]);
        exit;
    }

    $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);
    $activo = $datos['activo'] ?? null;

    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El identificador de la marca no es válido.'
        ]);
        exit;
    }

    if (!in_array($activo, [0, 1, '0', '1'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El estado seleccionado no es válido.'
        ]);
        exit;
    }

    $activo = (int)$activo;

    // Comprobar que la marca existe
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas WHERE id = :id LIMIT 1"
    );

    $consulta->execute(['id' => $id]);

    if (!$consulta->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La marca no existe.'
        ]);
        exit;
    }

    // Actualizar estado
    $stmt = $pdo->prepare(
        "UPDATE marcas SET activo = :activo WHERE id = :id"
    );

    $stmt->execute([
        'activo' => $activo,
        'id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Marca activada correctamente.'
            : 'Marca desactivada correctamente.',
        'marca_id' => $id,
        'activo' => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cambiar el estado de la marca.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
