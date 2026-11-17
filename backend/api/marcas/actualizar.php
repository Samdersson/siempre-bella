
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión administrativa
if (
    !isset($_SESSION['admin']) ||
    !is_array($_SESSION['admin']) ||
    !isset($_SESSION['admin']['id'])
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado. Inicia sesión nuevamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar token CSRF
exigirTokenCSRF();

try {
    $database = new Database();
    $pdo = $database->connect();

    // Leer JSON
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar tipos de datos
    if (
        !isset($datos['id']) ||
        !filter_var($datos['id'], FILTER_VALIDATE_INT) ||
        (int)$datos['id'] <= 0 ||
        !isset($datos['nombre']) ||
        !is_string($datos['nombre']) ||
        (isset($datos['descripcion']) && !is_string($datos['descripcion'])) ||
        (isset($datos['activo']) && !in_array($datos['activo'], [0, 1, '0', '1'], true))
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los campos enviados tienen un formato inválido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int)$datos['id'];
    $nombre = trim($datos['nombre']);
    $descripcion = trim($datos['descripcion'] ?? '');
    $activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;

    // Validar nombre
    if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nombre es obligatorio y no puede superar los 120 caracteres.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($descripcion, 'UTF-8') > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La descripción no puede superar los 255 caracteres.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($activo, [0, 1], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El estado seleccionado no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Comprobar que la marca existe
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas WHERE id = :id LIMIT 1"
    );

    $consulta->execute(['id' => $id]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La marca no existe.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Comprobar nombre duplicado
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas
         WHERE nombre = :nombre AND id != :id
         LIMIT 1"
    );

    $consulta->execute([
        'nombre' => $nombre,
        'id' => $id
    ]);

    if ($consulta->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe otra marca con ese nombre.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generar slug
    $slugBase = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);

    if ($slugBase === false) {
        throw new RuntimeException('No fue posible procesar el nombre.');
    }

    $slugBase = strtolower($slugBase);
    $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase);
    $slugBase = trim($slugBase, '-');

    if ($slugBase === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fue posible generar el identificador de la marca.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $slugBase = substr($slugBase, 0, 140);
    $slug = $slugBase;
    $contador = 1;

    // Garantizar slug único
    while ($contador <= 10000) {
        $consulta = $pdo->prepare(
            "SELECT id FROM marcas
             WHERE slug = :slug AND id != :id
             LIMIT 1"
        );

        $consulta->execute([
            'slug' => $slug,
            'id' => $id
        ]);

        if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
            break;
        }

        $sufijo = '-' . $contador++;
        $slug = substr(
            $slugBase,
            0,
            140 - strlen($sufijo)
        ) . $sufijo;
    }

    if ($contador > 10000) {
        throw new RuntimeException('No fue posible generar un slug único.');
    }

    // Actualizar marca
    $sql = "UPDATE marcas
            SET nombre = :nombre,
                slug = :slug,
                descripcion = :descripcion,
                activo = :activo
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'nombre' => $nombre,
        'slug' => $slug,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'activo' => $activo,
        'id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Marca actualizada correctamente.',
        'marca_id' => $id,
        'slug' => $slug
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log('Error al actualizar marca: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la marca.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('Error inesperado al actualizar marca: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado al actualizar la marca.'
    ], JSON_UNESCAPED_UNICODE);
}
