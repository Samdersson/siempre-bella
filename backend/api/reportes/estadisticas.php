
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No tienes autorización para consultar estos reportes.'
    ]);

    exit;
}

try {
    $database = new Database();
    $connection = $database->connect();

    // Validar fechas recibidas
    $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

    $validarFecha = function ($fecha) {
        $formato = DateTime::createFromFormat('!Y-m-d', $fecha);

        return $formato && $formato->format('Y-m-d') === $fecha;
    };

    if (!$validarFecha($fechaInicio) || !$validarFecha($fechaFin)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Las fechas proporcionadas no tienen un formato válido.'
        ]);

        exit;
    }

    if ($fechaInicio > $fechaFin) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'La fecha inicial no puede ser posterior a la fecha final.'
        ]);

        exit;
    }

    // Parámetros comunes para las consultas
    $parametros = [
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin
    ];

    $condicionFecha = "
        fecha_creacion >= :fecha_inicio
        AND fecha_creacion < DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
    ";

    // 1. Resumen general
    $sqlResumen = "
        SELECT
            COUNT(*) AS total_pedidos,

            COALESCE(SUM(subtotal), 0) AS valor_pedidos,

            COALESCE(SUM(
                CASE
                    WHEN estado != 'cancelado' THEN subtotal
                    ELSE 0
                END
            ), 0) AS valor_pedidos_no_cancelados,

            COUNT(DISTINCT telefono) AS clientes_unicos,

            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,

            SUM(CASE WHEN estado = 'contactado' THEN 1 ELSE 0 END) AS contactados,

            SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) AS confirmados,

            SUM(CASE WHEN estado = 'preparando' THEN 1 ELSE 0 END) AS preparando,

            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) AS enviados,

            SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados

        FROM pedidos_mayoristas
        WHERE $condicionFecha
    ";

    $stmt = $connection->prepare($sqlResumen);
    $stmt->execute($parametros);

    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Productos más solicitados
    $sqlProductos = "
        SELECT
            d.nombre_producto,
            SUM(d.cantidad) AS total_unidades,
            COUNT(DISTINCT d.pedido_id) AS total_pedidos,
            SUM(d.subtotal) AS valor_solicitado

        FROM pedido_mayorista_detalles d

        INNER JOIN pedidos_mayoristas p
            ON p.id = d.pedido_id

        WHERE
            p.fecha_creacion >= :fecha_inicio
            AND p.fecha_creacion < DATE_ADD(:fecha_fin, INTERVAL 1 DAY)

        GROUP BY d.nombre_producto
        ORDER BY total_unidades DESC
        LIMIT 10
    ";

    $stmt = $connection->prepare($sqlProductos);
    $stmt->execute($parametros);

    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Clientes con más pedidos
    $sqlClientes = "
        SELECT
            telefono,
            MAX(nombre_cliente) AS nombre_cliente,
            MAX(ciudad) AS ciudad,
            COUNT(*) AS total_pedidos,
            SUM(subtotal) AS valor_pedidos,
            MAX(fecha_creacion) AS ultimo_pedido

        FROM pedidos_mayoristas

        WHERE $condicionFecha

        GROUP BY telefono
        ORDER BY total_pedidos DESC, valor_pedidos DESC
        LIMIT 10
    ";

    $stmt = $connection->prepare($sqlClientes);
    $stmt->execute($parametros);

    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Pedidos agrupados por ciudad
    $sqlCiudades = "
        SELECT
            ciudad,
            COUNT(*) AS total_pedidos,
            SUM(subtotal) AS valor_pedidos

        FROM pedidos_mayoristas

        WHERE $condicionFecha

        GROUP BY ciudad
        ORDER BY total_pedidos DESC
        LIMIT 10
    ";

    $stmt = $connection->prepare($sqlCiudades);
    $stmt->execute($parametros);

    $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Distribución por estado
    $sqlEstados = "
        SELECT
            estado,
            COUNT(*) AS total,
            SUM(subtotal) AS valor_pedidos

        FROM pedidos_mayoristas

        WHERE $condicionFecha

        GROUP BY estado
        ORDER BY total DESC
    ";

    $stmt = $connection->prepare($sqlEstados);
    $stmt->execute($parametros);

    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta JSON
    echo json_encode([
        'success' => true,
        'message' => 'Reportes consultados correctamente.',

        'periodo' => [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ],

        'resumen' => [
            'total_pedidos' => (int) $resumen['total_pedidos'],
            'valor_pedidos' => (float) $resumen['valor_pedidos'],
            'valor_pedidos_no_cancelados' => (float) $resumen['valor_pedidos_no_cancelados'],
            'clientes_unicos' => (int) $resumen['clientes_unicos'],
            'pendientes' => (int) $resumen['pendientes'],
            'contactados' => (int) $resumen['contactados'],
            'confirmados' => (int) $resumen['confirmados'],
            'preparando' => (int) $resumen['preparando'],
            'enviados' => (int) $resumen['enviados'],
            'cancelados' => (int) $resumen['cancelados']
        ],

        'productos' => $productos,
        'clientes' => $clientes,
        'ciudades' => $ciudades,
        'estados' => $estados

    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('Error en reportes comerciales: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible generar los reportes comerciales.'
    ]);
}
