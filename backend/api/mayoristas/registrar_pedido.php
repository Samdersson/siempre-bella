
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';

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
        'success' => $success,
        'message' => $mensaje,
        'data' => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// --------------------------------------------------
// 1. SOLO ACEPTAR POST
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(false, 'Método no permitido.', [], 405);
}

// --------------------------------------------------
// 2. VALIDAR TIPO DE CONTENIDO
// --------------------------------------------------

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== 0) {
    responder(false, 'El formato de la solicitud no es válido.', [], 415);
}

// --------------------------------------------------
// 3. LIMITAR TAMAÑO DE LA SOLICITUD
// --------------------------------------------------

$longitud = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($longitud > 65536) {
    responder(false, 'La solicitud supera el tamaño permitido.', [], 413);
}

$connection = null;

try {

    // --------------------------------------------------
    // 4. RECIBIR Y VALIDAR LOS DATOS
    // --------------------------------------------------

    $contenido = file_get_contents('php://input');

    if ($contenido === false || trim($contenido) === '') {
        responder(false, 'No se recibieron datos.', [], 400);
    }

    $entrada = json_decode($contenido, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($entrada)) {
        responder(false, 'Los datos enviados no son válidos.', [], 400);
    }

    // Validar tipos antes de utilizar trim().
    if (
        !isset($entrada['nombre']) ||
        !is_string($entrada['nombre']) ||
        !isset($entrada['telefono']) ||
        !is_string($entrada['telefono']) ||
        !isset($entrada['ciudad']) ||
        !is_string($entrada['ciudad'])
    ) {
        responder(false, 'Los datos del comprador no son válidos.', [], 400);
    }

    $nombre = trim($entrada['nombre']);
    $telefono = trim($entrada['telefono']);
    $ciudad = trim($entrada['ciudad']);
    $items = $entrada['productos'] ?? null;

    // Validar nombre.
    if (
        $nombre === '' ||
        mb_strlen($nombre, 'UTF-8') > 150
    ) {
        responder(
            false,
            'Ingresa un nombre válido (máximo 150 caracteres).',
            [],
            400
        );
    }

    // Validar teléfono.
    if (
        $telefono === '' ||
        mb_strlen($telefono, 'UTF-8') > 40 ||
        !preg_match('/^[0-9+\s().-]{7,40}$/', $telefono)
    ) {
        responder(false, 'Ingresa un teléfono válido.', [], 400);
    }

    // Validar ciudad.
    if (
        $ciudad === '' ||
        mb_strlen($ciudad, 'UTF-8') > 120
    ) {
        responder(
            false,
            'Ingresa una ciudad válida (máximo 120 caracteres).',
            [],
            400
        );
    }

    // Validar productos.
    if (!is_array($items) || count($items) === 0) {
        responder(false, 'El pedido no contiene productos.', [], 400);
    }

    if (count($items) > 50) {
        responder(
            false,
            'El pedido supera el máximo de productos permitido.',
            [],
            400
        );
    }

    // --------------------------------------------------
    // 5. VALIDAR Y CONSOLIDAR CANTIDADES
    // --------------------------------------------------

    $cantidades = [];

    foreach ($items as $item) {

        if (!is_array($item)) {
            responder(
                false,
                'La información de uno de los productos no es válida.',
                [],
                400
            );
        }

        $idRecibido = $item['id'] ?? null;
        $cantidadRecibida = $item['cantidad'] ?? null;

        if (
            !is_scalar($idRecibido) ||
            !is_scalar($cantidadRecibida)
        ) {
            responder(
                false,
                'Hay productos con identificadores o cantidades inválidas.',
                [],
                400
            );
        }

        $id = filter_var(
            $idRecibido,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $cantidad = filter_var(
            $cantidadRecibida,
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => 1,
                'max_range' => 999
            ]]
        );

        if ($id === false || $cantidad === false) {
            responder(
                false,
                'Hay productos con identificadores o cantidades inválidas.',
                [],
                400
            );
        }

        // Consolidar productos repetidos.
        $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;

        if ($cantidades[$id] > 999) {
            responder(
                false,
                'La cantidad máxima por producto es de 999 unidades.',
                [],
                400
            );
        }
    }

    // --------------------------------------------------
    // 6. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();
    $connection = $database->connect();

    if (!$connection instanceof PDO) {
        throw new RuntimeException(
            'No fue posible establecer la conexión con la base de datos.'
        );
    }

    // Iniciar transacción.
    $connection->beginTransaction();

    // --------------------------------------------------
    // 7. CONSULTAR CONFIGURACIÓN COMERCIAL
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
            'No se encontró la configuración comercial.'
        );
    }

    $minimo = (float) $configuracion['monto_minimo_mayorista'];

    if (!is_finite($minimo) || $minimo <= 0) {
        throw new RuntimeException(
            'El monto mínimo mayorista no está configurado correctamente.'
        );
    }

    // --------------------------------------------------
    // 8. CONSULTAR PRODUCTOS DIRECTAMENTE EN MARIADB
    // --------------------------------------------------

    $ids = array_keys($cantidades);

    $marcadores = implode(
        ',',
        array_fill(0, count($ids), '?')
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
        $productosPorId[(int) $producto['id']] = $producto;
    }

    // --------------------------------------------------
    // 9. VALIDAR PRODUCTOS, PRECIOS Y DISPONIBILIDAD
    // --------------------------------------------------

    $productosValidados = [];
    $subtotalPedido = 0;

    foreach ($cantidades as $id => $cantidad) {

        if (!isset($productosPorId[$id])) {
            throw new ErrorValidacionPedido(
                'Uno de los productos del pedido ya no existe o su categoría está inactiva.'
            );
        }

        $producto = $productosPorId[$id];

        // Comprobar que esté activo.
        if ((int) $producto['activo'] !== 1) {
            throw new ErrorValidacionPedido(
                'El producto ' . $producto['nombre'] . ' está inactivo.'
            );
        }

        // Comprobar disponibilidad.
        if ($producto['disponibilidad'] !== 'disponible') {
            throw new ErrorValidacionPedido(
                'El producto ' . $producto['nombre'] . ' no está disponible.'
            );
        }

        // Consultar precio real desde la base de datos.
        $precio = (float) $producto['precio_mayorista'];

        if (!is_finite($precio) || $precio <= 0) {
            throw new ErrorValidacionPedido(
                'El producto ' . $producto['nombre'] . ' no tiene precio mayorista válido.'
            );
        }

        // Calcular subtotal del producto.
        $subtotalProducto = round($precio * $cantidad, 2);

        $productosValidados[] = [
            'id' => (int) $producto['id'],
            'nombre' => $producto['nombre'],
            'cantidad' => $cantidad,
            'precio_unitario' => round($precio, 2),
            'subtotal' => $subtotalProducto
        ];

        $subtotalPedido += $subtotalProducto;
    }

    $subtotalPedido = round($subtotalPedido, 2);

    if (!is_finite($subtotalPedido)) {
        throw new ErrorValidacionPedido(
            'No fue posible calcular el total del pedido.'
        );
    }

    // --------------------------------------------------
    // 10. VALIDAR COMPRA MÍNIMA
    // --------------------------------------------------

    if ($subtotalPedido < $minimo) {
        throw new ErrorValidacionPedido(
            'El pedido no alcanza el mínimo mayorista. Total actual: ' .
            number_format($subtotalPedido, 2, '.', '')
        );
    }

    // --------------------------------------------------
    // 11. GENERAR REFERENCIA ÚNICA
    // --------------------------------------------------

    $referencia = 'SBM-' .
        date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(3)));

    // --------------------------------------------------
    // 12. REGISTRAR CABECERA DEL PEDIDO
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
        ':referencia' => $referencia,
        ':nombre' => $nombre,
        ':telefono' => $telefono,
        ':ciudad' => $ciudad,
        ':subtotal' => $subtotalPedido,
        ':minimo' => $minimo,
        ':moneda' => $configuracion['moneda']
    ]);

    $pedidoId = (int) $connection->lastInsertId();

    // --------------------------------------------------
    // 13. REGISTRAR DETALLES DEL PEDIDO
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
            ':pedido_id' => $pedidoId,
            ':producto_id' => $producto['id'],
            ':nombre_producto' => $producto['nombre'],
            ':cantidad' => $producto['cantidad'],
            ':precio_unitario' => $producto['precio_unitario'],
            ':subtotal' => $producto['subtotal']
        ]);
    }

    // --------------------------------------------------
    // 14. REGISTRAR EVENTO INICIAL DEL HISTORIAL
    // --------------------------------------------------

    $insertarHistorial = $connection->prepare("
        INSERT INTO historial_pedidos_mayoristas (
            pedido_id,
            estado_anterior,
            estado_nuevo,
            administrador_id,
            administrador_nombre,
            observacion
        ) VALUES (
            :pedido_id,
            NULL,
            :estado_nuevo,
            NULL,
            :administrador_nombre,
            :observacion
        )
    ");

    $insertarHistorial->execute([
        ':pedido_id' => $pedidoId,
        ':estado_nuevo' => 'pendiente',
        ':administrador_nombre' => 'Sistema',
        ':observacion' => 'Pedido mayorista recibido'
    ]);

    // --------------------------------------------------
    // 15. CONFIRMAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->commit();

    // --------------------------------------------------
    // 16. DEVOLVER PEDIDO REGISTRADO
    // --------------------------------------------------

    responder(
        true,
        'Pedido mayorista registrado correctamente.',
        [
            'pedido_id' => $pedidoId,
            'referencia' => $referencia,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'ciudad' => $ciudad,
            'tienda' => $configuracion['nombre_tienda'],
            'whatsapp' => $configuracion['whatsapp'],
            'moneda' => $configuracion['moneda'],
            'minimo_mayorista' => $minimo,
            'subtotal' => $subtotalPedido,
            'estado' => 'pendiente',
            'productos' => $productosValidados
        ],
        201
    );

} catch (Throwable $e) {

    // Revertir transacción si ocurre un error.
    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        'Error registrando pedido mayorista: ' . $e->getMessage()
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
        'No fue posible registrar el pedido. Intenta nuevamente.',
        [],
        500
    );
}
