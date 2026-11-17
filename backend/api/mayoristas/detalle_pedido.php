
<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";

/**
 * Respuesta JSON.
 */
function responder($success, $mensaje, $codigo = 200, $datos = []) {

    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje,
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// --------------------------------------------------
// 1. VERIFICAR SESIÓN ADMINISTRATIVA
// --------------------------------------------------

if (!isset($_SESSION["admin"])) {

    responder(
        false,
        "No tienes autorización para consultar este pedido.",
        401
    );
}

// --------------------------------------------------
// 2. VALIDAR MÉTODO HTTP
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    header("Allow: GET");

    responder(
        false,
        "Método no permitido. Utiliza GET.",
        405
    );
}

try {

    // --------------------------------------------------
    // 3. VALIDAR IDENTIFICADOR
    // --------------------------------------------------

    $idRecibido = $_GET["id"] ?? null;

    if (
        (!is_string($idRecibido) && !is_int($idRecibido)) ||
        $idRecibido === ""
    ) {
        responder(
            false,
            "El identificador del pedido no es válido.",
            400
        );
    }

    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        [
            "options" => [
                "min_range" => 1
            ]
        ]
    );

    if ($id === false) {

        responder(
            false,
            "El identificador del pedido no es válido.",
            400
        );
    }

    // --------------------------------------------------
    // 4. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();

    $connection = $database->connect();

    if (!$connection instanceof PDO) {

        throw new RuntimeException(
            "No fue posible conectar con la base de datos."
        );
    }

    // --------------------------------------------------
    // 5. CONSULTAR INFORMACIÓN PRINCIPAL DEL PEDIDO
    // --------------------------------------------------

    $consultaPedido = $connection->prepare("
        SELECT
            id,
            referencia,
            nombre_cliente,
            telefono,
            ciudad,
            subtotal,
            minimo_mayorista,
            moneda,
            estado,
            fecha_creacion,
            fecha_actualizacion

        FROM pedidos_mayoristas

        WHERE id = :id

        LIMIT 1
    ");

    $consultaPedido->execute([
        ":id" => $id
    ]);

    $pedido = $consultaPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {

        responder(
            false,
            "No se encontró el pedido solicitado.",
            404
        );
    }

    // --------------------------------------------------
    // 6. CONSULTAR PRODUCTOS DEL PEDIDO
    // --------------------------------------------------

    $consultaDetalles = $connection->prepare("
        SELECT
            id,
            pedido_id,
            producto_id,
            nombre_producto,
            cantidad,
            precio_unitario,
            subtotal,
            fecha_creacion

        FROM pedido_mayorista_detalles

        WHERE pedido_id = :pedido_id

        ORDER BY id ASC
    ");

    $consultaDetalles->execute([
        ":pedido_id" => $id
    ]);

    $detalles = $consultaDetalles->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // 7. DEVOLVER INFORMACIÓN
    // --------------------------------------------------

    responder(
        true,
        "Detalle del pedido consultado correctamente.",
        200,
        [
            "pedido" => $pedido,
            "productos" => $detalles
        ]
    );

} catch (Throwable $e) {

    // Registrar el error internamente
    error_log(
        "Error consultando detalle del pedido mayorista: " .
        $e->getMessage()
    );

    responder(
        false,
        "No fue posible consultar el detalle del pedido.",
        500
    );
}
