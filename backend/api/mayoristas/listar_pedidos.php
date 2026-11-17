
<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";

/**
 * Responder en formato JSON.
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
        "No tienes autorización para consultar los pedidos.",
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
    // 3. CONECTAR A LA BASE DE DATOS
    // --------------------------------------------------

    $database = new Database();
    $connection = $database->connect();

    if (!$connection instanceof PDO) {
        throw new RuntimeException(
            "No fue posible conectar con la base de datos."
        );
    }

    // --------------------------------------------------
    // 4. VALIDAR PARÁMETROS DE BÚSQUEDA
    // --------------------------------------------------

    $busquedaRecibida = $_GET["buscar"] ?? "";
    $estadoRecibido = $_GET["estado"] ?? "";

    if (
        !is_string($busquedaRecibida) ||
        !is_string($estadoRecibido)
    ) {
        responder(
            false,
            "Los filtros enviados no son válidos.",
            400
        );
    }

    $busqueda = trim($busquedaRecibida);
    $estado = trim($estadoRecibido);

    // Limitar longitud de búsqueda
    $longitudBusqueda = function_exists("mb_strlen")
        ? mb_strlen($busqueda, "UTF-8")
        : strlen($busqueda);

    if ($longitudBusqueda > 150) {
        responder(
            false,
            "La búsqueda supera el máximo de 150 caracteres.",
            400
        );
    }

    // --------------------------------------------------
    // 5. VALIDAR ESTADOS PERMITIDOS
    // --------------------------------------------------

    $estadosPermitidos = [
        "pendiente",
        "contactado",
        "confirmado",
        "preparando",
        "enviado",
        "cancelado"
    ];

    if (
        $estado !== "" &&
        !in_array($estado, $estadosPermitidos, true)
    ) {
        responder(
            false,
            "El estado seleccionado no es válido.",
            400
        );
    }

    // --------------------------------------------------
    // 6. CONSTRUIR FILTROS
    // --------------------------------------------------

    $condiciones = [];
    $parametros = [];

    if ($busqueda !== "") {

        $condiciones[] = "(
            p.referencia LIKE :referencia
            OR p.nombre_cliente LIKE :nombre
            OR p.telefono LIKE :telefono
        )";

        $termino = "%" . $busqueda . "%";

        $parametros[":referencia"] = $termino;
        $parametros[":nombre"] = $termino;
        $parametros[":telefono"] = $termino;
    }

    if ($estado !== "") {

        $condiciones[] = "p.estado = :estado";

        $parametros[":estado"] = $estado;
    }

    $where = count($condiciones) > 0
        ? "WHERE " . implode(" AND ", $condiciones)
        : "";

    // --------------------------------------------------
    // 7. CONSULTAR PEDIDOS
    // --------------------------------------------------

    $sql = "
        SELECT
            p.id,
            p.referencia,
            p.nombre_cliente,
            p.telefono,
            p.ciudad,
            p.subtotal,
            p.minimo_mayorista,
            p.moneda,
            p.estado,
            p.fecha_creacion,
            p.fecha_actualizacion,

            COALESCE(
                detalles.total_productos,
                0
            ) AS total_productos,

            COALESCE(
                detalles.total_unidades,
                0
            ) AS total_unidades

        FROM pedidos_mayoristas p

        LEFT JOIN (
            SELECT
                pedido_id,
                COUNT(*) AS total_productos,
                SUM(cantidad) AS total_unidades

            FROM pedido_mayorista_detalles

            GROUP BY pedido_id
        ) detalles
            ON detalles.pedido_id = p.id

        $where

        ORDER BY
            p.fecha_creacion DESC,
            p.id DESC

        LIMIT 100
    ";

    $consulta = $connection->prepare($sql);
    $consulta->execute($parametros);

    $pedidos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // 8. CONSULTAR ESTADÍSTICAS GENERALES
    // --------------------------------------------------

    // Estas estadísticas corresponden a todos los pedidos,
    // independientemente de los filtros de búsqueda.

    $consultaEstadisticas = $connection->query("
        SELECT
            COUNT(*) AS total,

            COALESCE(
                SUM(estado = 'pendiente'),
                0
            ) AS pendientes,

            COALESCE(
                SUM(estado = 'contactado'),
                0
            ) AS contactados,

            COALESCE(
                SUM(estado = 'confirmado'),
                0
            ) AS confirmados,

            COALESCE(
                SUM(estado = 'preparando'),
                0
            ) AS preparando,

            COALESCE(
                SUM(estado = 'enviado'),
                0
            ) AS enviados,

            COALESCE(
                SUM(estado = 'cancelado'),
                0
            ) AS cancelados

        FROM pedidos_mayoristas
    ");

    $estadisticas = $consultaEstadisticas->fetch(PDO::FETCH_ASSOC);

    if (!is_array($estadisticas)) {
        throw new RuntimeException(
            "No fue posible obtener las estadísticas."
        );
    }

    // Convertir estadísticas a números enteros
    foreach ($estadisticas as $clave => $valor) {
        $estadisticas[$clave] = (int) $valor;
    }

    // --------------------------------------------------
    // 9. DEVOLVER RESULTADOS
    // --------------------------------------------------

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Pedidos consultados correctamente.",
        "total" => count($pedidos),
        "estadisticas" => $estadisticas,
        "data" => $pedidos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;

} catch (Throwable $e) {

    // Registrar detalles únicamente en el log del servidor
    error_log(
        "Error consultando pedidos mayoristas: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar los pedidos.",
        "total" => 0,
        "estadisticas" => [],
        "data" => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}
