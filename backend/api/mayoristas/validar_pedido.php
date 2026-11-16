
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

// Responder errores en formato JSON
function responder($success, $mensaje, $datos = [], $codigo = 200) {
    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje,
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// Solo permitir POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(
        false,
        "Método no permitido. Utiliza POST.",
        [],
        405
    );
}

try {

    // Conexión a la base de datos
    $database = new Database();
    $connection = $database->connect();

    // Leer los datos enviados desde React
    $entrada = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($entrada)) {
        responder(false, "El contenido JSON no es válido.", [], 400);
    }

    $items = $entrada["productos"] ?? null;

    if (!is_array($items) || count($items) === 0) {
        responder(false, "El pedido no contiene productos.", [], 400);
    }

    if (count($items) > 50) {
        responder(false, "El pedido supera el máximo de productos permitido.", [], 400);
    }

    // Validar y consolidar identificadores y cantidades
    $cantidades = [];

    foreach ($items as $item) {

        $id = filter_var(
            $item["id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        $cantidad = filter_var(
            $item["cantidad"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1, "max_range" => 999]]
        );

        if ($id === false || $cantidad === false) {
            responder(
                false,
                "Hay productos con identificadores o cantidades inválidas.",
                [],
                400
            );
        }

        $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;

        if ($cantidades[$id] > 999) {
            responder(
                false,
                "La cantidad máxima por producto es de 999 unidades.",
                [],
                400
            );
        }
    }

    // Consultar el mínimo mayorista configurado
    $consultaConfiguracion = $connection->prepare("
        SELECT
            nombre_tienda,
            whatsapp,
            monto_minimo_mayorista,
            moneda
        FROM configuracion_tienda
        WHERE id = 1
        LIMIT 1
    ");

    $consultaConfiguracion->execute();

    $configuracion = $consultaConfiguracion->fetch(PDO::FETCH_ASSOC);

    if (!$configuracion) {
        responder(
            false,
            "No se encontró la configuración comercial de la tienda.",
            [],
            500
        );
    }

    $minimoMayorista = (float) $configuracion["monto_minimo_mayorista"];

    // Consultar los productos solicitados directamente en MariaDB
    $ids = array_keys($cantidades);

    $marcadores = implode(
        ",",
        array_fill(0, count($ids), "?")
    );

    $sql = "
        SELECT
            p.id,
            p.nombre,
            p.slug,
            p.precio_mayorista,
            p.disponibilidad,
            p.activo,
            m.nombre AS marca,

            (
                SELECT pi.ruta
                FROM producto_imagenes pi
                WHERE pi.producto_id = p.id
                ORDER BY pi.es_principal DESC, pi.orden ASC
                LIMIT 1
            ) AS imagen

        FROM productos p

        LEFT JOIN marcas m
            ON m.id = p.marca_id

        WHERE p.id IN ($marcadores)
    ";

    $consultaProductos = $connection->prepare($sql);
    $consultaProductos->execute($ids);

    $productosEncontrados = $consultaProductos->fetchAll(PDO::FETCH_ASSOC);

    // Indexar productos encontrados por ID
    $productosPorId = [];

    foreach ($productosEncontrados as $producto) {
        $productosPorId[(int) $producto["id"]] = $producto;
    }

    $productosValidos = [];
    $productosRechazados = [];
    $subtotal = 0;

    // Comprobar cada producto solicitado
    foreach ($cantidades as $id => $cantidad) {

        if (!isset($productosPorId[$id])) {

            $productosRechazados[] = [
                "id" => $id,
                "motivo" => "El producto no existe o ya fue eliminado."
            ];

            continue;
        }

        $producto = $productosPorId[$id];

        if ((int) $producto["activo"] !== 1) {

            $productosRechazados[] = [
                "id" => $id,
                "nombre" => $producto["nombre"],
                "motivo" => "El producto está inactivo."
            ];

            continue;
        }

        if ($producto["disponibilidad"] !== "disponible") {

            $productosRechazados[] = [
                "id" => $id,
                "nombre" => $producto["nombre"],
                "motivo" => "El producto no está disponible."
            ];

            continue;
        }

        $precio = (float) $producto["precio_mayorista"];

        if ($precio <= 0) {

            $productosRechazados[] = [
                "id" => $id,
                "nombre" => $producto["nombre"],
                "motivo" => "El producto no tiene un precio mayorista válido."
            ];

            continue;
        }

        $totalProducto = round($precio * $cantidad, 2);

        $productosValidos[] = [
            "id" => (int) $producto["id"],
            "nombre" => $producto["nombre"],
            "slug" => $producto["slug"],
            "marca" => $producto["marca"],
            "imagen" => $producto["imagen"],
            "cantidad" => $cantidad,
            "precio_unitario" => round($precio, 2),
            "subtotal" => $totalProducto
        ];

        $subtotal += $totalProducto;
    }

    $subtotal = round($subtotal, 2);

    $faltante = max(
        round($minimoMayorista - $subtotal, 2),
        0
    );

    // El pedido solo es válido si todos los productos fueron aceptados
    // y se alcanza el mínimo mayorista.
    $pedidoValido =
        count($productosRechazados) === 0 &&
        count($productosValidos) > 0 &&
        $subtotal >= $minimoMayorista;

    responder(
        true,
        $pedidoValido
            ? "Pedido mayorista validado correctamente."
            : "El pedido necesita ajustes antes de continuar.",
        [
            "tienda" => $configuracion["nombre_tienda"],
            "whatsapp" => $configuracion["whatsapp"],
            "moneda" => $configuracion["moneda"],
            "minimo_mayorista" => $minimoMayorista,
            "subtotal" => $subtotal,
            "faltante" => $faltante,
            "pedido_valido" => $pedidoValido,
            "productos" => $productosValidos,
            "productos_rechazados" => $productosRechazados
        ]
    );

} catch (Throwable $e) {

    error_log(
        "Error validando pedido mayorista: " . $e->getMessage()
    );

    responder(
        false,
        "No fue posible validar el pedido. Intenta nuevamente.",
        [],
        500
    );
}
