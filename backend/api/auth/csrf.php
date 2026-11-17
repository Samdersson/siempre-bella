
<?php

require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Solo permitir GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Exigir sesión administrativa.
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    empty($_SESSION['admin']['id'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No hay una sesión administrativa activa.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Generar u obtener el token de la sesión.
$token = generarTokenCSRF();

echo json_encode([
    'success' => true,
    'token' => $token
], JSON_UNESCAPED_UNICODE);

exit;
