
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

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

            principal.ruta_archivo AS imagen_ruta,
            movil.ruta_archivo AS imagen_movil_ruta

        FROM banners b

        INNER JOIN recursos_multimedia principal
            ON principal.id = b.imagen_id
            AND principal.activo = 1

        LEFT JOIN recursos_multimedia movil
            ON movil.id = b.imagen_movil_id
            AND movil.activo = 1

        WHERE
            b.activo = 1
            AND b.posicion = 'promocional'
            AND (
                b.fecha_inicio IS NULL
                OR b.fecha_inicio <= NOW()
            )
            AND (
                b.fecha_fin IS NULL
                OR b.fecha_fin >= NOW()
            )

        ORDER BY
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

    error_log('Error al consultar banners promocionales: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible cargar los banners promocionales.'
    ], JSON_UNESCAPED_UNICODE);
}
