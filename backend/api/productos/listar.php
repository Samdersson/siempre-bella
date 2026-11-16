
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

try {

    $database = new Database();
    $connection = $database->connect();

    $sql = "
        SELECT
            p.id,
            p.nombre,
            p.slug,
            p.descripcion,
            p.precio_minorista,
            p.precio_mayorista,
            p.disponibilidad,

            m.nombre AS marca,

            (
                SELECT pi.ruta
                FROM producto_imagenes pi
                WHERE pi.producto_id = p.id
                ORDER BY pi.es_principal DESC, pi.orden ASC
                LIMIT 1
            ) AS imagen,

            (
                SELECT pr.precio_minorista_promocional
                FROM promociones pr
                WHERE pr.producto_id = p.id
                  AND pr.activo = 1
                  AND pr.precio_minorista_promocional IS NOT NULL
                  AND (pr.inicia_en IS NULL OR pr.inicia_en <= NOW())
                  AND (pr.termina_en IS NULL OR pr.termina_en >= NOW())
                ORDER BY pr.id DESC
                LIMIT 1
            ) AS precio_promocional

        FROM productos p

        LEFT JOIN marcas m
            ON m.id = p.marca_id

        WHERE p.activo = 1

        ORDER BY
            (p.disponibilidad = 'disponible') DESC,
            p.creado_en DESC

        LIMIT 12
    ";

    $statement = $connection->prepare($sql);
    $statement->execute();

    $productos = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "total" => count($productos),
        "data" => $productos
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar los productos."
    ], JSON_UNESCAPED_UNICODE);
}
