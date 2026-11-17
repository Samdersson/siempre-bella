
<?php

require_once __DIR__ . '/session.php';

/**
 * Genera un token CSRF asociado a la sesión actual.
 */
function generarTokenCSRF(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        strlen($_SESSION['csrf_token']) !== 64
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF recibido en la cabecera HTTP.
 */
function validarTokenCSRF(): bool
{
    $tokenSesion = $_SESSION['csrf_token'] ?? null;

    $tokenRecibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (
        !is_string($tokenSesion) ||
        !is_string($tokenRecibido) ||
        $tokenSesion === '' ||
        $tokenRecibido === ''
    ) {
        return false;
    }

    return hash_equals($tokenSesion, $tokenRecibido);
}

/**
 * Rechaza solicitudes sin un token CSRF válido.
 */
function exigirTokenCSRF(): void
{
    if (!validarTokenCSRF()) {
        http_response_code(403);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode([
            'success' => false,
            'message' => 'La solicitud no superó la validación de seguridad. Recarga la página e inténtalo nuevamente.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
