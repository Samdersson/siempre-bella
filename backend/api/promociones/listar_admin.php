
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
                p.precio_mayorista AS producto_precio_mayorista,

                v.nombre AS variante_nombre,
                v.precio_minorista AS variante_precio_minorista,
                v.precio_mayorista AS variante_precio_mayorista

            FROM promociones pr

            INNER JOIN productos p
                ON p.id = pr.producto_id

            LEFT JOIN variantes v
                ON v.id = pr.variante_id

            ORDER BY pr.creado_en DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($promociones),
        'data' => $promociones
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al listar las promociones.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
