
<?php

header("Content-Type: application/json; charset=utf-8");

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
    responder(false, "No tienes autorización.", 401);
}

// --------------------------------------------------
// 2. VALIDAR MÉTODO HTTP
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método no permitido.", 405);
}

$connection = null;

try {

    // --------------------------------------------------
    // 3. RECIBIR Y VALIDAR JSON
    // --------------------------------------------------

    $contenido = file_get_contents("php://input");

    $entrada = json_decode($contenido, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($entrada)
    ) {
        responder(false, "Los datos enviados no son válidos.", 400);
    }

    // --------------------------------------------------
    // 4. VALIDAR IDENTIFICADOR
    // --------------------------------------------------

    $idRecibido = $entrada["id"] ?? null;

    if (!is_scalar($idRecibido)) {
        responder(
            false,
            "Identificador de pedido no válido.",
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
            "Identificador de pedido no válido.",
            400
        );
    }

    // --------------------------------------------------
    // 5. VALIDAR ESTADO
    // --------------------------------------------------

    $estadoRecibido = $entrada["estado"] ?? null;

    if (!is_string($estadoRecibido)) {
        responder(
            false,
            "El estado enviado no es válido.",
            400
        );
    }

    $estado = trim($estadoRecibido);

    $estadosPermitidos = [
        "pendiente",
        "contactado",
        "confirmado",
        "preparando",
        "enviado",
        "cancelado"
    ];

    if (!in_array($estado, $estadosPermitidos, true)) {
        responder(
            false,
            "El estado seleccionado no es válido.",
            400
        );
    }

    // --------------------------------------------------
    // 6. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();

    $connection = $database->connect();

    if (!$connection instanceof PDO) {
        throw new RuntimeException(
            "No fue posible conectar con la base de datos."
        );
    }

    // --------------------------------------------------
    // 7. INICIAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->beginTransaction();

    // Consultar y bloquear el pedido durante la operación.
    $consulta = $connection->prepare("
        SELECT
            id,
            referencia,
            estado
        FROM pedidos_mayoristas
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");

    $consulta->execute([
        ":id" => $id
    ]);

    $pedido = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $connection->rollBack();

        responder(
            false,
            "No se encontró el pedido.",
            404
        );
    }

    $estadoAnterior = $pedido["estado"];

    // --------------------------------------------------
    // 8. EVITAR ACTUALIZACIONES INNECESARIAS
    // --------------------------------------------------

    if ($estadoAnterior === $estado) {

        $connection->commit();

        responder(
            true,
            "El pedido ya tenía ese estado.",
            200,
            [
                "id" => (int) $pedido["id"],
                "referencia" => $pedido["referencia"],
                "estado_anterior" => $estadoAnterior,
                "estado_nuevo" => $estado,
                "cambio_realizado" => false
            ]
        );
    }

    // --------------------------------------------------
    // 9. ACTUALIZAR ESTADO
    // --------------------------------------------------

    $actualizar = $connection->prepare("
        UPDATE pedidos_mayoristas
        SET estado = :estado
        WHERE id = :id
    ");

    $actualizar->execute([
        ":estado" => $estado,
        ":id" => $id
    ]);

    // --------------------------------------------------
    // 10. CONFIRMAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->commit();

    // --------------------------------------------------
    // 11. DEVOLVER RESULTADO
    // --------------------------------------------------

    responder(
        true,
        "Estado del pedido actualizado correctamente.",
        200,
        [
            "id" => (int) $pedido["id"],
            "referencia" => $pedido["referencia"],
            "estado_anterior" => $estadoAnterior,
            "estado_nuevo" => $estado,
            "cambio_realizado" => true
        ]
    );

} catch (Throwable $e) {

    // --------------------------------------------------
    // 12. REVERTIR OPERACIÓN SI OCURRE UN ERROR
    // --------------------------------------------------

    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    error_log(
        "Error actualizando estado mayorista: " .
        $e->getMessage()
    );

    responder(
        false,
        "No fue posible actualizar el estado del pedido.",
        500
    );
}
