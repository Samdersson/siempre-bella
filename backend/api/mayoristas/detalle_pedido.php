
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";

// Protección administrativa
if (!isset($_SESSION["admin"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "No tienes autorización para consultar este pedido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar método
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($id === false) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "El identificador del pedido no es válido."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $database = new Database();
    $connection = $database->connect();

    // Consultar información principal
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
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "No se encontró el pedido solicitado."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Consultar productos del pedido
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

    echo json_encode([
        "success" => true,
        "message" => "Detalle del pedido consultado correctamente.",
        "data" => [
            "pedido" => $pedido,
            "productos" => $detalles
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        "Error consultando detalle del pedido mayorista: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar el detalle del pedido."
    ], JSON_UNESCAPED_UNICODE);
}
