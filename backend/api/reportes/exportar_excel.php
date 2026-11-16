
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => 'No tienes autorización para exportar estos reportes.'
    ]);

    exit;
}

try {

    $database = new Database();
    $connection = $database->connect();

    // Validar fechas
    $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

    $validarFecha = function ($fecha) {
        if (!is_string($fecha)) {
            return false;
        }

        $formato = DateTime::createFromFormat('!Y-m-d', $fecha);

        return $formato && $formato->format('Y-m-d') === $fecha;
    };

    if (!$validarFecha($fechaInicio) || !$validarFecha($fechaFin)) {
        http_response_code(400);
        exit('Las fechas proporcionadas no son válidas.');
    }

    if ($fechaInicio > $fechaFin) {
        http_response_code(400);
        exit('La fecha inicial no puede ser posterior a la fecha final.');
    }

    $parametros = [
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin
    ];

    $condicionFecha = "
        fecha_creacion >= :fecha_inicio
        AND fecha_creacion < DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
    ";

    // ==========================================
    // 1. RESUMEN GENERAL
    // ==========================================

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

    // ==========================================
    // 2. PRODUCTOS
    // ==========================================

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

    // ==========================================
    // 3. CLIENTES
    // ==========================================

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

    // ==========================================
    // 4. CIUDADES
    // ==========================================

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

    // ==========================================
    // 5. ESTADOS
    // ==========================================

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

    // ==========================================
    // CONFIGURAR LIBRO EXCEL
    // ==========================================

    $spreadsheet = new Spreadsheet();

    $spreadsheet->getProperties()
        ->setCreator('Siempre Bella')
        ->setTitle('Reporte comercial Siempre Bella')
        ->setSubject('Reporte de pedidos mayoristas')
        ->setDescription('Reporte comercial generado desde el panel administrativo.');

    $colorPrincipal = '26354A';
    $colorSecundario = 'E9EEF5';
    $colorTexto = 'FFFFFF';

    // Función para crear encabezados y formato
    $prepararHoja = function (
        $hoja,
        $titulo,
        $encabezados,
        $fechaInicio,
        $fechaFin
    ) use ($colorPrincipal, $colorSecundario, $colorTexto) {

        $hoja->setCellValue('A1', $titulo);
        $hoja->mergeCells('A1:' . chr(64 + count($encabezados)) . '1');

        $hoja->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => $colorTexto]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $colorPrincipal]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $hoja->getRowDimension(1)->setRowHeight(30);

        $hoja->setCellValue('A2', 'Periodo:');
        $hoja->setCellValue('B2', $fechaInicio . ' al ' . $fechaFin);

        $hoja->getStyle('A2:B2')->getFont()->setBold(true);

        foreach ($encabezados as $indice => $encabezado) {
            $columna = chr(65 + $indice);
            $hoja->setCellValue($columna . '4', $encabezado);
        }

        $ultimaColumna = chr(64 + count($encabezados));

        $hoja->getStyle('A4:' . $ultimaColumna . '4')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $colorTexto]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $colorPrincipal]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $hoja->getRowDimension(4)->setRowHeight(25);

        $hoja->freezePane('A5');
        $hoja->setAutoFilter('A4:' . $ultimaColumna . '4');

        $hoja->getDefaultRowDimension()->setRowHeight(21);
    };

    // Función para escribir texto como texto explícito.
    // Evita que datos externos se interpreten como fórmulas.
    $escribirTexto = function ($hoja, $celda, $valor) {
        $hoja->setCellValueExplicit(
            $celda,
            (string) ($valor ?? ''),
            DataType::TYPE_STRING
        );
    };

    // Función para ajustar columnas
    $ajustarColumnas = function ($hoja, $cantidad) {
        for ($i = 0; $i < $cantidad; $i++) {
            $columna = chr(65 + $i);
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }
    };

    // ==========================================
    // HOJA 1: RESUMEN
    // ==========================================

    $hoja = $spreadsheet->getActiveSheet();
    $hoja->setTitle('Resumen');

    $prepararHoja(
        $hoja,
        'REPORTE COMERCIAL - SIEMPRE BELLA',
        ['Indicador', 'Resultado'],
        $fechaInicio,
        $fechaFin
    );

    $filasResumen = [
        ['Total de pedidos', (int) $resumen['total_pedidos']],
        ['Valor total de pedidos', (float) $resumen['valor_pedidos']],
        ['Valor de pedidos no cancelados', (float) $resumen['valor_pedidos_no_cancelados']],
        ['Clientes únicos', (int) $resumen['clientes_unicos']],
        ['Pedidos pendientes', (int) $resumen['pendientes']],
        ['Pedidos contactados', (int) $resumen['contactados']],
        ['Pedidos confirmados', (int) $resumen['confirmados']],
        ['Pedidos preparando', (int) $resumen['preparando']],
        ['Pedidos enviados', (int) $resumen['enviados']],
        ['Pedidos cancelados', (int) $resumen['cancelados']]
    ];

    $fila = 5;

    foreach ($filasResumen as $dato) {
        $escribirTexto($hoja, 'A' . $fila, $dato[0]);
        $hoja->setCellValue('B' . $fila, $dato[1]);
        $fila++;
    }

    $hoja->getStyle('B6:B7')
        ->getNumberFormat()
        ->setFormatCode('"$" #,##0');

    $ajustarColumnas($hoja, 2);

    // ==========================================
    // HOJA 2: PRODUCTOS
    // ==========================================

    $hoja = $spreadsheet->createSheet();
    $hoja->setTitle('Productos');

    $prepararHoja(
        $hoja,
        'PRODUCTOS MAS SOLICITADOS',
        ['Producto', 'Unidades', 'Pedidos', 'Valor solicitado'],
        $fechaInicio,
        $fechaFin
    );

    $fila = 5;

    foreach ($productos as $producto) {
        $escribirTexto($hoja, 'A' . $fila, $producto['nombre_producto']);
        $hoja->setCellValue('B' . $fila, (int) $producto['total_unidades']);
        $hoja->setCellValue('C' . $fila, (int) $producto['total_pedidos']);
        $hoja->setCellValue('D' . $fila, (float) $producto['valor_solicitado']);
        $fila++;
    }

    if ($fila > 5) {
        $hoja->getStyle('D5:D' . ($fila - 1))
            ->getNumberFormat()
            ->setFormatCode('"$" #,##0');
    }

    $ajustarColumnas($hoja, 4);

    // ==========================================
    // HOJA 3: CLIENTES
    // ==========================================

    $hoja = $spreadsheet->createSheet();
    $hoja->setTitle('Clientes');

    $prepararHoja(
        $hoja,
        'CLIENTES CON MAS PEDIDOS',
        ['Telefono', 'Cliente', 'Ciudad', 'Pedidos', 'Valor comprado', 'Ultimo pedido'],
        $fechaInicio,
        $fechaFin
    );

    $fila = 5;

    foreach ($clientes as $cliente) {
        $escribirTexto($hoja, 'A' . $fila, $cliente['telefono']);
        $escribirTexto($hoja, 'B' . $fila, $cliente['nombre_cliente']);
        $escribirTexto($hoja, 'C' . $fila, $cliente['ciudad']);

        $hoja->setCellValue('D' . $fila, (int) $cliente['total_pedidos']);
        $hoja->setCellValue('E' . $fila, (float) $cliente['valor_pedidos']);

        $escribirTexto($hoja, 'F' . $fila, $cliente['ultimo_pedido']);

        $fila++;
    }

    if ($fila > 5) {
        $hoja->getStyle('E5:E' . ($fila - 1))
            ->getNumberFormat()
            ->setFormatCode('"$" #,##0');
    }

    $ajustarColumnas($hoja, 6);

    // ==========================================
    // HOJA 4: CIUDADES
    // ==========================================

    $hoja = $spreadsheet->createSheet();
    $hoja->setTitle('Ciudades');

    $prepararHoja(
        $hoja,
        'PEDIDOS POR CIUDAD',
        ['Ciudad', 'Total pedidos', 'Valor pedidos'],
        $fechaInicio,
        $fechaFin
    );

    $fila = 5;

    foreach ($ciudades as $ciudad) {
        $escribirTexto($hoja, 'A' . $fila, $ciudad['ciudad']);
        $hoja->setCellValue('B' . $fila, (int) $ciudad['total_pedidos']);
        $hoja->setCellValue('C' . $fila, (float) $ciudad['valor_pedidos']);
        $fila++;
    }

    if ($fila > 5) {
        $hoja->getStyle('C5:C' . ($fila - 1))
            ->getNumberFormat()
            ->setFormatCode('"$" #,##0');
    }

    $ajustarColumnas($hoja, 3);

    // ==========================================
    // HOJA 5: ESTADOS
    // ==========================================

    $hoja = $spreadsheet->createSheet();
    $hoja->setTitle('Estados');

    $prepararHoja(
        $hoja,
        'DISTRIBUCION DE PEDIDOS POR ESTADO',
        ['Estado', 'Total pedidos', 'Valor pedidos'],
        $fechaInicio,
        $fechaFin
    );

    $fila = 5;

    foreach ($estados as $estado) {
        $escribirTexto($hoja, 'A' . $fila, ucfirst($estado['estado']));
        $hoja->setCellValue('B' . $fila, (int) $estado['total']);
        $hoja->setCellValue('C' . $fila, (float) $estado['valor_pedidos']);
        $fila++;
    }

    if ($fila > 5) {
        $hoja->getStyle('C5:C' . ($fila - 1))
            ->getNumberFormat()
            ->setFormatCode('"$" #,##0');
    }

    $ajustarColumnas($hoja, 3);

    // ==========================================
    // GENERAR Y DESCARGAR EXCEL
    // ==========================================

    $spreadsheet->setActiveSheetIndex(0);

    $nombreArchivo = 'Reporte_Siempre_Bella_' .
        $fechaInicio . '_al_' . $fechaFin . '.xlsx';

    // Evitar que cualquier salida previa dañe el archivo.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    $spreadsheet->disconnectWorksheets();
    exit;

} catch (Throwable $e) {

    error_log('Error exportando reportes Excel: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible generar el archivo Excel.'
    ]);

    exit;
}
