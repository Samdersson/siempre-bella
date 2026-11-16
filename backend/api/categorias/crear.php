
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar sesión administrativa
if (!isset($_SESSION['admin'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'No autorizado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Recibir datos
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$nombre = trim($datos['nombre'] ?? '');
$descripcion = trim($datos['descripcion'] ?? '');

$activo = isset($datos['activo'])
    ? filter_var($datos['activo'], FILTER_VALIDATE_INT)
    : 1;

// Validar nombre
if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio y admite máximo 100 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar descripción
if (mb_strlen($descripcion, 'UTF-8') > 255) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La descripción admite máximo 255 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validar estado
if (!in_array($activo, [0, 1], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El estado de la categoría no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Generar slug
function generarSlugCategoria(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

    return trim($texto, '-');
}

try {
    $database = new Database();
    $conexion = $database->connect();

    $slugBase = generarSlugCategoria($nombre);

    if ($slugBase === '') {
        throw new InvalidArgumentException(
            'No fue posible generar un slug válido.'
        );
    }

    // Evitar slugs duplicados
    $slug = $slugBase;
    $contador = 1;

    $consultaSlug = $conexion->prepare(
        "SELECT id FROM categorias WHERE slug = ? LIMIT 1"
    );

    while (true) {
        $consultaSlug->execute([$slug]);

        if (!$consultaSlug->fetch()) {
            break;
        }

        $slug = $slugBase . '-' . $contador;
        $contador++;
    }

    // Registrar categoría
    $consulta = $conexion->prepare(
        "INSERT INTO categorias (
            nombre,
            slug,
            descripcion,
            activo
        ) VALUES (?, ?, ?, ?)"
    );

    $consulta->execute([
        $nombre,
        $slug,
        $descripcion !== '' ? $descripcion : null,
        $activo
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Categoría registrada correctamente.',
        'categoria_id' => $conexion->lastInsertId(),
        'slug' => $slug
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible registrar la categoría.'
    ], JSON_UNESCAPED_UNICODE);
}
