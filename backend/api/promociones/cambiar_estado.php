
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

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

// Validar token CSRF
exigirTokenCSRF();

try {

    $database = new Database();
    $pdo = $database->connect();

    // Leer datos JSON
    $datos = json_decode(
        file_get_contents('php://input'),
        true
    );

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
            'message' => 'El identificador de la promoción no es válido.'
        ]);
    }

    // Validar estado
    $activo = $datos['activo'] ?? null;

    if (!in_array($activo, [0, 1, '0', '1'], true)) {
        responder(400, [
            'success' => false,
            'message' => 'El estado seleccionado no es válido.'
        ]);
    }

    $activo = (int) $activo;

    // Comprobar existencia de la promoción
    $consulta = $pdo->prepare(
        "SELECT id
         FROM promociones
         WHERE id = :id
         LIMIT 1"
    );

    $consulta->execute([
        'id' => $id
    ]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        responder(404, [
            'success' => false,
            'message' => 'La promoción no existe.'
        ]);
    }

    // Actualizar estado
    $stmt = $pdo->prepare(
        "UPDATE promociones
         SET activo = :activo
         WHERE id = :id"
    );

    $stmt->execute([
        'activo' => $activo,
        'id' => $id
    ]);

    responder(200, [
        'success' => true,
        'message' => $activo === 1
            ? 'Promoción activada correctamente.'
            : 'Promoción desactivada correctamente.',
        'promocion_id' => $id,
        'activo' => $activo
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al cambiar estado de promoción: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Error al cambiar el estado de la promoción.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al cambiar estado de promoción: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
