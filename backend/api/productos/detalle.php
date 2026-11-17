
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

try {

    $slug = trim($_GET["slug"] ?? "");

    if ($slug === "") {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Debes indicar el producto."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $database = new Database();
    $conexion = $database->connect();

    // Consultar producto
    $sql = "
        SELECT
            p.id,
            p.categoria_id,
            p.marca_id,
            p.sku,
            p.nombre,
            p.slug,
            p.descripcion,
            p.precio_minorista,
            p.precio_mayorista,
            p.disponibilidad,

            c.nombre AS categoria,
            c.slug AS categoria_slug,

            m.nombre AS marca,
            m.slug AS marca_slug,

            (
                SELECT pr.precio_minorista_promocional
                FROM promociones pr
                WHERE pr.producto_id = p.id
                  AND pr.activo = 1
                  AND pr.variante_id IS NULL
                  AND pr.precio_minorista_promocional IS NOT NULL
                  AND pr.precio_minorista_promocional > 0
                  AND pr.precio_minorista_promocional < p.precio_minorista
                  AND (
                      pr.inicia_en IS NULL
                      OR pr.inicia_en <= NOW()
                  )
                  AND (
                      pr.termina_en IS NULL
                      OR pr.termina_en >= NOW()
                  )
                ORDER BY pr.id DESC
                LIMIT 1
            ) AS precio_promocional

        FROM productos p

        INNER JOIN categorias c
            ON c.id = p.categoria_id

        LEFT JOIN marcas m
            ON m.id = p.marca_id

        WHERE
            p.slug = :slug
            AND p.activo = 1
            AND c.activo = 1

        LIMIT 1
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":slug" => $slug
    ]);

    $producto = $consulta->fetch();

    if (!$producto) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Producto no encontrado."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Consultar todas las fotografías
    $consultaImagenes = $conexion->prepare("
        SELECT
            id,
            ruta,
            texto_alternativo,
            es_principal,
            orden
        FROM producto_imagenes
        WHERE producto_id = ?
        ORDER BY es_principal DESC, orden ASC, id ASC
    ");

    $consultaImagenes->execute([
        $producto["id"]
    ]);

    $producto["imagenes"] = $consultaImagenes->fetchAll();

    // Fotografía principal como campo independiente
    $producto["imagen"] = null;

    foreach ($producto["imagenes"] as $imagen) {
        if ((int)$imagen["es_principal"] === 1) {
            $producto["imagen"] = $imagen["ruta"];
            break;
        }
    }

    // Si no hay principal, utilizar la primera fotografía
    if (
        $producto["imagen"] === null &&
        count($producto["imagenes"]) > 0
    ) {
        $producto["imagen"] = $producto["imagenes"][0]["ruta"];
    }

    echo json_encode([
        "success" => true,
        "data" => $producto
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar el producto."
    ], JSON_UNESCAPED_UNICODE);
}
