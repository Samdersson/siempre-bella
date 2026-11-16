
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// 1. Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Validar sesión
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 3. Validar categoría
$categoriaId = filter_var(
    $_POST['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$categoriaId || $categoriaId < 1 || !isset($_FILES['imagen'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Selecciona una categoría y una imagen.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES['imagen'];

// 4. Validar recepción del archivo
if ($archivo['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible recibir la imagen.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!is_uploaded_file($archivo['tmp_name'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Archivo inválido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Configuración
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

// 6. Preparar directorio
$directorio = __DIR__ . '/../../uploads/categorias/';

if (!is_dir($directorio)) {
    if (!mkdir($directorio, 0755, true)) {
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
$conexion = null;

try {

    // 7. Verificar GD y soporte WEBP
    if (
        !extension_loaded('gd') ||
        !function_exists('imagewebp') ||
        !function_exists('imagecreatefromstring')
    ) {
        throw new RuntimeException(
            'El servidor no tiene habilitada la conversión WEBP.'
        );
    }

    // 8. Comprobar formato real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);

    if (!in_array($mime, $tiposPermitidos, true)) {
        throw new InvalidArgumentException(
            'Solo se permiten imágenes JPG, PNG o WEBP.'
        );
    }

    // 9. Comprobar dimensiones
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

    // 10. Cargar imagen original
    $imagenOriginal = imagecreatefromstring(
        file_get_contents($archivo['tmp_name'])
    );

    if ($imagenOriginal === false) {
        throw new RuntimeException(
            'No fue posible procesar la imagen.'
        );
    }

    // 11. Calcular dimensiones conservando proporciones
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

    // 12. Generar archivo WEBP
    $nombreArchivo = bin2hex(random_bytes(16)) . '.webp';

    $nuevoArchivo = $directorio . $nombreArchivo;

    $convertida = imagewebp(
        $imagenFinal,
        $nuevoArchivo,
        $calidadWebp
    );

    imagedestroy($imagenOriginal);
    imagedestroy($imagenFinal);

    if (
        !$convertida ||
        !file_exists($nuevoArchivo) ||
        filesize($nuevoArchivo) === 0
    ) {
        throw new RuntimeException(
            'No fue posible convertir la imagen a WEBP.'
        );
    }

    // 13. Conectar con MariaDB
    $database = new Database();
    $conexion = $database->connect();

    $consultaCategoria = $conexion->prepare(
        "SELECT id, imagen
         FROM categorias
         WHERE id = ?"
    );

    $consultaCategoria->execute([$categoriaId]);

    $categoria = $consultaCategoria->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        throw new InvalidArgumentException(
            'La categoría seleccionada no existe.'
        );
    }

    $imagenAnterior = $categoria['imagen'];

    // 14. Actualizar ruta en base de datos
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

    // 15. Eliminar imagen anterior si ya no se utiliza
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

    echo json_encode([
        'success' => true,
        'message' => 'Imagen de categoría actualizada correctamente.',
        'imagen' => $ruta,
        'formato' => 'WEBP',
        'ancho' => $nuevoAncho,
        'alto' => $nuevoAlto
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    if ($nuevoArchivo && file_exists($nuevoArchivo)) {
        unlink($nuevoArchivo);
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($nuevoArchivo && file_exists($nuevoArchivo)) {
        unlink($nuevoArchivo);
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible procesar la imagen de categoría.'
    ], JSON_UNESCAPED_UNICODE);
}
