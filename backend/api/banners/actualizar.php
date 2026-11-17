
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Verificar sesión administrativa
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

// 3. Validar token CSRF
exigirTokenCSRF();

// 4. Recibir datos JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Validar identificadores
$id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);
$imagenId = filter_var($datos['imagen_id'] ?? null, FILTER_VALIDATE_INT);

$imagenMovilId = null;

if (
    isset($datos['imagen_movil_id']) &&
    $datos['imagen_movil_id'] !== '' &&
    $datos['imagen_movil_id'] !== null
) {
    $imagenMovilId = filter_var(
        $datos['imagen_movil_id'],
        FILTER_VALIDATE_INT
    );
}

if (
    $id === false || $id === null || $id <= 0 ||
    $imagenId === false || $imagenId === null || $imagenId <= 0
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El identificador del banner o de la imagen no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    $imagenMovilId !== null &&
    ($imagenMovilId === false || $imagenMovilId <= 0)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La imagen móvil seleccionada no es válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Obtener datos del banner
$nombre = trim($datos['nombre'] ?? '');
$titulo = trim($datos['titulo'] ?? '');
$subtitulo = trim($datos['subtitulo'] ?? '');
$textoBoton = trim($datos['texto_boton'] ?? '');
$enlaceBoton = trim($datos['enlace_boton'] ?? '');
$posicion = $datos['posicion'] ?? '';

$fechaInicio = $datos['fecha_inicio'] ?? null;
$fechaFin = $datos['fecha_fin'] ?? null;

$fechaInicio = ($fechaInicio === '') ? null : $fechaInicio;
$fechaFin = ($fechaFin === '') ? null : $fechaFin;

// 7. Validar campos de texto
if ($nombre === '' || mb_strlen($nombre) > 150) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio y debe tener máximo 150 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (mb_strlen($titulo) > 200 || mb_strlen($subtitulo) > 500) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El título o subtítulo supera la longitud permitida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (mb_strlen($textoBoton) > 100 || mb_strlen($enlaceBoton) > 500) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El texto o enlace del botón supera la longitud permitida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 8. Validar posición
$posicionesPermitidas = [
    'principal',
    'secundario',
    'promocional'
];

if (!in_array($posicion, $posicionesPermitidas, true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La posición seleccionada no es válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 9. Validar orden
$orden = filter_var($datos['orden'] ?? null, FILTER_VALIDATE_INT);

if ($orden === false || $orden === null || $orden < 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El orden debe ser un número entero igual o mayor que cero.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 10. Validar fechas
function validarFechaActualizacionBanner($fecha): bool
{
    if ($fecha === null) {
        return true;
    }

    if (!is_string($fecha)) {
        return false;
    }

    $formato = DateTime::createFromFormat('!Y-m-d H:i:s', $fecha);

    return $formato !== false &&
        $formato->format('Y-m-d H:i:s') === $fecha;
}

if (
    !validarFechaActualizacionBanner($fechaInicio) ||
    !validarFechaActualizacionBanner($fechaFin)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Las fechas deben tener el formato YYYY-MM-DD HH:MM:SS.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    $fechaInicio !== null &&
    $fechaFin !== null &&
    strtotime($fechaFin) <= strtotime($fechaInicio)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La fecha final debe ser posterior a la fecha inicial.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 11. Actualizar banner
try {

    $database = new Database();
    $conexion = $database->connect();

    // Verificar que el banner exista
    $consultaBanner = $conexion->prepare(
        "SELECT id
         FROM banners
         WHERE id = ?
         LIMIT 1"
    );

    $consultaBanner->execute([$id]);

    if (!$consultaBanner->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'El banner no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Verificar imagen principal activa
    $consultaImagen = $conexion->prepare(
        "SELECT id
         FROM recursos_multimedia
         WHERE id = ? AND activo = 1
         LIMIT 1"
    );

    $consultaImagen->execute([$imagenId]);

    if (!$consultaImagen->fetch()) {
        throw new InvalidArgumentException(
            'La imagen principal no existe o está inactiva.'
        );
    }

    // Verificar imagen móvil
    if ($imagenMovilId !== null) {

        if ($imagenMovilId === $imagenId) {
            throw new InvalidArgumentException(
                'La imagen móvil debe ser diferente de la imagen principal.'
            );
        }

        $consultaMovil = $conexion->prepare(
            "SELECT id
             FROM recursos_multimedia
             WHERE id = ? AND activo = 1
             LIMIT 1"
        );

        $consultaMovil->execute([$imagenMovilId]);

        if (!$consultaMovil->fetch()) {
            throw new InvalidArgumentException(
                'La imagen móvil no existe o está inactiva.'
            );
        }
    }

    // Actualizar banner sin modificar su estado activo
    $sql = "
        UPDATE banners
        SET
            nombre = ?,
            titulo = ?,
            subtitulo = ?,
            imagen_id = ?,
            imagen_movil_id = ?,
            texto_boton = ?,
            enlace_boton = ?,
            posicion = ?,
            orden = ?,
            fecha_inicio = ?,
            fecha_fin = ?
        WHERE id = ?
    ";

    $actualizar = $conexion->prepare($sql);

    $actualizar->execute([
        $nombre,
        $titulo !== '' ? $titulo : null,
        $subtitulo !== '' ? $subtitulo : null,
        $imagenId,
        $imagenMovilId,
        $textoBoton !== '' ? $textoBoton : null,
        $enlaceBoton !== '' ? $enlaceBoton : null,
        $posicion,
        $orden,
        $fechaInicio,
        $fechaFin,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Banner actualizado correctamente.',
        'banner_id' => $id
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar el banner.'
    ], JSON_UNESCAPED_UNICODE);
}
