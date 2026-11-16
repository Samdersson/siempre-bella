
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
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Datos JSON inválidos.'
        ]);
        exit;
    }

    $id = filter_var(
        $datos['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El ID del banner no es válido.'
        ]);
        exit;
    }

    $database = new Database();
    $conexion = $database->connect();

    $consulta = $conexion->prepare("
        SELECT id, nombre
        FROM banners
        WHERE id = :id
        LIMIT 1
    ");

    $consulta->execute([':id' => $id]);

    $banner = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$banner) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El banner no existe.'
        ]);
        exit;
    }

    $eliminar = $conexion->prepare("
        DELETE FROM banners
        WHERE id = :id
    ");

    $eliminar->execute([':id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Banner eliminado correctamente.',
        'banner_id' => $id
    ]);

} catch (PDOException $e) {
    error_log('Error al eliminar banner: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible eliminar el banner. Verifica si existen restricciones relacionadas.'
    ]);
}
