
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

// 4. Recibir datos
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Datos no válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$accion = $datos['accion'] ?? '';

$productoId = filter_var(
    $datos['producto_id'] ?? null,
    FILTER_VALIDATE_INT
);

$imagenId = filter_var(
    $datos['imagen_id'] ?? null,
    FILTER_VALIDATE_INT
);

// 5. Validar identificadores
if (
    !$productoId ||
    $productoId <= 0 ||
    !$imagenId ||
    $imagenId <= 0
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificadores no válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!in_array($accion, ['eliminar', 'principal'], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion = null;

try {
    $database = new Database();
    $conexion = $database->connect();

    // 6. Buscar imagen y verificar que pertenezca al producto
    $consultaImagen = $conexion->prepare(
        "SELECT id, producto_id, ruta, es_principal
         FROM producto_imagenes
         WHERE id = ? AND producto_id = ?
         LIMIT 1"
    );

    $consultaImagen->execute([
        $imagenId,
        $productoId
    ]);

    $imagen = $consultaImagen->fetch(PDO::FETCH_ASSOC);

    if (!$imagen) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'La imagen no existe o no pertenece a este producto.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 7. CAMBIAR IMAGEN PRINCIPAL
    if ($accion === 'principal') {

        $conexion->beginTransaction();

        // Quitar condición de principal a las demás imágenes
        $actualizarTodas = $conexion->prepare(
            "UPDATE producto_imagenes
             SET es_principal = 0
             WHERE producto_id = ?"
        );

        $actualizarTodas->execute([$productoId]);

        // Marcar la imagen seleccionada
        $actualizarPrincipal = $conexion->prepare(
            "UPDATE producto_imagenes
             SET es_principal = 1
             WHERE id = ? AND producto_id = ?"
        );

        $actualizarPrincipal->execute([
            $imagenId,
            $productoId
        ]);

        $conexion->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Imagen principal actualizada correctamente.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 8. ELIMINAR IMAGEN
    if ($accion === 'eliminar') {

        $conexion->beginTransaction();

        // Eliminar registro de imagen
        $eliminar = $conexion->prepare(
            "DELETE FROM producto_imagenes
             WHERE id = ? AND producto_id = ?"
        );

        $eliminar->execute([
            $imagenId,
            $productoId
        ]);

        // Consultar imágenes restantes
        $consultaRestantes = $conexion->prepare(
            "SELECT id
             FROM producto_imagenes
             WHERE producto_id = ?
             ORDER BY orden ASC, id ASC"
        );

        $consultaRestantes->execute([$productoId]);

        $restantes = $consultaRestantes->fetchAll(PDO::FETCH_ASSOC);

        // Si quedan imágenes, verificar que exista una principal
        if (count($restantes) > 0) {

            $consultaPrincipal = $conexion->prepare(
                "SELECT id
                 FROM producto_imagenes
                 WHERE producto_id = ? AND es_principal = 1
                 LIMIT 1"
            );

            $consultaPrincipal->execute([$productoId]);

            if (!$consultaPrincipal->fetch()) {

                $nuevaPrincipalId = $restantes[0]['id'];

                $actualizarPrincipal = $conexion->prepare(
                    "UPDATE producto_imagenes
                     SET es_principal = 1
                     WHERE id = ? AND producto_id = ?"
                );

                $actualizarPrincipal->execute([
                    $nuevaPrincipalId,
                    $productoId
                ]);
            }
        }

        // Verificar si el archivo sigue asociado a otro registro
        $consultaUso = $conexion->prepare(
            "SELECT COUNT(*)
             FROM producto_imagenes
             WHERE ruta = ?"
        );

        $consultaUso->execute([$imagen['ruta']]);

        $archivoEnUso = (int)$consultaUso->fetchColumn() > 0;

        // Confirmar cambios en la base de datos
        $conexion->commit();

        // Eliminar archivo físico únicamente si no está en uso
        if (!$archivoEnUso) {

            $rutaRelativa = str_replace(
                '\\',
                '/',
                $imagen['ruta']
            );

            $directorio = realpath(
                __DIR__ . '/../../uploads/productos'
            );

            if (
                $directorio !== false &&
                str_starts_with(
                    $rutaRelativa,
                    'uploads/productos/'
                )
            ) {

                $nombreArchivo = basename($rutaRelativa);

                $rutaArchivo = realpath(
                    $directorio . DIRECTORY_SEPARATOR . $nombreArchivo
                );

                if (
                    $rutaArchivo !== false &&
                    is_file($rutaArchivo) &&
                    dirname($rutaArchivo) === $directorio
                ) {

                    if (!unlink($rutaArchivo)) {
                        error_log(
                            'No fue posible eliminar el archivo: ' . $rutaArchivo
                        );
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
            'imagenes_restantes' => count($restantes)
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

} catch (Throwable $e) {

    if ($conexion && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible gestionar la imagen.'
    ], JSON_UNESCAPED_UNICODE);
}
