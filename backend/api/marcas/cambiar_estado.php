
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión administrativa
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

// Validar token CSRF
exigirTokenCSRF();

try {
    $database = new Database();
    $pdo = $database->connect();

    // Leer JSON
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar identificador
    if (
        !isset($datos['id']) ||
        !filter_var($datos['id'], FILTER_VALIDATE_INT) ||
        (int)$datos['id'] <= 0
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El identificador de la marca no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar estado
    $activo = $datos['activo'] ?? null;

    if (!in_array($activo, [0, 1, '0', '1'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El estado seleccionado no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int)$datos['id'];
    $activo = (int)$activo;

    // Comprobar que la marca existe
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas WHERE id = :id LIMIT 1"
    );

    $consulta->execute(['id' => $id]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La marca no existe.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Actualizar estado
    $stmt = $pdo->prepare(
        "UPDATE marcas
         SET activo = :activo
         WHERE id = :id"
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

    error_log('Error al cambiar estado de marca: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cambiar el estado de la marca.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('Error inesperado al cambiar estado de marca: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ], JSON_UNESCAPED_UNICODE);
}
