
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

$_SESSION = [];

$params = session_get_cookie_params();

setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $params['path'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite']
]);

session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Sesión cerrada correctamente.'
]);
