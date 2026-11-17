
<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";
require_once __DIR__ . "/../../config/csrf.php";

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

if (
    !isset($_SESSION["admin"]) ||
    !is_array($_SESSION["admin"])
) {
    responder(false, "No tienes autorización.", 401);
}

// Obtener identidad del administrador
$administradorId = filter_var(
    $_SESSION["admin"]["id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$administradorNombre = $_SESSION["admin"]["nombre"] ?? null;

if (
    $administradorId === false ||
    !is_string($administradorNombre) ||
    trim($administradorNombre) === ""
) {
    responder(
        false,
        "No fue posible identificar al administrador.",
        401
    );
}

$administradorNombre = trim($administradorNombre);

// --------------------------------------------------
// 2. VALIDAR MÉTODO HTTP
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    responder(
        false,
        "Método no permitido. Utiliza POST.",
        405
    );
}

// --------------------------------------------------
// 3. VALIDAR TOKEN CSRF
// --------------------------------------------------

exigirTokenCSRF();

// --------------------------------------------------
// 4. VALIDAR TIPO DE CONTENIDO
// --------------------------------------------------

$tipoContenido = strtolower(trim(
    explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]
));

if ($tipoContenido !== "application/json") {

    responder(
        false,
        "El contenido debe enviarse en formato JSON.",
        415
    );
}

// --------------------------------------------------
// 5. LIMITAR TAMAÑO DE SOLICITUD
// --------------------------------------------------

$longitud = (int) ($_SERVER["CONTENT_LENGTH"] ?? 0);

if ($longitud > 65536) {

    responder(
        false,
        "La solicitud supera el tamaño permitido.",
        413
    );
}

$connection = null;

try {

    // --------------------------------------------------
    // 6. RECIBIR Y VALIDAR JSON
    // --------------------------------------------------

    $contenido = file_get_contents("php://input");

    if ($contenido === false || trim($contenido) === "") {

        responder(
            false,
            "No se recibieron datos para actualizar.",
            400
        );
    }

    $entrada = json_decode($contenido, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($entrada)
    ) {

        responder(
            false,
            "Los datos enviados no son válidos.",
            400
        );
    }

    // --------------------------------------------------
    // 7. VALIDAR IDENTIFICADOR
    // --------------------------------------------------

    $idRecibido = $entrada["id"] ?? null;

    if (
        !is_string($idRecibido) &&
        !is_int($idRecibido)
    ) {

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
    // 8. VALIDAR ESTADO
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
    // 9. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();

    $connection = $database->connect();

    if (!$connection instanceof PDO) {

        throw new RuntimeException(
            "No fue posible conectar con la base de datos."
        );
    }

    // --------------------------------------------------
    // 10. INICIAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->beginTransaction();

    // Bloquear pedido durante la operación
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
    // 11. EVITAR CAMBIOS INNECESARIOS
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
    // 12. ACTUALIZAR ESTADO DEL PEDIDO
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
    // 13. REGISTRAR CAMBIO EN EL HISTORIAL
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
            :estado_anterior,
            :estado_nuevo,
            :administrador_id,
            :administrador_nombre,
            :observacion
        )
    ");

    $insertarHistorial->execute([
        ":pedido_id" => $id,
        ":estado_anterior" => $estadoAnterior,
        ":estado_nuevo" => $estado,
        ":administrador_id" => $administradorId,
        ":administrador_nombre" => $administradorNombre,
        ":observacion" => "Cambio de estado desde administración"
    ]);

    // --------------------------------------------------
    // 14. CONFIRMAR TRANSACCIÓN
    // --------------------------------------------------

    $connection->commit();

    // --------------------------------------------------
    // 15. DEVOLVER RESULTADO
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
            "cambio_realizado" => true,
            "administrador" => $administradorNombre
        ]
    );

} catch (Throwable $e) {

    // --------------------------------------------------
    // 16. REVERTIR OPERACIÓN SI OCURRE UN ERROR
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
