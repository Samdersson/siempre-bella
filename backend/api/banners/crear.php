
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// Verificar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Recibir datos JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Obtener y limpiar datos
$nombre = trim($datos['nombre'] ?? '');
$titulo = trim($datos['titulo'] ?? '');
$subtitulo = trim($datos['subtitulo'] ?? '');

$imagenId = filter_var(
    $datos['imagen_id'] ?? null,
    FILTER_VALIDATE_INT
);

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

$textoBoton = trim($datos['texto_boton'] ?? '');
$enlaceBoton = trim($datos['enlace_boton'] ?? '');

$posicion = $datos['posicion'] ?? 'principal';

$orden = filter_var(
    $datos['orden'] ?? null,
    FILTER_VALIDATE_INT
);

$fechaInicio = $datos['fecha_inicio'] ?? null;
$fechaFin = $datos['fecha_fin'] ?? null;

// Normalizar fechas vacías
$fechaInicio = ($fechaInicio === '') ? null : $fechaInicio;
$fechaFin = ($fechaFin === '') ? null : $fechaFin;

// Validar nombre
if ($nombre === '' || mb_strlen($nombre) > 150) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio y debe tener máximo 150 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar título
if ($titulo !== '' && mb_strlen($titulo) > 200) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El título no puede superar 200 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar texto del botón
if ($textoBoton !== '' && mb_strlen($textoBoton) > 100) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El texto del botón no puede superar 100 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar enlace
if ($enlaceBoton !== '' && mb_strlen($enlaceBoton) > 500) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El enlace no puede superar 500 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar imagen principal
if (!$imagenId || $imagenId <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Debes seleccionar una imagen principal.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar imagen móvil
if (
    $imagenMovilId !== null &&
    (!$imagenMovilId || $imagenMovilId <= 0)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La imagen móvil seleccionada no es válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar posición
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

// Validar orden
if ($orden === false || $orden === null) {
    $orden = null;
} elseif ($orden < 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El orden no puede ser negativo.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar formato de fechas
function validarFechaBanner($fecha): bool
{
    if ($fecha === null) {
        return true;
    }

    if (!is_string($fecha)) {
        return false;
    }

    $formato = DateTime::createFromFormat(
        '!Y-m-d H:i:s',
        $fecha
    );

    return $formato !== false &&
        $formato->format('Y-m-d H:i:s') === $fecha;
}

if (
    !validarFechaBanner($fechaInicio) ||
    !validarFechaBanner($fechaFin)
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

try {

    $database = new Database();
    $conexion = $database->connect();

    // Verificar que la imagen principal exista y esté activa
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

    // Verificar imagen móvil cuando se haya seleccionado
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

    // Asignar orden automáticamente por posición
    if ($orden === null) {

        $consultaOrden = $conexion->prepare(
            "SELECT COALESCE(MAX(orden), -1) + 1
             FROM banners
             WHERE posicion = ?"
        );

        $consultaOrden->execute([$posicion]);

        $orden = (int)$consultaOrden->fetchColumn();
    }

    // Registrar banner
    $sql = "
        INSERT INTO banners (
            nombre,
            titulo,
            subtitulo,
            imagen_id,
            imagen_movil_id,
            texto_boton,
            enlace_boton,
            posicion,
            orden,
            activo,
            fecha_inicio,
            fecha_fin
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?
        )
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
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
        $fechaFin
    ]);

    $bannerId = (int)$conexion->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Banner creado correctamente.',
        'banner_id' => $bannerId,
        'orden' => $orden
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
        'message' => 'No fue posible crear el banner.'
    ], JSON_UNESCAPED_UNICODE);
}
