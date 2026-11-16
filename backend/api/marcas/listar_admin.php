
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

try {

    // Crear conexión con la base de datos
    $database = new Database();
    $pdo = $database->connect();

    $sql = "SELECT
                id,
                nombre,
                slug,
                descripcion,
                logo,
                activo,
                creado_en,
                actualizado_en
            FROM marcas
            ORDER BY nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($marcas),
        'data' => $marcas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al listar las marcas.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
