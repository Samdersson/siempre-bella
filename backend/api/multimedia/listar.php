
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

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

    // 4. Parámetros de búsqueda
    $buscar = trim($_GET['buscar'] ?? '');
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $limite = (int)($_GET['limite'] ?? 20);

    $limite = max(1, min($limite, 100));

    $desplazamiento = ($pagina - 1) * $limite;

    // 5. Construir filtros
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

    // 6. Consultar total de recursos
    $consultaTotal = $conexion->prepare(
        "SELECT COUNT(*)
         FROM recursos_multimedia
         WHERE $where"
    );

    $consultaTotal->execute($parametros);

    $total = (int)$consultaTotal->fetchColumn();

    // 7. Consultar recursos multimedia
    $consulta = $conexion->prepare(
        "SELECT
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
         LIMIT $limite OFFSET $desplazamiento"
    );

    $consulta->execute($parametros);

    $recursos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // 8. Preparar URL pública
    foreach ($recursos as &$recurso) {
        $recurso['url'] = '/' . ltrim(
            $recurso['ruta_archivo'],
            '/'
        );
    }

    unset($recurso);

    // 9. Respuesta
    echo json_encode([
        'success' => true,
        'total' => $total,
        'pagina' => $pagina,
        'limite' => $limite,
        'total_paginas' => (int)ceil($total / $limite),
        'data' => $recursos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible consultar la biblioteca multimedia.'
    ], JSON_UNESCAPED_UNICODE);
}
