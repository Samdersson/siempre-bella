
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

// Validar token CSRF
exigirTokenCSRF();

try {

    $database = new Database();
    $pdo = $database->connect();

    // Leer datos JSON
    $contenido = file_get_contents('php://input');
    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        responder(400, [
            'success' => false,
            'message' => 'Los datos enviados no son válidos.'
        ]);
    }

    // Validar identificador de promoción
    $id = filter_var(
        $datos['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id <= 0) {
        responder(400, [
            'success' => false,
            'message' => 'El identificador de la promoción no es válido.'
        ]);
    }

    // Comprobar que la promoción existe
    $consulta = $pdo->prepare(
        "SELECT id
         FROM promociones
         WHERE id = :id
         LIMIT 1"
    );

    $consulta->execute(['id' => $id]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        responder(404, [
            'success' => false,
            'message' => 'La promoción no existe.'
        ]);
    }

    // Validar producto
    $productoId = filter_var(
        $datos['producto_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$productoId || $productoId <= 0) {
        responder(400, [
            'success' => false,
            'message' => 'Selecciona un producto válido.'
        ]);
    }

    // Las promociones se aplican únicamente a productos completos.
    // No se permiten variantes.
    $varianteId = null;

    // Validar nombre
    $nombreRecibido = $datos['nombre'] ?? null;

    if (!is_string($nombreRecibido)) {
        responder(400, [
            'success' => false,
            'message' => 'El nombre de la promoción no es válido.'
        ]);
    }

    $nombre = trim($nombreRecibido);

    if ($nombre === '' || mb_strlen($nombre) > 150) {
        responder(400, [
            'success' => false,
            'message' => 'El nombre es obligatorio y admite máximo 150 caracteres.'
        ]);
    }

    // Validar precios promocionales
    $precioMinorista = $datos['precio_minorista_promocional'] ?? null;
    $precioMayorista = $datos['precio_mayorista_promocional'] ?? null;

    $precios = [
        'minorista' => $precioMinorista,
        'mayorista' => $precioMayorista
    ];

    foreach ($precios as $tipo => $precio) {

        if ($precio === null || $precio === '') {
            $precios[$tipo] = null;
            continue;
        }

        if (
            !is_numeric($precio) ||
            !is_finite((float)$precio) ||
            (float)$precio <= 0
        ) {
            responder(400, [
                'success' => false,
                'message' => "El precio promocional $tipo no es válido."
            ]);
        }

        $precios[$tipo] = round((float)$precio, 2);
    }

    $precioMinorista = $precios['minorista'];
    $precioMayorista = $precios['mayorista'];

    if (
        $precioMinorista === null &&
        $precioMayorista === null
    ) {
        responder(400, [
            'success' => false,
            'message' => 'Debes ingresar al menos un precio promocional.'
        ]);
    }

    // Validar fechas
    $formatoFecha = 'Y-m-d H:i:s';

    $iniciaEn = $datos['inicia_en'] ?? null;
    $terminaEn = $datos['termina_en'] ?? null;

    if (
        ($iniciaEn !== null && !is_string($iniciaEn)) ||
        ($terminaEn !== null && !is_string($terminaEn))
    ) {
        responder(400, [
            'success' => false,
            'message' => 'Las fechas enviadas no son válidas.'
        ]);
    }

    $iniciaEn = trim($iniciaEn ?? '');
    $terminaEn = trim($terminaEn ?? '');

    $inicio = null;
    $fin = null;

    if ($iniciaEn !== '') {

        $inicio = DateTime::createFromFormat(
            $formatoFecha,
            $iniciaEn
        );

        if (
            !$inicio ||
            $inicio->format($formatoFecha) !== $iniciaEn
        ) {
            responder(400, [
                'success' => false,
                'message' => 'La fecha de inicio no tiene un formato válido.'
            ]);
        }
    }

    if ($terminaEn !== '') {

        $fin = DateTime::createFromFormat(
            $formatoFecha,
            $terminaEn
        );

        if (
            !$fin ||
            $fin->format($formatoFecha) !== $terminaEn
        ) {
            responder(400, [
                'success' => false,
                'message' => 'La fecha de finalización no tiene un formato válido.'
            ]);
        }
    }

    if ($inicio && $fin && $fin <= $inicio) {
        responder(400, [
            'success' => false,
            'message' => 'La fecha de finalización debe ser posterior al inicio.'
        ]);
    }

    // Consultar producto completo
    $consulta = $pdo->prepare(
        "SELECT id, precio_minorista, precio_mayorista, activo
         FROM productos
         WHERE id = :id
         LIMIT 1"
    );

    $consulta->execute(['id' => $productoId]);

    $producto = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        responder(404, [
            'success' => false,
            'message' => 'El producto no existe.'
        ]);
    }

    if ((int)$producto['activo'] !== 1) {
        responder(400, [
            'success' => false,
            'message' => 'No puedes promocionar productos inactivos.'
        ]);
    }

    // Obtener precios normales del producto.
    // Nunca consultar precios de variantes.
    $precioNormalMinorista = $producto['precio_minorista'];
    $precioNormalMayorista = $producto['precio_mayorista'];

    // Validar precio promocional minorista
    if ($precioMinorista !== null) {

        if (
            $precioNormalMinorista === null ||
            $precioMinorista >= (float)$precioNormalMinorista
        ) {
            responder(400, [
                'success' => false,
                'message' => 'El precio promocional minorista debe ser inferior al precio normal.'
            ]);
        }
    }

    // Validar precio promocional mayorista
    if ($precioMayorista !== null) {

        if (
            $precioNormalMayorista === null ||
            $precioMayorista >= (float)$precioNormalMayorista
        ) {
            responder(400, [
                'success' => false,
                'message' => 'El precio promocional mayorista debe ser inferior al precio normal.'
            ]);
        }
    }

    // Actualizar promoción
    $sql = "UPDATE promociones SET
                producto_id = :producto_id,
                variante_id = NULL,
                nombre = :nombre,
                precio_minorista_promocional = :precio_minorista,
                precio_mayorista_promocional = :precio_mayorista,
                inicia_en = :inicia_en,
                termina_en = :termina_en
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'producto_id' => $productoId,
        'nombre' => $nombre,
        'precio_minorista' => $precioMinorista,
        'precio_mayorista' => $precioMayorista,
        'inicia_en' => $iniciaEn !== '' ? $iniciaEn : null,
        'termina_en' => $terminaEn !== '' ? $terminaEn : null,
        'id' => $id
    ]);

    responder(200, [
        'success' => true,
        'message' => 'Promoción actualizada correctamente.',
        'promocion_id' => $id
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al actualizar promoción: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Error al actualizar la promoción.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error inesperado al actualizar promoción: ' . $e->getMessage()
    );

    responder(500, [
        'success' => false,
        'message' => 'Ocurrió un error inesperado.'
    ]);
}
