
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";

function responder($success, $mensaje, $codigo = 200, $datos = []) {
    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje,
        "data" => $datos
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Verificar sesión administrativa
if (!isset($_SESSION["admin"])) {
    responder(false, "No tienes autorización.", 401);
}

// Solo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método no permitido.", 405);
}

try {

    $entrada = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($entrada)) {
        responder(false, "Datos no válidos.", 400);
    }

    $id = filter_var(
        $entrada["id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    $estado = trim($entrada["estado"] ?? "");

    $estadosPermitidos = [
        "pendiente",
        "contactado",
        "confirmado",
        "preparando",
        "enviado",
        "cancelado"
    ];

    if ($id === false) {
        responder(false, "Identificador de pedido no válido.", 400);
    }

    if (!in_array($estado, $estadosPermitidos, true)) {
        responder(false, "El estado seleccionado no es válido.", 400);
    }

    $database = new Database();
    $connection = $database->connect();

    // Comprobar que existe el pedido
    $consulta = $connection->prepare("
        SELECT id, referencia, estado
        FROM pedidos_mayoristas
        WHERE id = :id
        LIMIT 1
    ");

    $consulta->execute([":id" => $id]);

    $pedido = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        responder(false, "No se encontró el pedido.", 404);
    }

    // Actualizar estado
    $actualizar = $connection->prepare("
        UPDATE pedidos_mayoristas
        SET estado = :estado
        WHERE id = :id
    ");

    $actualizar->execute([
        ":estado" => $estado,
        ":id" => $id
    ]);

    responder(
        true,
        "Estado del pedido actualizado correctamente.",
        200,
        [
            "id" => (int) $pedido["id"],
            "referencia" => $pedido["referencia"],
            "estado_anterior" => $pedido["estado"],
            "estado_nuevo" => $estado
        ]
    );

} catch (Throwable $e) {

    error_log(
        "Error actualizando estado mayorista: " . $e->getMessage()
    );

    responder(
        false,
        "No fue posible actualizar el estado del pedido.",
        500
    );
}
