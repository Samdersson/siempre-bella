
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

// 3. Verificar archivo recibido
if (!isset($_FILES['archivo'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Selecciona una imagen.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES['archivo'];

if (is_array($archivo['name'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Solo puedes cargar una imagen por solicitud.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible recibir la imagen.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 4. Configuración
$tiposPermitidos = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

$extensionesPermitidas = [
    'jpg',
    'jpeg',
    'png',
    'webp'
];

$pesoMaximo = 5 * 1024 * 1024;
$pixelesMaximos = 25000000;

$anchoMaximo = 1600;
$altoMaximo = 1600;
$calidadWebp = 78;

// 5. Validar tamaño
if (
    $archivo['size'] <= 0 ||
    $archivo['size'] > $pesoMaximo
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La imagen debe pesar máximo 5 MB.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Validar archivo temporal
$archivoTemporal = $archivo['tmp_name'];

if (!is_uploaded_file($archivoTemporal)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Archivo inválido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion = null;
$destino = null;
$imagenOriginal = null;
$imagenFinal = null;

try {

    // 7. Comprobar formato real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivoTemporal);

    if (!in_array($mime, $tiposPermitidos, true)) {
        throw new InvalidArgumentException(
            'Formato no permitido. Utiliza JPG, PNG o WEBP.'
        );
    }

    // 8. Validar extensión original
    $extensionOriginal = strtolower(
        pathinfo($archivo['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extensionOriginal, $extensionesPermitidas, true)) {
        throw new InvalidArgumentException(
            'La extensión del archivo no es válida.'
        );
    }

    // 9. Comprobar dimensiones
    $informacion = getimagesize($archivoTemporal);

    if ($informacion === false) {
        throw new InvalidArgumentException(
            'El archivo no contiene una imagen válida.'
        );
    }

    $anchoOriginal = $informacion[0];
    $altoOriginal = $informacion[1];

    if (
        $anchoOriginal <= 0 ||
        $altoOriginal <= 0 ||
        $anchoOriginal * $altoOriginal > $pixelesMaximos
    ) {
        throw new InvalidArgumentException(
            'La imagen tiene dimensiones demasiado grandes.'
        );
    }

    // 10. Crear imagen desde el archivo original
    $contenido = file_get_contents($archivoTemporal);

    if ($contenido === false) {
        throw new RuntimeException(
            'No fue posible leer la imagen.'
        );
    }

    $imagenOriginal = imagecreatefromstring($contenido);

    unset($contenido);

    if ($imagenOriginal === false) {
        throw new InvalidArgumentException(
            'No fue posible procesar la imagen.'
        );
    }

    // 11. Calcular dimensiones proporcionales
    $escala = min(
        1,
        $anchoMaximo / $anchoOriginal,
        $altoMaximo / $altoOriginal
    );

    $nuevoAncho = max(
        1,
        (int) round($anchoOriginal * $escala)
    );

    $nuevoAlto = max(
        1,
        (int) round($altoOriginal * $escala)
    );

    $imagenFinal = imagecreatetruecolor(
        $nuevoAncho,
        $nuevoAlto
    );

    if ($imagenFinal === false) {
        throw new RuntimeException(
            'No fue posible preparar la imagen optimizada.'
        );
    }

    // Conservar transparencia
    imagealphablending($imagenFinal, false);
    imagesavealpha($imagenFinal, true);

    $transparente = imagecolorallocatealpha(
        $imagenFinal,
        0,
        0,
        0,
        127
    );

    imagefill($imagenFinal, 0, 0, $transparente);

    imagecopyresampled(
        $imagenFinal,
        $imagenOriginal,
        0,
        0,
        0,
        0,
        $nuevoAncho,
        $nuevoAlto,
        $anchoOriginal,
        $altoOriginal
    );

    // 12. Preparar directorio
    $directorio = __DIR__ . '/../../uploads/multimedia/';

    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0755, true)) {
            throw new RuntimeException(
                'No fue posible crear la carpeta multimedia.'
            );
        }
    }

    if (!is_writable($directorio)) {
        throw new RuntimeException(
            'La carpeta multimedia no tiene permisos de escritura.'
        );
    }

    // 13. Generar nombre único
    $nombreOriginal = basename($archivo['name']);
    $nombreArchivo = bin2hex(random_bytes(16)) . '.webp';

    $destino = $directorio . $nombreArchivo;

    // 14. Convertir una sola vez a WEBP
    $convertida = imagewebp(
        $imagenFinal,
        $destino,
        $calidadWebp
    );

    imagedestroy($imagenOriginal);
    $imagenOriginal = null;

    imagedestroy($imagenFinal);
    $imagenFinal = null;

    if (
        !$convertida ||
        !is_file($destino) ||
        filesize($destino) <= 0
    ) {
        throw new RuntimeException(
            'No fue posible convertir la imagen a WEBP.'
        );
    }

    // 15. Preparar datos del recurso
    $rutaRelativa = 'uploads/multimedia/' . $nombreArchivo;

    $tamanoFinal = filesize($destino);

    $textoAlternativo = trim(
        $_POST['texto_alternativo'] ?? ''
    );

    if (mb_strlen($textoAlternativo, 'UTF-8') > 255) {
        throw new InvalidArgumentException(
            'El texto alternativo no puede superar 255 caracteres.'
        );
    }


    // 16. Registrar en la base de datos
    $database = new Database();
    $conexion = $database->connect();

    $conexion->beginTransaction();

    $consulta = $conexion->prepare(
        "INSERT INTO recursos_multimedia (
            nombre_original,
            nombre_archivo,
            ruta_archivo,
            tipo_mime,
            extension,
            tamanio_bytes,
            texto_alternativo,
            activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );

    $consulta->execute([
        $nombreOriginal,
        $nombreArchivo,
        $rutaRelativa,
        'image/webp',
        'webp',
        $tamanoFinal,
        $textoAlternativo !== '' ? $textoAlternativo : null
    ]);

    $recursoId = (int) $conexion->lastInsertId();

    $conexion->commit();

    // 17. Respuesta
    echo json_encode([
        'success' => true,
        'message' => 'Imagen cargada y optimizada correctamente.',
        'data' => [
            'id' => $recursoId,
            'nombre_original' => $nombreOriginal,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaRelativa,
            'url' => '/' . $rutaRelativa,
            'tipo_mime' => 'image/webp',
            'extension' => 'webp',
            'tamano_bytes' => $tamanoFinal,
            'ancho' => $nuevoAncho,
            'alto' => $nuevoAlto
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    if ($conexion && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    if ($destino && file_exists($destino)) {
        unlink($destino);
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($conexion && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    if ($destino && file_exists($destino)) {
        unlink($destino);
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible guardar la imagen multimedia.'
    ], JSON_UNESCAPED_UNICODE);

} finally {

    if ($imagenOriginal !== null) {
        imagedestroy($imagenOriginal);
    }

    if ($imagenFinal !== null) {
        imagedestroy($imagenFinal);
    }
}
