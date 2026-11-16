
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Recibir información
$nombre = trim($datos['nombre_tienda'] ?? '');
$whatsapp = trim($datos['whatsapp'] ?? '');
$montoMayorista = $datos['monto_minimo_mayorista'] ?? null;
$moneda = strtoupper(trim($datos['moneda'] ?? ''));
$mensajeEnvio = trim($datos['mensaje_envio'] ?? '');

// Validar nombre
if ($nombre === '' || mb_strlen($nombre) > 150) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio y debe tener máximo 150 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar WhatsApp
if (mb_strlen($whatsapp) > 30) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El número de WhatsApp no puede superar 30 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar mensaje de envío
if (mb_strlen($mensajeEnvio) > 500) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El mensaje de envío no puede superar 500 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar monto mayorista
if ($montoMayorista === '') {
    $montoMayorista = null;
}

if ($montoMayorista !== null) {

    if (
        !is_numeric($montoMayorista) ||
        (float) $montoMayorista < 0 ||
        (float) $montoMayorista > 9999999999.99
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'El monto mínimo mayorista no es válido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $montoMayorista = number_format(
        (float) $montoMayorista,
        2,
        '.',
        ''
    );
}

// Validar moneda
if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La moneda debe contener un código de tres letras.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    // Actualizar configuración principal
    $sql = "
        UPDATE configuracion_tienda
        SET
            nombre_tienda = ?,
            whatsapp = ?,
            monto_minimo_mayorista = ?,
            moneda = ?,
            mensaje_envio = ?
        WHERE id = 1
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        $nombre,
        $whatsapp !== '' ? $whatsapp : null,
        $montoMayorista,
        $moneda,
        $mensajeEnvio !== '' ? $mensajeEnvio : null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración de la tienda actualizada correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al actualizar configuración: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar la configuración.'
    ], JSON_UNESCAPED_UNICODE);
}
