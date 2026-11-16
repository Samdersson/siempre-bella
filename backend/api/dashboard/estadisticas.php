
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No tienes autorización para consultar estas estadísticas.'
    ]);

    exit;
}

try {
    $database = new Database();
    $connection = $database->connect();

    // 1. Resumen general de pedidos y clientes
    $sqlResumen = "
        SELECT
            COUNT(*) AS total_pedidos,
            COALESCE(SUM(subtotal), 0) AS valor_pedidos,
            COUNT(DISTINCT telefono) AS clientes_unicos,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'contactado' THEN 1 ELSE 0 END) AS contactados,
            SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,
            SUM(CASE WHEN estado = 'preparando' THEN 1 ELSE 0 END) AS preparando,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) AS enviados,
            SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados
        FROM pedidos_mayoristas
    ";

    $resumen = $connection->query($sqlResumen)->fetch(PDO::FETCH_ASSOC);

    // 2. Clientes recurrentes
    $sqlClientes = "
        SELECT COUNT(*) AS clientes_recurrentes
        FROM (
            SELECT telefono
            FROM pedidos_mayoristas
            GROUP BY telefono
            HAVING COUNT(*) > 1
        ) AS clientes
    ";

    $clientesRecurrentes = $connection
        ->query($sqlClientes)
        ->fetch(PDO::FETCH_ASSOC);

    // 3. Pedidos agrupados por mes: últimos seis meses calendario
    $sqlMensual = "
        SELECT
            DATE_FORMAT(fecha_creacion, '%Y-%m') AS periodo,
            COUNT(*) AS total_pedidos,
            COALESCE(SUM(subtotal), 0) AS valor_pedidos
        FROM pedidos_mayoristas
        WHERE fecha_creacion >= DATE_FORMAT(
            DATE_SUB(CURDATE(), INTERVAL 5 MONTH),
            '%Y-%m-01'
        )
        GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m')
        ORDER BY periodo ASC
    ";

    $pedidosMensuales = $connection
        ->query($sqlMensual)
        ->fetchAll(PDO::FETCH_ASSOC);

    // 4. Distribución de pedidos por estado
    $sqlEstados = "
        SELECT
            estado,
            COUNT(*) AS total
        FROM pedidos_mayoristas
        GROUP BY estado
        ORDER BY total DESC
    ";

    $pedidosPorEstado = $connection
        ->query($sqlEstados)
        ->fetchAll(PDO::FETCH_ASSOC);

    // 5. Productos más solicitados
    // Se utiliza nombre_producto para conservar el historial.
    $sqlProductos = "
        SELECT
            nombre_producto,
            SUM(cantidad) AS total_unidades,
            COUNT(DISTINCT pedido_id) AS total_pedidos,
            SUM(subtotal) AS valor_solicitado
        FROM pedido_mayorista_detalles
        GROUP BY nombre_producto
        ORDER BY total_unidades DESC
        LIMIT 5
    ";

    $productosSolicitados = $connection
        ->query($sqlProductos)
        ->fetchAll(PDO::FETCH_ASSOC);

    // 6. Pedidos por ciudad
    $sqlCiudades = "
        SELECT
            ciudad,
            COUNT(*) AS total_pedidos
        FROM pedidos_mayoristas
        GROUP BY ciudad
        ORDER BY total_pedidos DESC
        LIMIT 5
    ";

    $pedidosPorCiudad = $connection
        ->query($sqlCiudades)
        ->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Estadísticas consultadas correctamente.',

        'resumen' => [
            'total_pedidos' => (int) $resumen['total_pedidos'],
            'valor_pedidos' => (float) $resumen['valor_pedidos'],
            'clientes_unicos' => (int) $resumen['clientes_unicos'],
            'clientes_recurrentes' => (int) $clientesRecurrentes['clientes_recurrentes'],
            'pendientes' => (int) $resumen['pendientes'],
            'contactados' => (int) $resumen['contactados'],
            'confirmados' => (int) $resumen['confirmados'],
            'preparando' => (int) $resumen['preparando'],
            'enviados' => (int) $resumen['enviados'],
            'cancelados' => (int) $resumen['cancelados']
        ],

        'pedidos_mensuales' => $pedidosMensuales,
        'pedidos_por_estado' => $pedidosPorEstado,
        'productos_mas_solicitados' => $productosSolicitados,
        'pedidos_por_ciudad' => $pedidosPorCiudad

    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('Error estadísticas Dashboard: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar las estadísticas.'
    ]);
}
