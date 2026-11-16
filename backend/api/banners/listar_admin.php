
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $database = new Database();
    $conexion = $database->connect();

    $sql = "
        SELECT
            b.id,
            b.nombre,
            b.titulo,
            b.subtitulo,
            b.imagen_id,
            b.imagen_movil_id,
            b.texto_boton,
            b.enlace_boton,
            b.posicion,
            b.orden,
            b.activo,
            b.fecha_inicio,
            b.fecha_fin,
            b.fecha_creacion,
            b.fecha_actualizacion,

            principal.nombre_original AS imagen_nombre,
            principal.ruta_archivo AS imagen_ruta,
            principal.tipo_mime AS imagen_tipo,
            principal.activo AS imagen_activa,

            movil.nombre_original AS imagen_movil_nombre,
            movil.ruta_archivo AS imagen_movil_ruta,
            movil.tipo_mime AS imagen_movil_tipo,
            movil.activo AS imagen_movil_activa

        FROM banners b

        INNER JOIN recursos_multimedia principal
            ON principal.id = b.imagen_id

        LEFT JOIN recursos_multimedia movil
            ON movil.id = b.imagen_movil_id

        ORDER BY
            FIELD(
                b.posicion,
                'principal',
                'secundario',
                'promocional'
            ),
            b.orden ASC,
            b.id DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $banners = $consulta->fetchAll(PDO::FETCH_ASSOC);

    foreach ($banners as &$banner) {

        $banner['imagen_url'] = '/' . ltrim(
            $banner['imagen_ruta'],
            '/'
        );

        $banner['imagen_movil_url'] =
            !empty($banner['imagen_movil_ruta'])
                ? '/' . ltrim($banner['imagen_movil_ruta'], '/')
                : null;
    }

    unset($banner);

    echo json_encode([
        'success' => true,
        'total' => count($banners),
        'data' => $banners
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar los banners.'
    ], JSON_UNESCAPED_UNICODE);
}
