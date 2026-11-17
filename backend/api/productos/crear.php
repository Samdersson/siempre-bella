
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

// 2. Verificar sesión del administrador
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

// 4. Leer y validar JSON recibido
$contenido = file_get_contents('php://input');
$datos = json_decode($contenido, true);

if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Los datos enviados no tienen un formato válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Obtener datos del producto
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

// 6. Validaciones generales
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

if ($marcaId === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La marca seleccionada no es válida.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($disponibilidad, ['disponible', 'agotado'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Disponibilidad no válida.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($activo, [0, 1], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Estado del producto no válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 7. Validar precio mayorista
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

// 8. Generar slug del producto
function generarSlug(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

    return trim($texto, '-');
}

// 9. Registrar producto
try {
    $database = new Database();
    $conexion = $database->connect();

    // Verificar que la categoría exista y esté activa
    $consultaCategoria = $conexion->prepare(
        "SELECT id FROM categorias WHERE id = ? AND activo = 1"
    );

    $consultaCategoria->execute([$categoriaId]);

    if (!$consultaCategoria->fetch()) {
        throw new InvalidArgumentException(
            'La categoría seleccionada no está disponible.'
        );
    }

    // Verificar marca cuando se haya seleccionado
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
    $slugBase = generarSlug($nombre);

    if ($slugBase === '') {
        throw new InvalidArgumentException(
            'El nombre no permite generar un slug válido.'
        );
    }

    // Garantizar que el slug sea único
    $slug = $slugBase;
    $contador = 1;

    $consultaSlug = $conexion->prepare(
        "SELECT id FROM productos WHERE slug = ? LIMIT 1"
    );

    while (true) {
        $consultaSlug->execute([$slug]);

        if (!$consultaSlug->fetch()) {
            break;
        }

        $slug = $slugBase . '-' . $contador;
        $contador++;
    }

    // Insertar producto
    $consulta = $conexion->prepare(
        "INSERT INTO productos (
            categoria_id,
            marca_id,
            sku,
            nombre,
            slug,
            descripcion,
            precio_minorista,
            precio_mayorista,
            disponibilidad,
            activo
        ) VALUES (
            :categoria_id,
            :marca_id,
            :sku,
            :nombre,
            :slug,
            :descripcion,
            :precio_minorista,
            :precio_mayorista,
            :disponibilidad,
            :activo
        )"
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
        'activo' => $activo
    ]);

    // 10. Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Producto registrado correctamente.',
        'producto_id' => $conexion->lastInsertId(),
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
        'message' => 'No fue posible registrar el producto.'
    ], JSON_UNESCAPED_UNICODE);
}
