
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// 1. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Verificar sesión administrativa
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
        'message' => 'Datos no válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$activo = filter_var(
    $datos['activo'] ?? null,
    FILTER_VALIDATE_INT
);

// 4. Validar identificadores y estado
if (!$id || $id <= 0 || !in_array($activo, [0, 1], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificador o estado no válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    // 5. Comprobar que el recurso existe
    $consulta = $conexion->prepare(
        "SELECT id, activo
         FROM recursos_multimedia
         WHERE id = ?
         LIMIT 1"
    );

    $consulta->execute([$id]);

    $recurso = $consulta->fetch();

    if (!$recurso) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'El recurso multimedia no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 6. Evitar desactivar imágenes utilizadas por banners
    if ($activo === 0) {

        $consultaUso = $conexion->prepare(
            "SELECT COUNT(*)
             FROM banners
             WHERE imagen_id = ?
                OR imagen_movil_id = ?"
        );

        $consultaUso->execute([$id, $id]);

        $usos = (int)$consultaUso->fetchColumn();

        if ($usos > 0) {
            http_response_code(409);

            echo json_encode([
                'success' => false,
                'message' => 'No puedes desactivar esta imagen porque está asociada a un banner.',
                'usos' => $usos
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    // 7. Actualizar estado
    $actualizar = $conexion->prepare(
        "UPDATE recursos_multimedia
         SET activo = ?
         WHERE id = ?"
    );

    $actualizar->execute([$activo, $id]);

    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Recurso multimedia activado correctamente.'
            : 'Recurso multimedia desactivado correctamente.',
        'id' => $id,
        'activo' => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cambiar el estado del recurso.'
    ], JSON_UNESCAPED_UNICODE);
}
