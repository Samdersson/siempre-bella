
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

function responder($success, $mensaje, $datos = [], $codigo = 200) {
    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje,
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// Solo aceptar POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método no permitido.", [], 405);
}

try {
    $entrada = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($entrada)) {
        responder(false, "Los datos enviados no son válidos.", [], 400);
    }

    // Validar datos del comprador
    $nombre = trim($entrada["nombre"] ?? "");
    $telefono = trim($entrada["telefono"] ?? "");
    $ciudad = trim($entrada["ciudad"] ?? "");
    $items = $entrada["productos"] ?? null;

    if (
        $nombre === "" ||
        mb_strlen($nombre) > 150
    ) {
        responder(false, "Ingresa un nombre válido (máximo 150 caracteres).", [], 400);
    }

    if (
        $telefono === "" ||
        mb_strlen($telefono) > 40 ||
        !preg_match('/^[0-9+\s().-]{7,40}$/', $telefono)
    ) {
        responder(false, "Ingresa un teléfono válido.", [], 400);
    }

    if (
        $ciudad === "" ||
        mb_strlen($ciudad) > 120
    ) {
        responder(false, "Ingresa una ciudad válida (máximo 120 caracteres).", [], 400);
    }

    if (!is_array($items) || count($items) === 0) {
        responder(false, "El pedido no contiene productos.", [], 400);
    }

    if (count($items) > 50) {
        responder(false, "El pedido supera el máximo de productos permitido.", [], 400);
    }

    // Consolidar cantidades por producto
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
            responder(false, "Hay productos con identificadores o cantidades inválidas.", [], 400);
        }

        $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;

        if ($cantidades[$id] > 999) {
            responder(false, "La cantidad máxima por producto es de 999 unidades.", [], 400);
        }
    }

    // Conexión
    $database = new Database();
    $connection = $database->connect();

    // Iniciar transacción
    $connection->beginTransaction();

    // Leer configuración comercial
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
        throw new RuntimeException("No se encontró la configuración comercial.");
    }

    $minimo = (float) $configuracion["monto_minimo_mayorista"];

    // Consultar productos directamente en MariaDB
    $ids = array_keys($cantidades);
    $marcadores = implode(",", array_fill(0, count($ids), "?"));

    $sqlProductos = "
        SELECT
            p.id,
            p.nombre,
            p.precio_mayorista,
            p.disponibilidad,
            p.activo
        FROM productos p
        WHERE p.id IN ($marcadores)
        FOR UPDATE
    ";

    $consultaProductos = $connection->prepare($sqlProductos);
    $consultaProductos->execute($ids);

    $encontrados = $consultaProductos->fetchAll(PDO::FETCH_ASSOC);

    $productosPorId = [];

    foreach ($encontrados as $producto) {
        $productosPorId[(int) $producto["id"]] = $producto;
    }

    $productosValidados = [];
    $subtotalPedido = 0;

    foreach ($cantidades as $id => $cantidad) {
        if (!isset($productosPorId[$id])) {
            throw new RuntimeException(
                "Uno de los productos del pedido ya no existe."
            );
        }

        $producto = $productosPorId[$id];

        if ((int) $producto["activo"] !== 1) {
            throw new RuntimeException(
                "El producto " . $producto["nombre"] . " está inactivo."
            );
        }

        if ($producto["disponibilidad"] !== "disponible") {
            throw new RuntimeException(
                "El producto " . $producto["nombre"] . " no está disponible."
            );
        }

        $precio = (float) $producto["precio_mayorista"];

        if ($precio <= 0) {
            throw new RuntimeException(
                "El producto " . $producto["nombre"] . " no tiene precio mayorista."
            );
        }

        $subtotalProducto = round($precio * $cantidad, 2);

        $productosValidados[] = [
            "id" => (int) $producto["id"],
            "nombre" => $producto["nombre"],
            "cantidad" => $cantidad,
            "precio_unitario" => round($precio, 2),
            "subtotal" => $subtotalProducto
        ];

        $subtotalPedido += $subtotalProducto;
    }

    $subtotalPedido = round($subtotalPedido, 2);

    if ($subtotalPedido < $minimo) {
        throw new RuntimeException(
            "El pedido no alcanza el mínimo mayorista. Total actual: " .
            number_format($subtotalPedido, 2, ".", "")
        );
    }

    // Crear referencia única
    $referencia = "SBM-" .
        date("Ymd") . "-" .
        strtoupper(bin2hex(random_bytes(3)));

    // Registrar cabecera del pedido
    $insertarPedido = $connection->prepare("
        INSERT INTO pedidos_mayoristas (
            referencia,
            nombre_cliente,
            telefono,
            ciudad,
            subtotal,
            minimo_mayorista,
            moneda,
            estado
        ) VALUES (
            :referencia,
            :nombre,
            :telefono,
            :ciudad,
            :subtotal,
            :minimo,
            :moneda,
            'pendiente'
        )
    ");

    $insertarPedido->execute([
        ":referencia" => $referencia,
        ":nombre" => $nombre,
        ":telefono" => $telefono,
        ":ciudad" => $ciudad,
        ":subtotal" => $subtotalPedido,
        ":minimo" => $minimo,
        ":moneda" => $configuracion["moneda"]
    ]);

    $pedidoId = (int) $connection->lastInsertId();

    // Registrar detalles del pedido
    $insertarDetalle = $connection->prepare("
        INSERT INTO pedido_mayorista_detalles (
            pedido_id,
            producto_id,
            nombre_producto,
            cantidad,
            precio_unitario,
            subtotal
        ) VALUES (
            :pedido_id,
            :producto_id,
            :nombre_producto,
            :cantidad,
            :precio_unitario,
            :subtotal
        )
    ");

    foreach ($productosValidados as $producto) {
        $insertarDetalle->execute([
            ":pedido_id" => $pedidoId,
            ":producto_id" => $producto["id"],
            ":nombre_producto" => $producto["nombre"],
            ":cantidad" => $producto["cantidad"],
            ":precio_unitario" => $producto["precio_unitario"],
            ":subtotal" => $producto["subtotal"]
        ]);
    }

    // Confirmar toda la operación
    $connection->commit();

    responder(
        true,
        "Pedido mayorista registrado correctamente.",
        [
            "pedido_id" => $pedidoId,
            "referencia" => $referencia,
            "nombre" => $nombre,
            "telefono" => $telefono,
            "ciudad" => $ciudad,
            "tienda" => $configuracion["nombre_tienda"],
            "whatsapp" => $configuracion["whatsapp"],
            "moneda" => $configuracion["moneda"],
            "minimo_mayorista" => $minimo,
            "subtotal" => $subtotalPedido,
            "estado" => "pendiente",
            "productos" => $productosValidados
        ],
        201
    );

} catch (Throwable $e) {

    if (
        isset($connection) &&
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        "Error registrando pedido mayorista: " . $e->getMessage()
    );

    // Los errores de validación comercial se devuelven sin detalles SQL.
    $mensaje = $e instanceof RuntimeException
        ? $e->getMessage()
        : "No fue posible registrar el pedido. Intenta nuevamente.";

    responder(false, $mensaje, [], 400);
}
