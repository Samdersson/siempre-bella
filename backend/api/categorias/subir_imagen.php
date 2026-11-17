
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Validar sesión administrativa
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

// 4. Validar categoría e imagen
$categoriaId = filter_var(
    $_POST['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $categoriaId === false ||
    $categoriaId === null ||
    $categoriaId < 1 ||
    !isset($_FILES['imagen']) ||
    !is_array($_FILES['imagen'])
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Selecciona una categoría y una imagen válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES['imagen'];

// 5. Validar recepción del archivo
if (
    !isset($archivo['error'], $archivo['tmp_name'], $archivo['size']) ||
    $archivo['error'] !== UPLOAD_ERR_OK
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible recibir la imagen.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    !is_uploaded_file($archivo['tmp_name']) ||
    $archivo['size'] <= 0
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El archivo recibido no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Configuración
$tiposPermitidos = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

$pesoMaximo = 5 * 1024 * 1024;
$pixelesMaximos = 25000000;
$anchoMaximo = 1600;
$altoMaximo = 1600;
$calidadWebp = 78;

if ($archivo['size'] > $pesoMaximo) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La imagen debe pesar máximo 5 MB.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 7. Preparar directorio
$directorio = __DIR__ . '/../../uploads/categorias/';

if (!is_dir($directorio)) {
    if (!mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'No fue posible preparar el almacenamiento.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

if (!is_writable($directorio)) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'La carpeta de imágenes no tiene permisos de escritura.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$nuevoArchivo = null;
$imagenOriginal = null;
$imagenFinal = null;

try {

    // 8. Verificar GD y soporte WEBP
    if (
        !extension_loaded('gd') ||
        !function_exists('imagewebp') ||
        !function_exists('imagecreatefromstring')
    ) {
        throw new RuntimeException(
            'El servidor no tiene habilitada la conversión WEBP.'
        );
    }

    // 9. Comprobar formato real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);

    if (!in_array($mime, $tiposPermitidos, true)) {
        throw new InvalidArgumentException(
            'Solo se permiten imágenes JPG, PNG o WEBP.'
        );
    }

    // 10. Comprobar dimensiones
    $informacion = getimagesize($archivo['tmp_name']);

    if ($informacion === false) {
        throw new InvalidArgumentException(
            'La imagen no es válida.'
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

    // 11. Cargar imagen original
    $contenido = file_get_contents($archivo['tmp_name']);

    if ($contenido === false) {
        throw new RuntimeException(
            'No fue posible leer la imagen.'
        );
    }

    $imagenOriginal = imagecreatefromstring($contenido);

    if ($imagenOriginal === false) {
        throw new RuntimeException(
            'No fue posible procesar la imagen.'
        );
    }

    // 12. Calcular dimensiones conservando proporciones
    $escala = min(
        1,
        $anchoMaximo / $anchoOriginal,
        $altoMaximo / $altoOriginal
    );

    $nuevoAncho = max(
        1,
        (int)round($anchoOriginal * $escala)
    );

    $nuevoAlto = max(
        1,
        (int)round($altoOriginal * $escala)
    );

    $imagenFinal = imagecreatetruecolor(
        $nuevoAncho,
        $nuevoAlto
    );

    if ($imagenFinal === false) {
        throw new RuntimeException(
            'No fue posible preparar la imagen.'
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

    // 13. Generar archivo WEBP
    $nombreArchivo = bin2hex(random_bytes(16)) . '.webp';

    $nuevoArchivo = $directorio . $nombreArchivo;

    $convertida = imagewebp(
        $imagenFinal,
        $nuevoArchivo,
        $calidadWebp
    );

    imagedestroy($imagenOriginal);
    $imagenOriginal = null;

    imagedestroy($imagenFinal);
    $imagenFinal = null;

    if (
        !$convertida ||
        !file_exists($nuevoArchivo) ||
        filesize($nuevoArchivo) === 0
    ) {
        throw new RuntimeException(
            'No fue posible convertir la imagen a WEBP.'
        );
    }

    // 14. Conectar con MariaDB
    $database = new Database();
    $conexion = $database->connect();

    // Verificar categoría
    $consultaCategoria = $conexion->prepare(
        "SELECT id, imagen
         FROM categorias
         WHERE id = ?
         LIMIT 1"
    );

    $consultaCategoria->execute([$categoriaId]);

    $categoria = $consultaCategoria->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        throw new InvalidArgumentException(
            'La categoría seleccionada no existe.'
        );
    }

    $imagenAnterior = $categoria['imagen'];

    // 15. Actualizar ruta en base de datos
    $ruta = 'uploads/categorias/' . $nombreArchivo;

    $actualizar = $conexion->prepare(
        "UPDATE categorias
         SET imagen = ?
         WHERE id = ?"
    );

    $actualizar->execute([
        $ruta,
        $categoriaId
    ]);

    // 16. Eliminar imagen anterior si ya no se utiliza
    if (
        !empty($imagenAnterior) &&
        $imagenAnterior !== $ruta &&
        str_starts_with($imagenAnterior, 'uploads/categorias/')
    ) {
        $consultaUso = $conexion->prepare(
            "SELECT COUNT(*)
             FROM categorias
             WHERE imagen = ?"
        );

        $consultaUso->execute([$imagenAnterior]);

        if ((int)$consultaUso->fetchColumn() === 0) {

            $nombreAnterior = basename($imagenAnterior);

            $rutaAnterior = realpath(
                $directorio . $nombreAnterior
            );

            $directorioReal = realpath($directorio);

            if (
                $rutaAnterior !== false &&
                $directorioReal !== false &&
                dirname($rutaAnterior) === $directorioReal &&
                is_file($rutaAnterior)
            ) {
                unlink($rutaAnterior);
            }
        }
    }

    // La imagen ya está registrada correctamente
    $nuevoArchivo = null;

    echo json_encode([
        'success' => true,
        'message' => 'Imagen de categoría actualizada correctamente.',
        'imagen' => $ruta,
        'formato' => 'WEBP',
        'ancho' => $nuevoAncho,
        'alto' => $nuevoAlto
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al subir imagen de categoría: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible procesar la imagen de categoría.'
    ], JSON_UNESCAPED_UNICODE);

} finally {

    // Liberar recursos GD si ocurrió un error
    if ($imagenOriginal !== null) {
        imagedestroy($imagenOriginal);
    }

    if ($imagenFinal !== null) {
        imagedestroy($imagenFinal);
    }

    // Eliminar archivo generado si no se completó el proceso
    if (
        $nuevoArchivo !== null &&
        file_exists($nuevoArchivo)
    ) {
        unlink($nuevoArchivo);
    }
}
