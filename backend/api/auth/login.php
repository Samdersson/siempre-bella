
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);

$email = trim($datos['email'] ?? '');
$password = $datos['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ingresa un correo y una contraseña válidos.'
    ]);
    exit;
}

try {
    $database = new Database();
    $conexion = $database->connect();

    $consulta = $conexion->prepare(
        "SELECT id, nombre, email, password_hash, activo
         FROM usuarios
         WHERE email = :email
         LIMIT 1"
    );

    $consulta->execute(['email' => $email]);

    $usuario = $consulta->fetch();

    if (
        !$usuario ||
        (int) $usuario['activo'] !== 1 ||
        !password_verify($password, $usuario['password_hash'])
    ) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Correo o contraseña incorrectos.'
        ]);
        exit;
    }

    session_regenerate_id(true);

    $actualizar = $conexion->prepare(
        "UPDATE usuarios
         SET ultimo_acceso = NOW()
         WHERE id = :id"
    );

    $actualizar->execute(['id' => $usuario['id']]);

    $_SESSION['admin'] = [
        'id' => (int) $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email']
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso.',
        'usuario' => $_SESSION['admin']
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible iniciar sesión.'
    ]);
}
