
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Validar sesión administrativa
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

// 3. Validar token CSRF
exigirTokenCSRF();

// 4. Recibir datos JSON
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Validar tipos de datos
if (
    !isset($datos['nombre']) ||
    !is_string($datos['nombre']) ||
    (isset($datos['descripcion']) && !is_string($datos['descripcion']))
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos de la categoría no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 6. Obtener información
$categoriaId = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$nombre = trim($datos['nombre']);
$descripcion = trim($datos['descripcion'] ?? '');

$activo = isset($datos['activo'])
    ? filter_var($datos['activo'], FILTER_VALIDATE_INT)
    : 1;

// 7. Validar identificador
if (
    $categoriaId === false ||
    $categoriaId === null ||
    $categoriaId < 1
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Identificador de categoría no válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 8. Validar nombre
if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio y admite máximo 100 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 9. Validar descripción
if (mb_strlen($descripcion, 'UTF-8') > 255) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La descripción admite máximo 255 caracteres.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 10. Validar estado
if (
    $activo === false ||
    $activo === null ||
    !in_array($activo, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El estado de la categoría no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 11. Generar slug
function generarSlugCategoriaActualizar(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

    if ($texto === false) {
        return '';
    }

    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

    return trim($texto, '-');
}

// 12. Actualizar categoría
try {

    $database = new Database();
    $conexion = $database->connect();

    // Comprobar que la categoría existe
    $consultaCategoria = $conexion->prepare(
        "SELECT id
         FROM categorias
         WHERE id = ?
         LIMIT 1"
    );

    $consultaCategoria->execute([$categoriaId]);

    if (!$consultaCategoria->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'La categoría no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $slugBase = generarSlugCategoriaActualizar($nombre);

    if ($slugBase === '') {
        throw new InvalidArgumentException(
            'No fue posible generar un slug válido.'
        );
    }

    // Evitar slug duplicado en otras categorías
    $slug = $slugBase;
    $contador = 1;

    $consultaSlug = $conexion->prepare(
        "SELECT id
         FROM categorias
         WHERE slug = ? AND id != ?
         LIMIT 1"
    );

    while (true) {

        $consultaSlug->execute([
            $slug,
            $categoriaId
        ]);

        if (!$consultaSlug->fetch()) {
            break;
        }

        $slug = $slugBase . '-' . $contador;
        $contador++;

        if ($contador > 10000) {
            throw new RuntimeException(
                'No fue posible generar un slug único.'
            );
        }
    }

    // Actualizar categoría sin modificar su imagen
    $consulta = $conexion->prepare(
        "UPDATE categorias SET
            nombre = ?,
            slug = ?,
            descripcion = ?,
            activo = ?
         WHERE id = ?"
    );

    $consulta->execute([
        $nombre,
        $slug,
        $descripcion !== '' ? $descripcion : null,
        $activo,
        $categoriaId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Categoría actualizada correctamente.',
        'categoria_id' => $categoriaId,
        'slug' => $slug
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log(
        'Error al actualizar categoría: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible actualizar la categoría.'
    ], JSON_UNESCAPED_UNICODE);
}
