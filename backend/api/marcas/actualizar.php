
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ]);
        exit;
    }

    $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre = trim($datos['nombre'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;

    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El identificador de la marca no es válido.'
        ]);
        exit;
    }

    if ($nombre === '' || mb_strlen($nombre) > 120) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nombre es obligatorio y no puede superar los 120 caracteres.'
        ]);
        exit;
    }

    if (mb_strlen($descripcion) > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La descripción no puede superar los 255 caracteres.'
        ]);
        exit;
    }

    if (!in_array($activo, [0, 1], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El estado seleccionado no es válido.'
        ]);
        exit;
    }

    // Comprobar que la marca existe
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas WHERE id = :id LIMIT 1"
    );
    $consulta->execute(['id' => $id]);

    if (!$consulta->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'La marca no existe.'
        ]);
        exit;
    }

    // Comprobar nombre duplicado en otra marca
    $consulta = $pdo->prepare(
        "SELECT id FROM marcas
         WHERE nombre = :nombre AND id != :id
         LIMIT 1"
    );

    $consulta->execute([
        'nombre' => $nombre,
        'id' => $id
    ]);

    if ($consulta->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe otra marca con ese nombre.'
        ]);
        exit;
    }

    // Generar slug
    $slugBase = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
    $slugBase = strtolower($slugBase);
    $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase);
    $slugBase = trim($slugBase, '-');

    if ($slugBase === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fue posible generar el identificador de la marca.'
        ]);
        exit;
    }

    $slug = substr($slugBase, 0, 140);
    $contador = 1;

    while (true) {
        $consulta = $pdo->prepare(
            "SELECT id FROM marcas
             WHERE slug = :slug AND id != :id
             LIMIT 1"
        );

        $consulta->execute([
            'slug' => $slug,
            'id' => $id
        ]);

        if (!$consulta->fetch()) {
            break;
        }

        $sufijo = '-' . $contador++;
        $slug = substr($slugBase, 0, 140 - strlen($sufijo)) . $sufijo;
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la marca.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
