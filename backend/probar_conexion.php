<?php

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $pdo = $database->connect();

    $result = $pdo->query(
        "SELECT DATABASE() AS database_name, VERSION() AS version"
    )->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Conexión exitosa con Railway',
        'database' => $result['database_name'],
        'version' => $result['version']
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);

    $original = $e->getPrevious();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'technical_error' => $original
            ? $original->getMessage()
            : null
    ], JSON_PRETTY_PRINT);
}