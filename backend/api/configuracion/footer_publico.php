
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Este endpoint es público y utiliza el proxy de Vite.
// No necesita autenticación ni token CSRF.

// Permitir únicamente solicitudes GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {

    $database = new Database();
    $conexion = $database->connect();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // Información general de la tienda
    $consultaTienda = $conexion->prepare("
        SELECT
            nombre_tienda,
            whatsapp,
            monto_minimo_mayorista,
            moneda,
            mensaje_envio
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

    // Obtener únicamente las redes sociales activas
    $consultaRedes = $conexion->prepare("
        SELECT
            plataforma,
            url,
            orden
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
            'redes' => $redes
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        'Error en footer_publico.php: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener la configuración de la tienda.'
    ], JSON_UNESCAPED_UNICODE);
}
