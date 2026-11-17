
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

// Validar método HTTP
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

    // Leer información JSON
    $contenido = file_get_contents('php://input');
    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        responder(400, [
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ]);
    }

    // Validar tipos de datos
    $camposTexto = [
        'nombre_tienda',
        'whatsapp',
        'moneda',
        'mensaje_envio'
    ];

    foreach ($camposTexto as $campo) {
        if (
            isset($datos[$campo]) &&
            !is_string($datos[$campo])
        ) {
            responder(400, [
                'success' => false,
                'message' => "El campo $campo no tiene un formato válido."
            ]);
        }
    }

    // Recibir información
    $nombre = trim($datos['nombre_tienda'] ?? '');
    $whatsapp = trim($datos['whatsapp'] ?? '');
    $moneda = strtoupper(trim($datos['moneda'] ?? ''));
    $mensajeEnvio = trim($datos['mensaje_envio'] ?? '');

    $montoMayorista = $datos['monto_minimo_mayorista'] ?? null;

    // Validar nombre de la tienda
    if ($nombre === '' || mb_strlen($nombre) > 150) {
        responder(400, [
            'success' => false,
            'message' => 'El nombre es obligatorio y debe tener máximo 150 caracteres.'
        ]);
    }

    // Validar WhatsApp
    if (mb_strlen($whatsapp) > 30) {
        responder(400, [
            'success' => false,
            'message' => 'El número de WhatsApp no puede superar 30 caracteres.'
        ]);
    }

    // Validar mensaje de envío
    if (mb_strlen($mensajeEnvio) > 500) {
        responder(400, [
            'success' => false,
            'message' => 'El mensaje de envío no puede superar 500 caracteres.'
        ]);
    }

    // Validar monto mínimo mayorista
    if ($montoMayorista === '') {
        $montoMayorista = null;
    }

    if ($montoMayorista !== null) {

        if (
            (!is_string($montoMayorista) &&
             !is_int($montoMayorista) &&
             !is_float($montoMayorista)) ||
            !is_numeric($montoMayorista) ||
            !is_finite((float)$montoMayorista) ||
            (float)$montoMayorista < 0 ||
            (float)$montoMayorista > 9999999999.99
        ) {
            responder(400, [
                'success' => false,
                'message' => 'El monto mínimo mayorista no es válido.'
            ]);
        }

        $montoMayorista = number_format(
            (float)$montoMayorista,
            2,
            '.',
            ''
        );
    }

    // Validar código de moneda
    if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
        responder(400, [
            'success' => false,
            'message' => 'La moneda debe contener un código de tres letras.'
        ]);
    }

    // Conectar base de datos
    $database = new Database();
    $conexion = $database->connect();

    // Actualizar configuración principal
    $sql = "
        UPDATE configuracion_tienda
        SET
            nombre_tienda = :nombre,
            whatsapp = :whatsapp,
            monto_minimo_mayorista = :monto,
            moneda = :moneda,
            mensaje_envio = :mensaje
        WHERE id = 1
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'nombre' => $nombre,
        'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
        'monto' => $montoMayorista,
        'moneda' => $moneda,
        'mensaje' => $mensajeEnvio !== '' ? $mensajeEnvio : null
    ]);

    responder(200, [
        'success' => true,
        'message' => 'Configuración de la tienda actualizada correctamente.'
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al actualizar configuración: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'No fue posible actualizar la configuración.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al actualizar configuración: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
