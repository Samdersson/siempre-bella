<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function responderUsuarios(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['admin']['id'])) {
    responderUsuarios(401, ['success' => false, 'message' => 'Inicia sesión para continuar.']);
}

try {
    $db = (new Database())->connect();
    $actualId = (int) $_SESSION['admin']['id'];

    // Revalidar que la cuenta actual siga activa.
    $validar = $db->prepare('SELECT id FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1');
    $validar->execute(['id' => $actualId]);
    if (!$validar->fetchColumn()) {
        $_SESSION = [];
        session_destroy();
        responderUsuarios(401, ['success' => false, 'message' => 'Tu cuenta ya no tiene acceso al panel.']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $usuarios = $db->query(
            'SELECT id, nombre, email, activo, ultimo_acceso, creado_en FROM usuarios ORDER BY nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        responderUsuarios(200, ['success' => true, 'data' => $usuarios]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        responderUsuarios(405, ['success' => false, 'message' => 'Método no permitido.']);
    }

    exigirTokenCSRF();
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        responderUsuarios(400, ['success' => false, 'message' => 'Los datos enviados no son válidos.']);
    }

    $accion = $datos['accion'] ?? '';
    if ($accion === 'crear' || $accion === 'actualizar') {
        $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = trim(is_string($datos['nombre'] ?? null) ? $datos['nombre'] : '');
        $email = strtolower(trim(is_string($datos['email'] ?? null) ? $datos['email'] : ''));
        $password = $datos['password'] ?? '';
        $activo = isset($datos['activo']) ? (int) ((bool) $datos['activo']) : 1;

        if ($nombre === '' || mb_strlen($nombre) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            responderUsuarios(422, ['success' => false, 'message' => 'Ingresa un nombre y un correo válidos.']);
        }
        if (!is_string($password) || ($accion === 'crear' && strlen($password) < 8) || strlen($password) > 1024) {
            responderUsuarios(422, ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
        }
        if ($accion === 'actualizar' && (!$id || $id < 1)) {
            responderUsuarios(422, ['success' => false, 'message' => 'El usuario indicado no es válido.']);
        }
        if ($accion === 'actualizar' && $id === $actualId && !$activo) {
            responderUsuarios(409, ['success' => false, 'message' => 'No puedes desactivar tu propia cuenta.']);
        }

        if ($accion === 'actualizar') {
            $duplicado = $db->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
            $duplicado->execute(['email' => $email, 'id' => $id]);
        } else {
            $duplicado = $db->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $duplicado->execute(['email' => $email]);
        }
        if ($duplicado->fetchColumn()) {
            responderUsuarios(409, ['success' => false, 'message' => 'Ya existe un usuario con ese correo.']);
        }

        if ($accion === 'crear') {
            $stmt = $db->prepare('INSERT INTO usuarios (nombre, email, password_hash, activo) VALUES (:nombre, :email, :hash, 1)');
            $stmt->execute(['nombre' => $nombre, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        } else {
            $buscar = $db->prepare('SELECT id FROM usuarios WHERE id = :id');
            $buscar->execute(['id' => $id]);
            if (!$buscar->fetchColumn()) responderUsuarios(404, ['success' => false, 'message' => 'No se encontró el usuario.']);
            if ($password !== '') {
                $stmt = $db->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, activo = :activo, password_hash = :hash WHERE id = :id');
                $stmt->execute(['nombre' => $nombre, 'email' => $email, 'activo' => $activo, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
            } else {
                $stmt = $db->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, activo = :activo WHERE id = :id');
                $stmt->execute(['nombre' => $nombre, 'email' => $email, 'activo' => $activo, 'id' => $id]);
            }
            if ($id === $actualId) {
                $_SESSION['admin']['nombre'] = $nombre;
                $_SESSION['admin']['email'] = $email;
            }
        }
        responderUsuarios(200, ['success' => true, 'message' => $accion === 'crear' ? 'Usuario creado.' : 'Usuario actualizado.']);
    }

    if ($accion === 'eliminar') {
        $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) responderUsuarios(422, ['success' => false, 'message' => 'El usuario indicado no es válido.']);
        if ($id === $actualId) responderUsuarios(409, ['success' => false, 'message' => 'No puedes eliminar tu propia cuenta.']);
        $stmt = $db->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if (!$stmt->rowCount()) responderUsuarios(404, ['success' => false, 'message' => 'No se encontró el usuario.']);
        responderUsuarios(200, ['success' => true, 'message' => 'Usuario eliminado.']);
    }

    responderUsuarios(422, ['success' => false, 'message' => 'La acción solicitada no es válida.']);
} catch (Throwable $e) {
    error_log('Error al gestionar usuarios: ' . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        responderUsuarios(409, ['success' => false, 'message' => 'Ya existe un usuario con ese correo.']);
    }
    responderUsuarios(500, ['success' => false, 'message' => 'No fue posible gestionar los usuarios.']);
}
