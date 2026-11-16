
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

$archivoNuevo = null;

try {
    $database = new Database();
    $pdo = $database->connect();

    $marcaId = filter_var(
        $_POST['marca_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$marcaId || $marcaId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El identificador de la marca no es válido.'
        ]);
        exit;
    }

    if (!isset($_FILES['logo'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No se recibió ningún logotipo.'
        ]);
        exit;
    }

    $archivo = $_FILES['logo'];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error al recibir el archivo.',
            'codigo' => $archivo['error']
        ]);
        exit;
    }

    // Validar tamaño máximo: 5 MB
    if ($archivo['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El logotipo no puede superar los 5 MB.'
        ]);
        exit;
    }

    // Validar que sea una imagen real
    $informacion = getimagesize($archivo['tmp_name']);

    if ($informacion === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El archivo no es una imagen válida.'
        ]);
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
        ]);
        exit;
    }

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
        ]);
        exit;
    }

    if (!function_exists('imagewebp')) {
        throw new RuntimeException(
            'El servidor no tiene habilitada la conversión WEBP.'
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
        ]);
        exit;
    }

    // Crear carpeta de almacenamiento
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

    // Cargar imagen según su formato
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

    $anchoFinal = (int)round($anchoOriginal * $factor);
    $altoFinal = (int)round($altoOriginal * $factor);

    $imagenFinal = imagecreatetruecolor(
        $anchoFinal,
        $altoFinal
    );

    if (!$imagenFinal) {
        imagedestroy($imagenOriginal);

        throw new RuntimeException(
            'No fue posible preparar la imagen.'
        );
    }

    // Conservar transparencia
    imagealphablending($imagenFinal, false);
    imagesavealpha($imagenFinal, true);

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

    // Nombre aleatorio para evitar colisiones
    $nombreArchivo = 'marca_' . bin2hex(random_bytes(16)) . '.webp';

    $archivoNuevo = $carpeta . $nombreArchivo;

    $guardado = imagewebp(
        $imagenFinal,
        $archivoNuevo,
        80
    );

    imagedestroy($imagenOriginal);
    imagedestroy($imagenFinal);

    if (!$guardado) {
        throw new RuntimeException(
            'No fue posible guardar el logotipo.'
        );
    }

    // Registrar ruta en la base de datos
    $rutaLogo = '/uploads/marcas/' . $nombreArchivo;

    $actualizar = $pdo->prepare(
        "UPDATE marcas SET logo = :logo WHERE id = :id"
    );

    $actualizar->execute([
        'logo' => $rutaLogo,
        'id' => $marcaId
    ]);

    // Eliminar logotipo anterior si pertenece a esta carpeta
    $logoAnterior = $marca['logo'] ?? '';

    if (
        $logoAnterior !== '' &&
        strpos($logoAnterior, '/uploads/marcas/') === 0
    ) {
        $nombreAnterior = basename($logoAnterior);

        $rutaAnterior = $carpeta . $nombreAnterior;

        if (
            is_file($rutaAnterior) &&
            realpath(dirname($rutaAnterior)) === realpath($carpeta)
        ) {
            @unlink($rutaAnterior);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Logotipo actualizado correctamente.',
        'marca_id' => $marcaId,
        'logo' => $rutaLogo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($archivoNuevo && is_file($archivoNuevo)) {
        @unlink($archivoNuevo);
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar el logotipo.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
