
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/session.php";

// Proteger el endpoint: solo administradores autenticados.
if (!isset($_SESSION["admin"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "No tienes autorización para consultar los pedidos."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $connection = $database->connect();

    // Parámetros de búsqueda
    $busqueda = trim($_GET["buscar"] ?? "");
    $estado = trim($_GET["estado"] ?? "");

    $estadosPermitidos = [
        "pendiente",
        "contactado",
        "confirmado",
        "preparando",
        "enviado",
        "cancelado"
    ];

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

    if ($estado !== "" && in_array($estado, $estadosPermitidos, true)) {
        $condiciones[] = "p.estado = :estado";
        $parametros[":estado"] = $estado;
    }

    $where = count($condiciones) > 0
        ? "WHERE " . implode(" AND ", $condiciones)
        : "";

    // Consultar pedidos y cantidad de productos por pedido.
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

            COALESCE(detalles.total_productos, 0) AS total_productos,
            COALESCE(detalles.total_unidades, 0) AS total_unidades

        FROM pedidos_mayoristas p

        LEFT JOIN (
            SELECT
                pedido_id,
                COUNT(*) AS total_productos,
                SUM(cantidad) AS total_unidades
            FROM pedido_mayorista_detalles
            GROUP BY pedido_id
        ) detalles ON detalles.pedido_id = p.id

        $where

        ORDER BY p.fecha_creacion DESC, p.id DESC
        LIMIT 100
    ";

    $consulta = $connection->prepare($sql);
    $consulta->execute($parametros);

    $pedidos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas generales de pedidos.
    $consultaEstadisticas = $connection->query("
        SELECT
            COUNT(*) AS total,
            SUM(estado = 'pendiente') AS pendientes,
            SUM(estado = 'contactado') AS contactados,
            SUM(estado = 'confirmado') AS confirmados,
            SUM(estado = 'preparando') AS preparando,
            SUM(estado = 'enviado') AS enviados,
            SUM(estado = 'cancelado') AS cancelados
        FROM pedidos_mayoristas
    ");

    $estadisticas = $consultaEstadisticas->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Pedidos consultados correctamente.",
        "total" => count($pedidos),
        "estadisticas" => $estadisticas,
        "data" => $pedidos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        "Error consultando pedidos mayoristas: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar los pedidos."
    ], JSON_UNESCAPED_UNICODE);
}
