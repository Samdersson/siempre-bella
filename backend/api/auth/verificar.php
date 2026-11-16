
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'No hay una sesión activa.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'authenticated' => true,
    'usuario' => $_SESSION['admin']
]);
