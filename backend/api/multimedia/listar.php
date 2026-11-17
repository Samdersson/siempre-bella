
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// 1. Verificar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    // 3. Conectar con la base de datos
    $database = new Database();
    $conexion = $database->connect();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.'
        );
    }

    // 4. Validar parámetros de búsqueda
    $buscar = $_GET['buscar'] ?? '';

    if (!is_string($buscar)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'El término de búsqueda no es válido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $buscar = trim($buscar);

    if (mb_strlen($buscar, 'UTF-8') > 255) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'El término de búsqueda es demasiado largo.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 5. Validar número de página
    $paginaRecibida = $_GET['pagina'] ?? '1';

    $pagina = filter_var(
        $paginaRecibida,
        FILTER_VALIDATE_INT
    );

    if (
        $pagina === false ||
        $pagina === null ||
        $pagina < 1 ||
        $pagina > 1000000
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'El número de página no es válido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 6. Validar límite de resultados
    $limiteRecibido = $_GET['limite'] ?? '20';

    $limite = filter_var(
        $limiteRecibido,
        FILTER_VALIDATE_INT
    );

    if (
        $limite === false ||
        $limite === null ||
        $limite < 1 ||
        $limite > 100
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'El límite de resultados no es válido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $desplazamiento = ($pagina - 1) * $limite;

    // 7. Construir filtros
    $condiciones = ['1 = 1'];
    $parametros = [];

    if ($buscar !== '') {

        $condiciones[] = "(
            nombre_original LIKE ?
            OR nombre_archivo LIKE ?
            OR texto_alternativo LIKE ?
        )";

        $termino = '%' . $buscar . '%';

        $parametros[] = $termino;
        $parametros[] = $termino;
        $parametros[] = $termino;
    }

    $where = implode(' AND ', $condiciones);

    // 8. Consultar total de recursos
    $consultaTotal = $conexion->prepare("
        SELECT COUNT(*)
        FROM recursos_multimedia
        WHERE $where
    ");

    $consultaTotal->execute($parametros);

    $total = (int) $consultaTotal->fetchColumn();

    // 9. Consultar recursos multimedia
    $consulta = $conexion->prepare("
        SELECT
            id,
            nombre_original,
            nombre_archivo,
            ruta_archivo,
            tipo_mime,
            extension,
            tamanio_bytes,
            texto_alternativo,
            activo,
            fecha_creacion,
            fecha_actualizacion
        FROM recursos_multimedia
        WHERE $where
        ORDER BY fecha_creacion DESC, id DESC
        LIMIT $limite OFFSET $desplazamiento
    ");

    $consulta->execute($parametros);

    $recursos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // 10. Preparar URL pública
    foreach ($recursos as &$recurso) {

        $recurso['url'] = '/' . ltrim(
            $recurso['ruta_archivo'],
            '/'
        );
    }

    unset($recurso);

    // 11. Calcular total de páginas
    $totalPaginas = (int) ceil($total / $limite);

    // 12. Respuesta
    echo json_encode([
        'success' => true,
        'total' => $total,
        'pagina' => $pagina,
        'limite' => $limite,
        'total_paginas' => $totalPaginas,
        'data' => $recursos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    error_log(
        'Error al listar biblioteca multimedia: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar la biblioteca multimedia.'
    ], JSON_UNESCAPED_UNICODE);
}
