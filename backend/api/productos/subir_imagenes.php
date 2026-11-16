
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// 1. Validar método y sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ]);
    exit;
}

// 2. Validar producto
$productoId = filter_var(
    $_POST['producto_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$productoId || !isset($_FILES['imagenes'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Selecciona un producto y sus fotografías.'
    ]);
    exit;
}

$imagenes = $_FILES['imagenes'];

if (!is_array($imagenes['name'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Formato de archivos inválido.'
    ]);
    exit;
}

$total = count($imagenes['name']);

if ($total < 1 || $total > 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Puedes cargar entre 1 y 8 fotografías.'
    ]);
    exit;
}

// 3. Configuración de imágenes
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

$directorio = __DIR__ . '/../../uploads/productos/';

if (!is_dir($directorio)) {
    if (!mkdir($directorio, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No fue posible preparar el almacenamiento.'
        ]);
        exit;
    }
}

if (!is_writable($directorio)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'La carpeta de imágenes no tiene permisos de escritura.'
    ]);
    exit;
}

$archivosGuardados = [];
$conexion = null;

try {

    // 4. Conectar con MariaDB
    $database = new Database();
    $conexion = $database->connect();

    $consultaProducto = $conexion->prepare(
        "SELECT id FROM productos WHERE id = ?"
    );

    $consultaProducto->execute([$productoId]);

    if (!$consultaProducto->fetch()) {
        throw new InvalidArgumentException(
            'El producto seleccionado no existe.'
        );
    }

    // 5. Preparar transacción
    $conexion->beginTransaction();

    $consultaOrden = $conexion->prepare(
        "SELECT
            COALESCE(MAX(orden), -1) + 1
         FROM producto_imagenes
         WHERE producto_id = ?"
    );

    $consultaOrden->execute([$productoId]);

    $orden = (int)$consultaOrden->fetchColumn();

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

    // 6. Procesar fotografías
    for ($i = 0; $i < $total; $i++) {

        if ($imagenes['error'][$i] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                'Una fotografía no se pudo recibir correctamente.'
            );
        }

        $archivoTemporal = $imagenes['tmp_name'][$i];

        if (!is_uploaded_file($archivoTemporal)) {
            throw new InvalidArgumentException(
                'Archivo de imagen inválido.'
            );
        }

        if ($imagenes['size'][$i] > $pesoMaximo) {
            throw new InvalidArgumentException(
                'Cada fotografía debe pesar máximo 5 MB.'
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
        $imagenOriginal = imagecreatefromstring(
            file_get_contents($archivoTemporal)
        );

        if ($imagenOriginal === false) {
            throw new RuntimeException(
                'No fue posible procesar una fotografía.'
            );
        }

        // Calcular nuevas dimensiones sin deformar
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


        // Generar nombre único para la imagen
        $nombreArchivo = bin2hex(random_bytes(16)) . '.webp';
        $destino = $directorio . $nombreArchivo;

        // Convertir imagen a WEBP
        $convertida = imagewebp(
            $imagenFinal,
            $destino,
            $calidadWebp
        );

        // Liberar memoria
        imagedestroy($imagenOriginal);
        imagedestroy($imagenFinal);

        // Registrar el archivo para limpiarlo si falla la operación
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

        // Guardar ruta relativa
        $ruta = 'uploads/productos/' . $nombreArchivo;

        $esPrincipal = ($orden === 0) ? 1 : 0;

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

    // 7. Confirmar operación
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
        if (file_exists($archivo)) {
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
        if (file_exists($archivo)) {
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
