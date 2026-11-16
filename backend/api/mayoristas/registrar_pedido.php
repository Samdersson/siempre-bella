
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";

/**
 * Excepción para errores de validación comercial.
 */
class ErrorValidacionPedido extends RuntimeException {}

/**
 * Responder al cliente en formato JSON.
 */
function responder($success, $mensaje, $datos = [], $codigo = 200) {

    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje,
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// Solo aceptar POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método no permitido.", [], 405);
}

$connection = null;

try {

    // --------------------------------------------------
    // 1. RECIBIR Y VALIDAR LOS DATOS
    // --------------------------------------------------

    $contenido = file_get_contents("php://input");

    $entrada = json_decode($contenido, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($entrada)
    ) {
        responder(false, "Los datos enviados no son válidos.", [], 400);
    }

    // Datos del comprador.
    $nombre = trim($entrada["nombre"] ?? "");
    $telefono = trim($entrada["telefono"] ?? "");
    $ciudad = trim($entrada["ciudad"] ?? "");
    $items = $entrada["productos"] ?? null;

    // Validar nombre.
    if (
        !is_string($nombre) ||
        $nombre === "" ||
        mb_strlen($nombre) > 150
    ) {
        responder(
            false,
            "Ingresa un nombre válido (máximo 150 caracteres).",
            [],
            400
        );
    }

    // Validar teléfono.
    if (
        !is_string($telefono) ||
        $telefono === "" ||
        mb_strlen($telefono) > 40 ||
        !preg_match('/^[0-9+\s().-]{7,40}$/', $telefono)
    ) {
        responder(false, "Ingresa un teléfono válido.", [], 400);
    }

    // Validar ciudad.
    if (
        !is_string($ciudad) ||
        $ciudad === "" ||
        mb_strlen($ciudad) > 120
    ) {
        responder(
            false,
            "Ingresa una ciudad válida (máximo 120 caracteres).",
            [],
            400
        );
    }

    // Validar productos.
    if (!is_array($items) || count($items) === 0) {
        responder(false, "El pedido no contiene productos.", [], 400);
    }

    if (count($items) > 50) {
        responder(
            false,
            "El pedido supera el máximo de productos permitido.",
            [],
            400
        );
    }

    // --------------------------------------------------
    // 2. VALIDAR Y CONSOLIDAR CANTIDADES
    // --------------------------------------------------

    $cantidades = [];

    foreach ($items as $item) {

        if (!is_array($item)) {
            responder(
                false,
                "La información de uno de los productos no es válida.",
                [],
                400
            );
        }

        $idRecibido = $item["id"] ?? null;
        $cantidadRecibida = $item["cantidad"] ?? null;

        if (
            !is_scalar($idRecibido) ||
            !is_scalar($cantidadRecibida)
        ) {
            responder(
                false,
                "Hay productos con identificadores o cantidades inválidas.",
                [],
                400
            );
        }

        $id = filter_var(
            $idRecibido,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        $cantidad = filter_var(
            $cantidadRecibida,
            FILTER_VALIDATE_INT,
            ["options" => [
                "min_range" => 1,
                "max_range" => 999
            ]]
        );

        if ($id === false || $cantidad === false) {
            responder(
                false,
                "Hay productos con identificadores o cantidades inválidas.",
                [],
                400
            );
        }

        // Consolidar productos repetidos.
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

    // --------------------------------------------------
    // 3. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();

    $connection = $database->connect();

    if (!$connection instanceof PDO) {
        throw new RuntimeException(
            "No fue posible establecer la conexión con la base de datos."
        );
    }

    // Iniciar transacción.
    $connection->beginTransaction();

    // --------------------------------------------------
    // 4. CONSULTAR CONFIGURACIÓN COMERCIAL
    // --------------------------------------------------

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
        throw new RuntimeException(
            "No se encontró la configuración comercial."
        );
    }

    $minimo = (float) $configuracion["monto_minimo_mayorista"];

    if ($minimo <= 0) {
        throw new RuntimeException(
            "El monto mínimo mayorista no está configurado correctamente."
        );
    }

    // --------------------------------------------------
    // 5. CONSULTAR PRODUCTOS DIRECTAMENTE EN MARIADB
    // --------------------------------------------------

    $ids = array_keys($cantidades);

    $marcadores = implode(
        ",",
        array_fill(0, count($ids), "?")
    );

    $sqlProductos = "
        SELECT
            p.id,
            p.nombre,
            p.precio_mayorista,
            p.disponibilidad,
            p.activo
        FROM productos p
        INNER JOIN categorias c
            ON c.id = p.categoria_id
           AND c.activo = 1
        WHERE p.id IN ($marcadores)
        FOR UPDATE
    ";

    $consultaProductos = $connection->prepare($sqlProductos);

    $consultaProductos->execute($ids);

    $encontrados = $consultaProductos->fetchAll(PDO::FETCH_ASSOC);

    // Organizar productos por identificador.
    $productosPorId = [];

    foreach ($encontrados as $producto) {
        $productosPorId[(int) $producto["id"]] = $producto;
    }

    // --------------------------------------------------
    // 6. VALIDAR PRODUCTOS, PRECIOS Y DISPONIBILIDAD
    // --------------------------------------------------

    $productosValidados = [];

    $subtotalPedido = 0;

    foreach ($cantidades as $id => $cantidad) {

        if (!isset($productosPorId[$id])) {
            throw new ErrorValidacionPedido(
                "Uno de los productos del pedido ya no existe o su categoría está inactiva."
            );
        }

        $producto = $productosPorId[$id];

        // Comprobar que esté activo.
        if ((int) $producto["activo"] !== 1) {
            throw new ErrorValidacionPedido(
                "El producto " . $producto["nombre"] . " está inactivo."
            );
        }

        // Comprobar disponibilidad.
        if ($producto["disponibilidad"] !== "disponible") {
            throw new ErrorValidacionPedido(
                "El producto " . $producto["nombre"] . " no está disponible."
            );
        }

        // Consultar precio real desde la base de datos.
        $precio = (float) $producto["precio_mayorista"];

        if ($precio <= 0) {
            throw new ErrorValidacionPedido(
                "El producto " . $producto["nombre"] . " no tiene precio mayorista."
            );
        }

        // Calcular subtotal del producto.
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

    // --------------------------------------------------
    // 7. VALIDAR COMPRA MÍNIMA
    // --------------------------------------------------

    if ($subtotalPedido < $minimo) {
        throw new ErrorValidacionPedido(
            "El pedido no alcanza el mínimo mayorista. Total actual: " .
            number_format($subtotalPedido, 2, ".", "")
        );
    }

    // --------------------------------------------------
    // 8. GENERAR REFERENCIA ÚNICA
    // --------------------------------------------------

    $referencia = "SBM-" .
        date("Ymd") . "-" .
        strtoupper(bin2hex(random_bytes(3)));

    // --------------------------------------------------
    // 9. REGISTRAR CABECERA DEL PEDIDO
    // --------------------------------------------------

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

    // --------------------------------------------------
    // 10. REGISTRAR DETALLES DEL PEDIDO
    // --------------------------------------------------

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

    // --------------------------------------------------
    // 11. CONFIRMAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->commit();

    // --------------------------------------------------
    // 12. DEVOLVER PEDIDO REGISTRADO
    // --------------------------------------------------

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

    // --------------------------------------------------
    // 13. REVERTIR TRANSACCIÓN SI OCURRE UN ERROR
    // --------------------------------------------------

    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    // Registrar el error en el log del servidor.
    error_log(
        "Error registrando pedido mayorista: " . $e->getMessage()
    );

    // Errores comerciales o de validación.
    if ($e instanceof ErrorValidacionPedido) {
        responder(
            false,
            $e->getMessage(),
            [],
            400
        );
    }

    // Errores internos del servidor.
    responder(
        false,
        "No fue posible registrar el pedido. Intenta nuevamente.",
        [],
        500
    );
}
