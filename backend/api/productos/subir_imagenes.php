
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

// 4. Validar producto
$productoId = filter_var(
    $_POST['producto_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$productoId || $productoId <= 0 || !isset($_FILES['imagenes'])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Selecciona un producto y sus fotografías.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$imagenes = $_FILES['imagenes'];

if (
    !isset($imagenes['name']) ||
    !is_array($imagenes['name']) ||
    !isset($imagenes['tmp_name']) ||
    !is_array($imagenes['tmp_name']) ||
    !isset($imagenes['error']) ||
    !is_array($imagenes['error']) ||
    !isset($imagenes['size']) ||
    !is_array($imagenes['size'])
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Formato de archivos inválido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$total = count($imagenes['name']);

if (
    $total < 1 ||
    $total > 8 ||
    count($imagenes['tmp_name']) !== $total ||
    count($imagenes['error']) !== $total ||
    count($imagenes['size']) !== $total
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Puedes cargar entre 1 y 8 fotografías.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Configuración de imágenes
$tiposPermitidos = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

$calidadWebp = 78;
$anchoMaximo = 1600;
$altoMaximo = 1600;
$pesoMaximo = 5 * 1024 * 1024;
$pixelesMaximos = 25000000;

// Verificar disponibilidad de GD y WEBP
if (
    !function_exists('imagecreatefromstring') ||
    !function_exists('imagewebp')
) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'El servidor no tiene habilitado el procesamiento WEBP.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Preparar directorio
$directorio = __DIR__ . '/../../uploads/productos/';

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

$archivosGuardados = [];
$conexion = null;

// 6. Procesar fotografías
try {

    // Conectar con MariaDB
    $database = new Database();
    $conexion = $database->connect();

    // Verificar que el producto exista
    $consultaProducto = $conexion->prepare(
        "SELECT id FROM productos WHERE id = ?"
    );

    $consultaProducto->execute([$productoId]);

    if (!$consultaProducto->fetch()) {
        throw new InvalidArgumentException(
            'El producto seleccionado no existe.'
        );
    }

    // Iniciar transacción
    $conexion->beginTransaction();

    // Obtener siguiente orden
    $consultaOrden = $conexion->prepare(
        "SELECT COALESCE(MAX(orden), -1) + 1
         FROM producto_imagenes
         WHERE producto_id = ?"
    );

    $consultaOrden->execute([$productoId]);

    $orden = (int)$consultaOrden->fetchColumn();

    // Preparar inserción
    $consultaImagen = $conexion->prepare(
        "INSERT INTO producto_imagenes (
            producto_id,
            ruta,
            texto_alternativo,
            es_principal,
            orden
        ) VALUES (?, ?, ?, ?, ?)"
    );

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $textoAlternativo = trim(
        $_POST['texto_alternativo'] ?? ''
    );

    // 7. Procesar cada fotografía
    for ($i = 0; $i < $total; $i++) {

        // Verificar errores de carga
        if ($imagenes['error'][$i] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                'Una fotografía no se pudo recibir correctamente.'
            );
        }

        $archivoTemporal = $imagenes['tmp_name'][$i];

        if (
            !is_string($archivoTemporal) ||
            !is_uploaded_file($archivoTemporal)
        ) {
            throw new InvalidArgumentException(
                'Archivo de imagen inválido.'
            );
        }

        // Validar tamaño
        if (
            !is_numeric($imagenes['size'][$i]) ||
            $imagenes['size'][$i] <= 0 ||
            $imagenes['size'][$i] > $pesoMaximo
        ) {
            throw new InvalidArgumentException(
                'Cada fotografía debe pesar más de 0 y máximo 5 MB.'
            );
        }

        // Comprobar formato real
        $mime = $finfo->file($archivoTemporal);

        if (!in_array($mime, $tiposPermitidos, true)) {
            throw new InvalidArgumentException(
                'Formato no permitido. Utiliza JPG, PNG o WEBP.'
            );
        }

        // Comprobar dimensiones
        $informacion = getimagesize($archivoTemporal);

        if ($informacion === false) {
            throw new InvalidArgumentException(
                'Una fotografía no es válida.'
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
                'Una fotografía tiene dimensiones demasiado grandes.'
            );
        }

        // Crear imagen desde el archivo original
        $contenidoImagen = file_get_contents($archivoTemporal);

        if ($contenidoImagen === false) {
            throw new RuntimeException(
                'No fue posible leer una fotografía.'
            );
        }

        $imagenOriginal = imagecreatefromstring($contenidoImagen);

        if ($imagenOriginal === false) {
            throw new RuntimeException(
                'No fue posible procesar una fotografía.'
            );
        }

        // Calcular dimensiones sin deformar
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
            imagedestroy($imagenOriginal);

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

        // Generar nombre único
        $nombreArchivo = bin2hex(random_bytes(16)) . '.webp';

        $destino = $directorio . $nombreArchivo;

        // Convertir a WEBP
        $convertida = imagewebp(
            $imagenFinal,
            $destino,
            $calidadWebp
        );

        // Liberar memoria
        imagedestroy($imagenOriginal);
        imagedestroy($imagenFinal);

        // Registrar archivo para limpieza en caso de error
        if (file_exists($destino)) {
            $archivosGuardados[] = $destino;
        }

        // Comprobar conversión
        if (
            !$convertida ||
            !file_exists($destino) ||
            filesize($destino) === 0
        ) {
            throw new RuntimeException(
                'No fue posible convertir una fotografía a WEBP.'
            );
        }

        // Ruta relativa
        $ruta = 'uploads/productos/' . $nombreArchivo;

        // Primera imagen del producto será principal si aún no hay imágenes
        $esPrincipal = ($orden === 0) ? 1 : 0;

        // Guardar registro
        $consultaImagen->execute([
            $productoId,
            $ruta,
            $textoAlternativo !== ''
                ? $textoAlternativo
                : null,
            $esPrincipal,
            $orden
        ]);

        $orden++;
    }

    // 8. Confirmar operación
    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Fotografías optimizadas y guardadas correctamente.',
        'total' => $total,
        'formato' => 'WEBP',
        'calidad' => $calidadWebp
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    if ($conexion && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    foreach ($archivosGuardados as $archivo) {
        if (is_file($archivo)) {
            unlink($archivo);
        }
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

    foreach ($archivosGuardados as $archivo) {
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible procesar las fotografías.'
    ], JSON_UNESCAPED_UNICODE);
}
