<?php

require_once __DIR__ . '/../../config/session.php';

// Evitar almacenamiento de respuestas de autenticación en caché.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Solo permitir solicitudes GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Comprobar sesión administrativa.
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    empty($_SESSION['admin']['id'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'No hay una sesión activa.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Sesión válida.
echo json_encode([
    'success' => true,
    'authenticated' => true,
    'usuario' => [
        'id' => $_SESSION['admin']['id'],
        'nombre' => $_SESSION['admin']['nombre'],
        'email' => $_SESSION['admin']['email']
    ]
], JSON_UNESCAPED_UNICODE);

exit;