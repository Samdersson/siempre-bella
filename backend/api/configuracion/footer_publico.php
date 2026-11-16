
<?php

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://127.0.0.1:5173');
header('Access-Control-Allow-Credentials: true');

try {
    $database = new Database();
    $conexion = $database->connect();

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
        throw new Exception('No se encontró la configuración de la tienda.');
    }

    // Redes sociales activas
    $consultaRedes = $conexion->prepare("
        SELECT plataforma, url, orden
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
    error_log('Error en footer_publico.php: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible obtener la configuración de la tienda.'
    ], JSON_UNESCAPED_UNICODE);
}
