
<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . "/../../config/session.php";

/**
 * Responder en formato JSON.
 */
function responder($success, $mensaje, $codigo = 200) {

    http_response_code($codigo);

    echo json_encode([
        "success" => $success,
        "message" => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// --------------------------------------------------
// 1. VALIDAR MÉTODO HTTP
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método no permitido.", 405);
}

try {

    // --------------------------------------------------
    // 2. LIMPIAR VARIABLES DE SESIÓN
    // --------------------------------------------------

    $_SESSION = [];

    // --------------------------------------------------
    // 3. ELIMINAR COOKIE DE SESIÓN
    // --------------------------------------------------

    $params = session_get_cookie_params();

    $opcionesCookie = [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => $params["samesite"]
    ];

    // Conservar el dominio original si está configurado.
    if (!empty($params["domain"])) {
        $opcionesCookie["domain"] = $params["domain"];
    }

    setcookie(
        session_name(),
        "",
        $opcionesCookie
    );

    // --------------------------------------------------
    // 4. DESTRUIR SESIÓN DEL SERVIDOR
    // --------------------------------------------------

    if (!session_destroy()) {
        throw new RuntimeException(
            "No fue posible destruir la sesión."
        );
    }

    // --------------------------------------------------
    // 5. CONFIRMAR CIERRE
    // --------------------------------------------------

    responder(
        true,
        "Sesión cerrada correctamente."
    );

} catch (Throwable $e) {

    error_log(
        "Error cerrando sesión administrativa: " .
        $e->getMessage()
    );

    responder(
        false,
        "No fue posible cerrar la sesión correctamente.",
        500
    );
}
