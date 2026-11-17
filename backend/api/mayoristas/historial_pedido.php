
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
        "No tienes autorización para consultar el historial.",
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
    // 3. VALIDAR IDENTIFICADOR DEL PEDIDO
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

    $pedidoId = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        [
            "options" => [
                "min_range" => 1
            ]
        ]
    );

    if ($pedidoId === false) {
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
    // 5. VERIFICAR QUE EXISTA EL PEDIDO
    // --------------------------------------------------

    $consultaPedido = $connection->prepare("
        SELECT id, referencia
        FROM pedidos_mayoristas
        WHERE id = :id
        LIMIT 1
    ");

    $consultaPedido->execute([
        ":id" => $pedidoId
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
    // 6. CONSULTAR HISTORIAL
    // --------------------------------------------------

    $consultaHistorial = $connection->prepare("
        SELECT
            id,
            pedido_id,
            estado_anterior,
            estado_nuevo,
            administrador_id,
            administrador_nombre,
            observacion,
            fecha_cambio

        FROM historial_pedidos_mayoristas

        WHERE pedido_id = :pedido_id

        ORDER BY fecha_cambio ASC, id ASC
    ");

    $consultaHistorial->execute([
        ":pedido_id" => $pedidoId
    ]);

    $historial = $consultaHistorial->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // 7. DEVOLVER RESULTADOS
    // --------------------------------------------------

    responder(
        true,
        "Historial consultado correctamente.",
        200,
        [
            "pedido" => [
                "id" => (int) $pedido["id"],
                "referencia" => $pedido["referencia"]
            ],
            "total_eventos" => count($historial),
            "historial" => $historial
        ]
    );

} catch (Throwable $e) {

    error_log(
        "Error consultando historial mayorista: " .
        $e->getMessage()
    );

    responder(
        false,
        "No fue posible consultar el historial del pedido.",
        500
    );
}
