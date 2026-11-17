
<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

// 1. Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 2. Verificar sesión administrativa
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

// 4. Recibir información
$contenido = file_get_contents('php://input');
$datos = json_decode($contenido, true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 5. Obtener datos
$id = filter_var(
    $datos['id'] ?? null,
    FILTER_VALIDATE_INT
);

$nombre = trim($datos['nombre'] ?? '');

$categoriaId = filter_var(
    $datos['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);

$marcaId = isset($datos['marca_id']) && $datos['marca_id'] !== ''
    ? filter_var($datos['marca_id'], FILTER_VALIDATE_INT)
    : null;

$sku = trim($datos['sku'] ?? '');
$descripcion = trim($datos['descripcion'] ?? '');

$precioMinorista = $datos['precio_minorista'] ?? null;
$precioMayorista = $datos['precio_mayorista'] ?? null;

$disponibilidad = $datos['disponibilidad'] ?? 'disponible';
$activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;

// 6. Validar identificador
if (!$id || $id <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'El identificador del producto no es válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 7. Validar categoría y datos principales
if (
    $nombre === '' ||
    !$categoriaId ||
    !is_numeric($precioMinorista) ||
    (float)$precioMinorista <= 0
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Completa el nombre, categoría y precio minorista.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 8. Validar marca
if (
    $marcaId !== null &&
    ($marcaId === false || $marcaId <= 0)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'La marca seleccionada no es válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 9. Validar disponibilidad
if (!in_array($disponibilidad, ['disponible', 'agotado'], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Disponibilidad no válida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 10. Validar estado
if (!in_array($activo, [0, 1], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Estado del producto no válido.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 11. Validar precio mayorista
if ($precioMayorista !== null && $precioMayorista !== '') {
    if (!is_numeric($precioMayorista) || (float)$precioMayorista < 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Precio mayorista no válido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
} else {
    $precioMayorista = null;
}

// 12. Generar slug
function generarSlugActualizacion(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

    return trim($texto, '-');
}

// 13. Actualizar producto
try {
    $database = new Database();
    $conexion = $database->connect();

    // Comprobar que el producto exista
    $consultaProducto = $conexion->prepare(
        "SELECT id FROM productos WHERE id = ? LIMIT 1"
    );

    $consultaProducto->execute([$id]);

    if (!$consultaProducto->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'El producto que intentas editar no existe.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Comprobar categoría activa
    $consultaCategoria = $conexion->prepare(
        "SELECT id FROM categorias WHERE id = ? AND activo = 1"
    );

    $consultaCategoria->execute([$categoriaId]);

    if (!$consultaCategoria->fetch()) {
        throw new InvalidArgumentException(
            'La categoría seleccionada no está disponible.'
        );
    }

    // Comprobar marca activa
    if ($marcaId !== null) {
        $consultaMarca = $conexion->prepare(
            "SELECT id FROM marcas WHERE id = ? AND activo = 1"
        );

        $consultaMarca->execute([$marcaId]);

        if (!$consultaMarca->fetch()) {
            throw new InvalidArgumentException(
                'La marca seleccionada no está disponible.'
            );
        }
    }

    // Generar slug
    $slugBase = generarSlugActualizacion($nombre);

    if ($slugBase === '') {
        throw new InvalidArgumentException(
            'El nombre no permite generar un slug válido.'
        );
    }

    $slug = $slugBase;
    $contador = 1;

    // Evitar duplicados excluyendo el producto actual
    $consultaSlug = $conexion->prepare(
        "SELECT id
         FROM productos
         WHERE slug = ? AND id != ?
         LIMIT 1"
    );

    while (true) {
        $consultaSlug->execute([$slug, $id]);

        if (!$consultaSlug->fetch()) {
            break;
        }

        $slug = $slugBase . '-' . $contador;
        $contador++;
    }

    // Ejecutar actualización
    $consulta = $conexion->prepare(
        "UPDATE productos SET
            categoria_id = :categoria_id,
            marca_id = :marca_id,
            sku = :sku,
            nombre = :nombre,
            slug = :slug,
            descripcion = :descripcion,
            precio_minorista = :precio_minorista,
            precio_mayorista = :precio_mayorista,
            disponibilidad = :disponibilidad,
            activo = :activo
         WHERE id = :id"
    );

    $consulta->execute([
        'categoria_id' => $categoriaId,
        'marca_id' => $marcaId,
        'sku' => $sku !== '' ? $sku : null,
        'nombre' => $nombre,
        'slug' => $slug,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'precio_minorista' => $precioMinorista,
        'precio_mayorista' => $precioMayorista,
        'disponibilidad' => $disponibilidad,
        'activo' => $activo,
        'id' => $id
    ]);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Producto actualizado correctamente.',
        'producto_id' => $id,
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
        'message' => 'No fue posible actualizar el producto.'
    ], JSON_UNESCAPED_UNICODE);
}
