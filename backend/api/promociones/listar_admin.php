
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión administrativa
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    empty($_SESSION['admin']['id'])
) {
    responder(401, [
        'success' => false,
        'message' => 'No autorizado.'
    ]);
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

try {

    $database = new Database();
    $pdo = $database->connect();

    $sql = "SELECT
                pr.id,
                pr.producto_id,
                pr.variante_id,
                pr.nombre,
                pr.precio_minorista_promocional,
                pr.precio_mayorista_promocional,
                pr.inicia_en,
                pr.termina_en,
                pr.activo,
                pr.creado_en,
                pr.actualizado_en,

                p.nombre AS producto_nombre,
                p.precio_minorista AS producto_precio_minorista,
                p.precio_mayorista AS producto_precio_mayorista

            FROM promociones pr

            INNER JOIN productos p
                ON p.id = pr.producto_id

            ORDER BY pr.creado_en DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    responder(200, [
        'success' => true,
        'total' => count($promociones),
        'data' => $promociones
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al listar promociones administrativas: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Error al listar las promociones.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al listar promociones: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
