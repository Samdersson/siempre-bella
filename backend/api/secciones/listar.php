
<?php

require_once __DIR__ . "/../../config/database.php";

// Respuesta en formato JSON
header("Content-Type: application/json; charset=utf-8");

try {

    // Conexión con la base de datos
    $database = new Database();
    $connection = $database->connect();

    // Consultar las secciones visibles
    $sql = "SELECT
                id,
                identificador,
                nombre,
                tipo_seccion,
                configuracion,
                orden,
                visible
            FROM secciones_tienda
            WHERE visible = 1
            ORDER BY orden ASC";

    $statement = $connection->prepare($sql);
    $statement->execute();

    $secciones = $statement->fetchAll();

    // Convertir configuracion JSON a estructura PHP
    foreach ($secciones as &$seccion) {
        $seccion["configuracion"] = json_decode(
            $seccion["configuracion"],
            true
        );
    }

    unset($seccion);

    // Respuesta de la API
    echo json_encode([
        "success" => true,
        "total" => count($secciones),
        "data" => $secciones
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar las secciones."
    ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

    error_log('ERROR SECCIONES: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "No fue posible consultar las secciones."
    ], JSON_UNESCAPED_UNICODE);

}