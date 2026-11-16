
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    // Obtener información de la tienda
    $consultaTienda = $conexion->prepare("
        SELECT
            id,
            nombre_tienda,
            whatsapp,
            monto_minimo_mayorista,
            moneda,
            mensaje_envio,
            actualizado_en
        FROM configuracion_tienda
        WHERE id = 1
        LIMIT 1
    ");

    $consultaTienda->execute();

    $tienda = $consultaTienda->fetch(PDO::FETCH_ASSOC);

    if (!$tienda) {
        throw new RuntimeException(
            'No se encontró la configuración de la tienda.'
        );
    }

    // Obtener redes sociales
    $consultaRedes = $conexion->prepare("
        SELECT
            id,
            plataforma,
            url,
            orden,
            activo
        FROM redes_sociales
        WHERE activo = 1
        ORDER BY orden ASC, id ASC
    ");

    $consultaRedes->execute();

    $redes = $consultaRedes->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'tienda' => $tienda,
            'redes_sociales' => $redes
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al obtener configuración: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cargar la configuración.'
    ], JSON_UNESCAPED_UNICODE);
}
