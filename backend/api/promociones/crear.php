
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

    // Las promociones no admiten variantes
    if (
        isset($datos['variante_id']) &&
        $datos['variante_id'] !== null &&
        $datos['variante_id'] !== ''
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Las promociones solo pueden aplicarse a productos completos, no a variantes.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar tipos
    if (
        !isset($datos['producto_id']) ||
        !filter_var($datos['producto_id'], FILTER_VALIDATE_INT) ||
        (int)$datos['producto_id'] <= 0 ||
        !isset($datos['nombre']) ||
        !is_string($datos['nombre']) ||
        (isset($datos['precio_minorista_promocional']) &&
            $datos['precio_minorista_promocional'] !== null &&
            $datos['precio_minorista_promocional'] !== '' &&
            !is_numeric($datos['precio_minorista_promocional'])) ||
        (isset($datos['precio_mayorista_promocional']) &&
            $datos['precio_mayorista_promocional'] !== null &&
            $datos['precio_mayorista_promocional'] !== '' &&
            !is_numeric($datos['precio_mayorista_promocional'])) ||
        (isset($datos['inicia_en']) && !is_string($datos['inicia_en'])) ||
        (isset($datos['termina_en']) && !is_string($datos['termina_en']))
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Los campos enviados tienen un formato inválido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Recibir información
    $productoId = (int)$datos['producto_id'];
    $nombre = trim($datos['nombre']);

    $precioMinorista = $datos['precio_minorista_promocional'] ?? null;
    $precioMayorista = $datos['precio_mayorista_promocional'] ?? null;

    $iniciaEn = trim($datos['inicia_en'] ?? '');
    $terminaEn = trim($datos['termina_en'] ?? '');

    // Validar nombre
    if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 150) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nombre es obligatorio y admite máximo 150 caracteres.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Normalizar precios
    $precios = [
        'minorista' => $precioMinorista,
        'mayorista' => $precioMayorista
    ];

    foreach ($precios as $tipo => $precio) {
        if ($precio === null || $precio === '') {
            $precios[$tipo] = null;
            continue;
        }

        if (!is_numeric($precio)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "El precio promocional $tipo no es válido."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $valor = (float)$precio;

        if (!is_finite($valor) || $valor <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "El precio promocional $tipo debe ser mayor que cero."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $precios[$tipo] = round($valor, 2);
    }

    $precioMinorista = $precios['minorista'];
    $precioMayorista = $precios['mayorista'];

    if ($precioMinorista === null && $precioMayorista === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Debes ingresar al menos un precio promocional.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar fechas
    $formatoFecha = 'Y-m-d H:i:s';

    $inicio = null;
    $fin = null;

    if ($iniciaEn !== '') {
        $inicio = DateTime::createFromFormat(
            '!' . $formatoFecha,
            $iniciaEn
        );

        if (!$inicio || $inicio->format($formatoFecha) !== $iniciaEn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de inicio no tiene un formato válido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($terminaEn !== '') {
        $fin = DateTime::createFromFormat(
            '!' . $formatoFecha,
            $terminaEn
        );

        if (!$fin || $fin->format($formatoFecha) !== $terminaEn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de finalización no tiene un formato válido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($inicio && $fin && $fin <= $inicio) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La fecha de finalización debe ser posterior al inicio.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El producto seleccionado no existe.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$producto['activo'] !== 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No puedes crear promociones para productos inactivos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Los precios siempre corresponden al producto completo
    $precioNormalMinorista = $producto['precio_minorista'];
    $precioNormalMayorista = $producto['precio_mayorista'];

    // Validar precio minorista
    if ($precioMinorista !== null) {
        if (
            $precioNormalMinorista === null ||
            $precioMinorista >= (float)$precioNormalMinorista
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'El precio promocional minorista debe ser inferior al precio normal.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Validar precio mayorista
    if ($precioMayorista !== null) {
        if (
            $precioNormalMayorista === null ||
            $precioMayorista >= (float)$precioNormalMayorista
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'El precio promocional mayorista debe ser inferior al precio normal.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Registrar promoción exclusivamente para producto completo
    $sql = "INSERT INTO promociones (
                producto_id,
                variante_id,
                nombre,
                precio_minorista_promocional,
                precio_mayorista_promocional,
                inicia_en,
                termina_en,
                activo
            ) VALUES (
                :producto_id,
                NULL,
                :nombre,
                :precio_minorista,
                :precio_mayorista,
                :inicia_en,
                :termina_en,
                1
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'producto_id' => $productoId,
        'nombre' => $nombre,
        'precio_minorista' => $precioMinorista,
        'precio_mayorista' => $precioMayorista,
        'inicia_en' => $iniciaEn !== '' ? $iniciaEn : null,
        'termina_en' => $terminaEn !== '' ? $terminaEn : null
    ]);

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Promoción creada correctamente.',
        'promocion_id' => (int)$pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log('Error al registrar promoción: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la promoción.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('Error inesperado al registrar promoción: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado al registrar la promoción.'
    ], JSON_UNESCAPED_UNICODE);
}
