
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
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

// 3. Validar token CSRF
exigirTokenCSRF();

// 4. Recibir y validar JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Datos no válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Validar identificador
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

// 6. Validar estado
$activo = filter_var(
    $datos['activo'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0 ||
    !in_array($activo, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificador o estado no válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion = null;
$transaccionActiva = false;

try {

    // 7. Conectar con la base de datos
    $database = new Database();
    $conexion = $database->connect();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // 8. Iniciar transacción
    $conexion->beginTransaction();
    $transaccionActiva = true;

    // 9. Comprobar que el recurso existe y bloquear su registro
    $consulta = $conexion->prepare("
        SELECT id, activo
        FROM recursos_multimedia
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $consulta->execute([$id]);

    $recurso = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$recurso) {
        $conexion->rollBack();
        $transaccionActiva = false;

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'El recurso multimedia no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 10. Evitar desactivar imágenes utilizadas por banners
    if ($activo === 0) {

        $consultaUso = $conexion->prepare("
            SELECT COUNT(*)
            FROM banners
            WHERE imagen_id = ?
               OR imagen_movil_id = ?
        ");

        $consultaUso->execute([$id, $id]);

        $usos = (int) $consultaUso->fetchColumn();

        if ($usos > 0) {
            $conexion->rollBack();
            $transaccionActiva = false;

            http_response_code(409);

            echo json_encode([
                'success' => false,
                'message' => 'No puedes desactivar esta imagen porque está asociada a un banner.',
                'usos' => $usos
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    // 11. Actualizar estado
    $actualizar = $conexion->prepare("
        UPDATE recursos_multimedia
        SET activo = ?
        WHERE id = ?
    ");

    $actualizar->execute([$activo, $id]);

    // 12. Confirmar transacción
    $conexion->commit();
    $transaccionActiva = false;

    // 13. Respuesta
    echo json_encode([
        'success' => true,
        'message' => $activo === 1
            ? 'Recurso multimedia activado correctamente.'
            : 'Recurso multimedia desactivado correctamente.',
        'id' => $id,
        'activo' => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if (
        $conexion instanceof PDO &&
        $transaccionActiva &&
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    error_log(
        'Error al cambiar estado del recurso multimedia: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cambiar el estado del recurso.'
    ], JSON_UNESCAPED_UNICODE);
}
