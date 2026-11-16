
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

try {

    $database = new Database();
    $connection = $database->connect();

    $sql = "
        SELECT
            pr.id AS promocion_id,
            pr.nombre AS nombre_promocion,
            pr.precio_minorista_promocional,
            pr.precio_mayorista_promocional,
            pr.inicia_en,
            pr.termina_en,

            p.id AS producto_id,
            p.nombre AS producto,
            p.slug,
            p.precio_minorista,
            p.disponibilidad,

            m.nombre AS marca,

            (
                SELECT pi.ruta
                FROM producto_imagenes pi
                WHERE pi.producto_id = p.id
                ORDER BY pi.es_principal DESC, pi.orden ASC
                LIMIT 1
            ) AS imagen

        FROM promociones pr

        INNER JOIN productos p
            ON p.id = pr.producto_id

        LEFT JOIN marcas m
            ON m.id = p.marca_id

        WHERE
            pr.activo = 1
            AND p.activo = 1
            AND pr.variante_id IS NULL
            AND (
                pr.inicia_en IS NULL
                OR pr.inicia_en <= NOW()
            )
            AND (
                pr.termina_en IS NULL
                OR pr.termina_en >= NOW()
            )

        ORDER BY pr.id DESC
    ";

    $statement = $connection->prepare($sql);
    $statement->execute();

    $promociones = $statement->fetchAll();

    echo json_encode([
        "success" => true,
        "total" => count($promociones),
        "data" => $promociones
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar las promociones."
    ], JSON_UNESCAPED_UNICODE);
}
