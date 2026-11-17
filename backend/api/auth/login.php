
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Configuración del límite de intentos.
const MAX_INTENTOS = 5;
const TIEMPO_BLOQUEO = 15;

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Solo permitir POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

// Validar JSON.
$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(400, [
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ]);
}

$email = $datos['email'] ?? '';
$password = $datos['password'] ?? '';

if (
    !is_string($email) ||
    !is_string($password)
) {
    responder(400, [
        'success' => false,
        'message' => 'Los datos enviados no son válidos.'
    ]);
}

$email = strtolower(trim($email));

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 190 ||
    $password === '' ||
    strlen($password) > 1024
) {
    responder(400, [
        'success' => false,
        'message' => 'Ingresa un correo y una contraseña válidos.'
    ]);
}

// Identificar el origen mediante la IP de la conexión.
// No confiar en X-Forwarded-For enviado por el cliente.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$identificador = hash('sha256', $ip);

$database = null;
$conexion = null;

try {
    $database = new Database();
    $conexion = $database->connect();

    // Iniciar transacción para controlar intentos simultáneos.
    $conexion->beginTransaction();

    // Crear el registro si todavía no existe.
    $insertar = $conexion->prepare(
        "INSERT IGNORE INTO login_intentos
            (email, identificador, intentos)
         VALUES
            (:email, :identificador, 0)"
    );

    $insertar->execute([
        'email' => $email,
        'identificador' => $identificador
    ]);

    // Bloquear el registro mientras procesamos el intento.
    $consultaIntentos = $conexion->prepare(
        "SELECT id, intentos, bloqueado_hasta
         FROM login_intentos
         WHERE email = :email
           AND identificador = :identificador
         LIMIT 1
         FOR UPDATE"
    );

    $consultaIntentos->execute([
        'email' => $email,
        'identificador' => $identificador
    ]);

    $registro = $consultaIntentos->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        throw new RuntimeException(
            'No fue posible consultar el registro de intentos.'
        );
    }

    $idIntento = (int) $registro['id'];

    // Comprobar si existe un bloqueo vigente.
    if ($registro['bloqueado_hasta'] !== null) {

        $consultaBloqueo = $conexion->prepare(
            "SELECT TIMESTAMPDIFF(
                SECOND,
                NOW(),
                bloqueado_hasta
            ) AS segundos
            FROM login_intentos
            WHERE id = :id"
        );

        $consultaBloqueo->execute(['id' => $idIntento]);

        $segundos = (int) $consultaBloqueo->fetchColumn();

        if ($segundos > 0) {
            $conexion->commit();

            header('Retry-After: ' . $segundos);

            responder(429, [
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente después del bloqueo.',
                'retry_after' => $segundos
            ]);
        }

        // El bloqueo venció. Reiniciar el contador.
        $reiniciar = $conexion->prepare(
            "UPDATE login_intentos
             SET intentos = 0,
                 bloqueado_hasta = NULL,
                 ultimo_intento = NOW()
             WHERE id = :id"
        );

        $reiniciar->execute(['id' => $idIntento]);
    }

    // Buscar el administrador.
    $consultaUsuario = $conexion->prepare(
        "SELECT id, nombre, email, password_hash, activo
         FROM usuarios
         WHERE email = :email
         LIMIT 1"
    );

    $consultaUsuario->execute(['email' => $email]);

    $usuario = $consultaUsuario->fetch(PDO::FETCH_ASSOC);

    // Verificar credenciales.
    if (
        !$usuario ||
        (int) $usuario['activo'] !== 1 ||
        !password_verify($password, $usuario['password_hash'])
    ) {

        // Registrar intento fallido y bloquear al alcanzar el límite.
        $actualizarIntentos = $conexion->prepare(
            "UPDATE login_intentos
             SET intentos = intentos + 1,
                 ultimo_intento = NOW(),
                 bloqueado_hasta = CASE
                    WHEN intentos + 1 >= :max_intentos
                    THEN DATE_ADD(
                        NOW(),
                        INTERVAL 15 MINUTE
                    )
                    ELSE NULL
                 END
             WHERE id = :id"
        );

        $actualizarIntentos->execute([
            'max_intentos' => MAX_INTENTOS,
            'id' => $idIntento
        ]);

        // Consultar el resultado del intento.
        $consultarEstado = $conexion->prepare(
            "SELECT intentos, bloqueado_hasta
             FROM login_intentos
             WHERE id = :id"
        );

        $consultarEstado->execute(['id' => $idIntento]);

        $estado = $consultarEstado->fetch(PDO::FETCH_ASSOC);

        $conexion->commit();

        if ($estado['bloqueado_hasta'] !== null) {
            header('Retry-After: 900');

            responder(429, [
                'success' => false,
                'message' => 'Has alcanzado el límite de intentos. Intenta nuevamente en 15 minutos.',
                'retry_after' => 900
            ]);
        }

        responder(401, [
            'success' => false,
            'message' => 'Correo o contraseña incorrectos.',
            'intentos_restantes' => MAX_INTENTOS - (int) $estado['intentos']
        ]);
    }

    // Regenerar el identificador de sesión.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException(
            'No fue posible renovar la sesión.'
        );
    }

    // Actualizar último acceso.
    $actualizarAcceso = $conexion->prepare(
        "UPDATE usuarios
         SET ultimo_acceso = NOW()
         WHERE id = :id"
    );

    $actualizarAcceso->execute([
        'id' => $usuario['id']
    ]);

    // Eliminar los intentos fallidos tras el acceso correcto.
    $eliminarIntentos = $conexion->prepare(
        "DELETE FROM login_intentos
         WHERE id = :id"
    );

    $eliminarIntentos->execute([
        'id' => $idIntento
    ]);

    $conexion->commit();

    // Crear sesión administrativa.
    $_SESSION['admin'] = [
        'id' => (int) $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email']
    ];

    responder(200, [
        'success' => true,
        'message' => 'Inicio de sesión exitoso.',
        'usuario' => $_SESSION['admin']
    ]);

} catch (Throwable $e) {

    if ($conexion instanceof PDO && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('Error en login: ' . $e->getMessage());

    responder(500, [
        'success' => false,
        'message' => 'No fue posible iniciar sesión.'
    ]);
}
