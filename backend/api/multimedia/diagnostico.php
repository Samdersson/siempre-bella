
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    $base = $conexion->query(
        "SELECT DATABASE() AS base_datos,
                @@hostname AS servidor,
                @@port AS puerto"
    )->fetch();

    $columnas = $conexion->query(
        "SHOW COLUMNS FROM recursos_multimedia"
    )->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'conexion' => $base,
        'columnas' => $columnas,
        'existe_tamano_bytes' => in_array('tamano_bytes', $columnas, true)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    error_log($e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar la estructura.'
    ], JSON_UNESCAPED_UNICODE);
}
