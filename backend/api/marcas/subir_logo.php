
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión administrativa
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

// Validar token CSRF
exigirTokenCSRF();

$archivoNuevo = null;
$imagenOriginal = null;
$imagenFinal = null;

try {
    $database = new Database();
    $pdo = $database->connect();

    // Validar identificador de marca
    $marcaId = filter_var(
        $_POST['marca_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($marcaId === false || $marcaId === null || $marcaId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El identificador de la marca no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar archivo recibido
    if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No se recibió ningún logotipo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $archivo = $_FILES['logo'];

    if (
        !isset($archivo['error'], $archivo['size'], $archivo['tmp_name']) ||
        !is_string($archivo['tmp_name'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La información del archivo no es válida.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al recibir el archivo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (
        !is_uploaded_file($archivo['tmp_name']) ||
        !is_file($archivo['tmp_name'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El archivo recibido no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar tamaño máximo: 5 MB
    if (
        $archivo['size'] <= 0 ||
        $archivo['size'] > 5 * 1024 * 1024
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El logotipo debe pesar máximo 5 MB.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar imagen real
    $informacion = getimagesize($archivo['tmp_name']);

    if ($informacion === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El archivo no es una imagen válida.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mime = $informacion['mime'];

    $formatosPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!in_array($mime, $formatosPermitidos, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Formato no permitido. Utiliza JPG, PNG o WEBP.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar dimensiones
    $anchoOriginal = $informacion[0];
    $altoOriginal = $informacion[1];

    if (
        $anchoOriginal <= 0 ||
        $altoOriginal <= 0 ||
        ($anchoOriginal * $altoOriginal) > 25000000
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Las dimensiones de la imagen no son válidas.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (
        !function_exists('imagewebp') ||
        !function_exists('imagecreatetruecolor') ||
        !function_exists('imagecopyresampled')
    ) {
        throw new RuntimeException(
            'El servidor no tiene habilitadas las funciones necesarias para procesar imágenes.'
        );
    }

    // Comprobar que la marca existe
    $consulta = $pdo->prepare(
        "SELECT id, logo FROM marcas WHERE id = :id LIMIT 1"
    );

    $consulta->execute(['id' => $marcaId]);

    $marca = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$marca) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La marca no existe.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Crear carpeta
    $carpeta = __DIR__ . '/../../uploads/marcas/';

    if (
        !is_dir($carpeta) &&
        !mkdir($carpeta, 0755, true) &&
        !is_dir($carpeta)
    ) {
        throw new RuntimeException(
            'No fue posible crear la carpeta de logotipos.'
        );
    }

    if (!is_writable($carpeta)) {
        throw new RuntimeException(
            'La carpeta de logotipos no tiene permisos de escritura.'
        );
    }

    // Cargar imagen original
    switch ($mime) {
        case 'image/jpeg':
            $imagenOriginal = imagecreatefromjpeg($archivo['tmp_name']);
            break;

        case 'image/png':
            $imagenOriginal = imagecreatefrompng($archivo['tmp_name']);
            break;

        case 'image/webp':
            $imagenOriginal = imagecreatefromwebp($archivo['tmp_name']);
            break;

        default:
            $imagenOriginal = false;
    }

    if (!$imagenOriginal) {
        throw new RuntimeException(
            'No fue posible procesar la imagen.'
        );
    }

    // Redimensionar proporcionalmente
    $maximo = 1600;

    $factor = min(
        1,
        $maximo / $anchoOriginal,
        $maximo / $altoOriginal
    );

    $anchoFinal = max(1, (int)round($anchoOriginal * $factor));
    $altoFinal = max(1, (int)round($altoOriginal * $factor));

    $imagenFinal = imagecreatetruecolor(
        $anchoFinal,
        $altoFinal
    );

    if (!$imagenFinal) {
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
        $anchoFinal,
        $altoFinal,
        $anchoOriginal,
        $altoOriginal
    );

    // Generar nombre aleatorio
    $nombreArchivo = 'marca_' . bin2hex(random_bytes(16)) . '.webp';

    $archivoNuevo = $carpeta . $nombreArchivo;

    // Convertir a WEBP
    $guardado = imagewebp(
        $imagenFinal,
        $archivoNuevo,
        80
    );

    if (!$guardado || !is_file($archivoNuevo)) {
        throw new RuntimeException(
            'No fue posible guardar el logotipo.'
        );
    }

    // Liberar memoria de imágenes
    imagedestroy($imagenOriginal);
    $imagenOriginal = null;

    imagedestroy($imagenFinal);
    $imagenFinal = null;

    // Actualizar ruta en base de datos
    $rutaLogo = '/uploads/marcas/' . $nombreArchivo;

    $pdo->beginTransaction();

    $actualizar = $pdo->prepare(
        "UPDATE marcas SET logo = :logo WHERE id = :id"
    );

    $actualizar->execute([
        'logo' => $rutaLogo,
        'id' => $marcaId
    ]);

    $pdo->commit();

    // Eliminar logo anterior solo después de confirmar el cambio
    $logoAnterior = $marca['logo'] ?? '';

    if (
        is_string($logoAnterior) &&
        strpos($logoAnterior, '/uploads/marcas/') === 0
    ) {
        $nombreAnterior = basename($logoAnterior);

        if (
            $nombreAnterior !== '' &&
            $nombreAnterior !== $nombreArchivo &&
            preg_match('/^marca_[a-f0-9]{32}\.webp$/', $nombreAnterior)
        ) {
            $rutaAnterior = $carpeta . $nombreAnterior;

            if (
                is_file($rutaAnterior) &&
                realpath(dirname($rutaAnterior)) === realpath($carpeta)
            ) {
                if (!@unlink($rutaAnterior)) {
                    error_log(
                        'No fue posible eliminar el logotipo anterior de la marca ' . $marcaId
                    );
                }
            }
        }
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Logotipo actualizado correctamente.',
        'marca_id' => $marcaId,
        'logo' => $rutaLogo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Eliminar archivo nuevo si ocurrió un error
    if ($archivoNuevo && is_file($archivoNuevo)) {
        @unlink($archivoNuevo);
    }

    error_log('Error al subir logotipo de marca: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar el logotipo.'
    ], JSON_UNESCAPED_UNICODE);

} finally {

    if ($imagenOriginal instanceof GdImage) {
        imagedestroy($imagenOriginal);
    }

    if ($imagenFinal instanceof GdImage) {
        imagedestroy($imagenFinal);
    }
}
