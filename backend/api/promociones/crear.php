
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

    // Recibir información
    $productoId = filter_var(
        $datos['producto_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $varianteId = (
        isset($datos['variante_id']) &&
        $datos['variante_id'] !== ''
    )
        ? filter_var($datos['variante_id'], FILTER_VALIDATE_INT)
        : null;

    $nombre = trim($datos['nombre'] ?? '');

    $precioMinorista = $datos['precio_minorista_promocional'] ?? null;
    $precioMayorista = $datos['precio_mayorista_promocional'] ?? null;

    $iniciaEn = trim($datos['inicia_en'] ?? '');
    $terminaEn = trim($datos['termina_en'] ?? '');

    // Validar identificadores
    if (!$productoId || $productoId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Selecciona un producto válido.'
        ]);
        exit;
    }

    if ($varianteId !== null && (!$varianteId || $varianteId <= 0)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La variante seleccionada no es válida.'
        ]);
        exit;
    }

    // Validar nombre
    if ($nombre === '' || mb_strlen($nombre) > 150) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nombre es obligatorio y admite máximo 150 caracteres.'
        ]);
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

        if (!is_numeric($precio) || (float)$precio < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "El precio promocional $tipo no es válido."
            ]);
            exit;
        }

        $precios[$tipo] = round((float)$precio, 2);
    }

    $precioMinorista = $precios['minorista'];
    $precioMayorista = $precios['mayorista'];

    if ($precioMinorista === null && $precioMayorista === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Debes ingresar al menos un precio promocional.'
        ]);
        exit;
    }

    // Validar fechas
    $formatoFecha = 'Y-m-d H:i:s';

    $inicio = null;
    $fin = null;

    if ($iniciaEn !== '') {
        $inicio = DateTime::createFromFormat($formatoFecha, $iniciaEn);

        if (!$inicio || $inicio->format($formatoFecha) !== $iniciaEn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de inicio no tiene un formato válido.'
            ]);
            exit;
        }
    }

    if ($terminaEn !== '') {
        $fin = DateTime::createFromFormat($formatoFecha, $terminaEn);

        if (!$fin || $fin->format($formatoFecha) !== $terminaEn) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de finalización no tiene un formato válido.'
            ]);
            exit;
        }
    }

    if ($inicio && $fin && $fin <= $inicio) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La fecha de finalización debe ser posterior al inicio.'
        ]);
        exit;
    }

    // Consultar producto
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
        ]);
        exit;
    }

    if ((int)$producto['activo'] !== 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No puedes crear promociones para productos inactivos.'
        ]);
        exit;
    }

    // Si hay variante, validar que pertenezca al producto
    if ($varianteId !== null) {
        $consulta = $pdo->prepare(
            "SELECT id, producto_id, precio_minorista,
                    precio_mayorista, activo
             FROM variantes
             WHERE id = :id AND producto_id = :producto_id
             LIMIT 1"
        );

        $consulta->execute([
            'id' => $varianteId,
            'producto_id' => $productoId
        ]);

        $variante = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$variante) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'La variante no existe o no pertenece al producto.'
            ]);
            exit;
        }

        if ((int)$variante['activo'] !== 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No puedes promocionar una variante inactiva.'
            ]);
            exit;
        }

        $precioNormalMinorista = $variante['precio_minorista'];
        $precioNormalMayorista = $variante['precio_mayorista'];

    } else {
        $precioNormalMinorista = $producto['precio_minorista'];
        $precioNormalMayorista = $producto['precio_mayorista'];
    }

    // Validar que los precios promocionales sean inferiores
    if ($precioMinorista !== null) {
        if (
            $precioNormalMinorista === null ||
            $precioMinorista >= (float)$precioNormalMinorista
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'El precio promocional minorista debe ser inferior al precio normal.'
            ]);
            exit;
        }
    }

    if ($precioMayorista !== null) {
        if (
            $precioNormalMayorista === null ||
            $precioMayorista >= (float)$precioNormalMayorista
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'El precio promocional mayorista debe ser inferior al precio normal.'
            ]);
            exit;
        }
    }

    // Registrar promoción
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
                :variante_id,
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
        'variante_id' => $varianteId,
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
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la promoción.',
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
