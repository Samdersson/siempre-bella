
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

try {

    $database = new Database();
    $conexion = $database->connect();

    // Filtros recibidos desde React
    $categoria = trim($_GET["categoria"] ?? "");
    $buscar = trim($_GET["buscar"] ?? "");
    $marca = trim($_GET["marca"] ?? "");
    $orden = $_GET["orden"] ?? "recientes";

    // Condiciones principales
    $condiciones = [
        "p.activo = 1",
        "c.activo = 1"
    ];

    $parametros = [];

    // Filtrar por categoría mediante slug
    if ($categoria !== "") {
        $condiciones[] = "c.slug = :categoria";
        $parametros[":categoria"] = $categoria;
    }

    // Buscar por nombre
    if ($buscar !== "") {
        $condiciones[] = "p.nombre LIKE :buscar";
        $parametros[":buscar"] = "%" . $buscar . "%";
    }

    // Filtrar por marca mediante slug
    if ($marca !== "") {
        $condiciones[] = "m.slug = :marca";
        $condiciones[] = "m.activo = 1";
        $parametros[":marca"] = $marca;
    }

    // Ordenamiento permitido
    $ordenamientos = [
        "recientes" => "p.creado_en DESC",
        "nombre_asc" => "p.nombre ASC",
        "nombre_desc" => "p.nombre DESC",
        "precio_asc" => "precio_final ASC",
        "precio_desc" => "precio_final DESC"
    ];

    $ordenSQL = $ordenamientos[$orden] ?? $ordenamientos["recientes"];

    $whereSQL = implode(" AND ", $condiciones);

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
                SELECT pi.ruta
                FROM producto_imagenes pi
                WHERE pi.producto_id = p.id
                ORDER BY
                    pi.es_principal DESC,
                    pi.orden ASC
                LIMIT 1
            ) AS imagen,

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

        WHERE $whereSQL

        ORDER BY
            (p.disponibilidad = 'disponible') DESC,
            $ordenSQL,
            p.id DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    $productos = $consulta->fetchAll();

    echo json_encode([
        "success" => true,
        "total" => count($productos),
        "data" => $productos
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar el catálogo."
    ], JSON_UNESCAPED_UNICODE);
}
