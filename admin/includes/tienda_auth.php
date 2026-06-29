<?php

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../../includes/telefono_helpers.php';
require_once __DIR__ . '/joyeria_session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cliente_correo.php';
require_once __DIR__ . '/../models/cliente.php';

joyeria_session_start();

const JOYERIA_TIENDA_SESSION_KEY = 'joyeria_tienda_auth';

class TiendaAuthService extends Sistema
{
    private const SELECT_CLIENTE_ACTIVO = "SELECT u.id_usuario,
                       u.nombre,
                       u.primer_apellido,
                       u.segundo_apellido,
                       u.correo,
                       u.telefono,
                       u.contrasena,
                       u.activo,
                       u.correo_verificado_en,
                       c.id_cliente,
                       c.activo AS cliente_activo
                FROM usuarios u
                INNER JOIN clientes c ON c.id_usuario_FK = u.id_usuario AND c.activo = 1
                WHERE u.activo = 1";

    public function findClienteActivoPorCorreo(string $correo): ?array
    {
        $correo = joyeria_cliente_correo_normalizado($correo);
        if ($correo === '') {
            return null;
        }

        $sql = self::SELECT_CLIENTE_ACTIVO . ' AND LOWER(TRIM(u.correo)) = :correo LIMIT 1';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findClienteActivoPorTelefono(string $telefono): ?array
    {
        $norm = joyeria_telefono_normalizado($telefono);
        if ($norm === '') {
            return null;
        }

        $sql = self::SELECT_CLIENTE_ACTIVO . " AND u.telefono IS NOT NULL AND TRIM(u.telefono) != ''";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matches = [];
        foreach ($rows as $row) {
            $existente = joyeria_telefono_normalizado((string) ($row['telefono'] ?? ''));
            if ($existente !== '' && $existente === $norm) {
                $matches[] = $row;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) > 1) {
            error_log('tienda_auth telefono duplicado legacy: ' . $norm . ' matches=' . count($matches));
            return null;
        }

        return $matches[0];
    }

    public function findClienteActivoPorIdentificador(string $identificador): ?array
    {
        $identificador = trim($identificador);
        if ($identificador === '') {
            return null;
        }

        if (joyeria_identificador_es_correo($identificador)) {
            return $this->findClienteActivoPorCorreo($identificador);
        }

        return $this->findClienteActivoPorTelefono($identificador);
    }
}

function tienda_auth_service(): TiendaAuthService
{
    static $service = null;
    if ($service === null) {
        $service = new TiendaAuthService();
    }
    return $service;
}

function tienda_auth_user(): ?array
{
    if (!isset($_SESSION[JOYERIA_TIENDA_SESSION_KEY]) || !is_array($_SESSION[JOYERIA_TIENDA_SESSION_KEY])) {
        return null;
    }

    return $_SESSION[JOYERIA_TIENDA_SESSION_KEY];
}

function tienda_is_logged_in(): bool
{
    return tienda_auth_user() !== null;
}

function tienda_usuario_correo_verificado(array $usuario): bool
{
    $val = $usuario['correo_verificado_en'] ?? null;
    return $val !== null && trim((string) $val) !== '';
}

function tienda_login(string $identificador, string $contrasena, ?string &$error = null, ?string &$failureCode = null): bool
{
    $identificador = trim($identificador);
    if ($identificador === '' || $contrasena === '') {
        $error = 'Correo o telefono y contrasena son obligatorios.';
        return false;
    }

    try {
        $svc = tienda_auth_service();
        $usuario = $svc->findClienteActivoPorIdentificador($identificador);

        if ($usuario === null) {
            if (joyeria_identificador_es_correo($identificador)) {
                $adminUser = auth_service()->findUserByEmail(joyeria_cliente_correo_normalizado($identificador));
                if ($adminUser !== null) {
                    $error = 'Ese correo es de usuario del sistema pero no de cliente de la tienda en linea.';
                    return false;
                }
            }
            $error = 'No hay cliente activo con ese correo o telefono.';
            return false;
        }

        $passCheck = auth_verify_password_for_login($contrasena, (string) $usuario['contrasena']);
        if (!$passCheck['ok']) {
            $error = $passCheck['message'];
            return false;
        }

        if (!tienda_usuario_correo_verificado($usuario)) {
            $failureCode = 'EMAIL_NOT_VERIFIED';
            $error = 'Debes confirmar tu correo antes de iniciar sesion. Revisa tu bandeja de entrada o solicita un nuevo enlace.';
            return false;
        }

        $_SESSION[JOYERIA_TIENDA_SESSION_KEY] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'id_cliente' => (int) $usuario['id_cliente'],
            'nombre' => trim((string) $usuario['nombre']),
            'nombre_completo' => trim(
                (string) $usuario['nombre'] . ' ' . $usuario['primer_apellido'] . ' ' . ($usuario['segundo_apellido'] ?? '')
            ),
            'correo' => (string) $usuario['correo'],
        ];

        session_regenerate_id(true);
        return true;
    } catch (Throwable $e) {
        $error = 'No se pudo iniciar sesion. Intenta nuevamente.';
        return false;
    }
}

/**
 * Registro de cliente sin direccion (tienda).
 *
 * @return int id_cliente o 0 si falla
 */
function tienda_register(array $data, ?string &$error = null): int
{
    try {
        $correo = tienda_validar_correo_registro((string) ($data['correo'] ?? ''));
        $telefono = tienda_validar_telefono_registro((string) ($data['telefono'] ?? ''));
        $contrasena = tienda_validar_contrasena_registro(
            (string) ($data['contrasena'] ?? ''),
            (string) ($data['contrasena_confirm'] ?? ($data['contrasena'] ?? ''))
        );

        $clienteModel = new Cliente();
        $payload = [
            'nombre' => $data['nombre'] ?? '',
            'primer_apellido' => $data['primer_apellido'] ?? '',
            'segundo_apellido' => isset($data['segundo_apellido']) ? trim((string) $data['segundo_apellido']) : null,
            'correo' => $correo,
            'telefono' => $telefono,
            'contrasena' => $contrasena,
            'correo_verificado' => false,
            'telefono_ya_validado' => true,
        ];

        return (int) $clienteModel->crear($payload);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        return 0;
    } catch (Exception $e) {
        $error = $e->getMessage();
        return 0;
    } catch (Throwable $e) {
        $error = 'No se pudo completar el registro.';
        return 0;
    }
}

function tienda_logout(): void
{
    unset($_SESSION[JOYERIA_TIENDA_SESSION_KEY]);
    session_regenerate_id(true);
}

/**
 * Normaliza y valida correo para alta de cliente en tienda.
 *
 * @throws InvalidArgumentException
 */
function tienda_validar_correo_registro(string $correo): string
{
    $norm = joyeria_cliente_correo_normalizado($correo);
    if ($norm === '') {
        throw new InvalidArgumentException('El correo es obligatorio.');
    }
    if (!joyeria_cliente_correo_es_entregable($norm)) {
        throw new InvalidArgumentException('Introduce un correo electronico valido.');
    }
    if (!filter_var($norm, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('El correo electronico no es valido.');
    }
    if (mb_strlen($norm) > 80) {
        throw new InvalidArgumentException('El correo no puede superar 80 caracteres.');
    }

    $stmt = tienda_auth_service()->getDb()->prepare(
        'SELECT 1 FROM usuarios WHERE LOWER(TRIM(correo)) = :correo LIMIT 1'
    );
    $stmt->bindValue(':correo', $norm, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('El correo ya esta registrado. Inicia sesion o usa otro correo.');
    }

    return $norm;
}

/**
 * @throws InvalidArgumentException
 */
function tienda_validar_telefono_registro(string $telefono): string
{
    $telefono = trim($telefono);
    if ($telefono === '') {
        throw new InvalidArgumentException('El telefono es obligatorio.');
    }

    $norm = joyeria_telefono_normalizado($telefono);
    if ($norm === '') {
        throw new InvalidArgumentException('Introduce un telefono valido.');
    }

    $nacionales = joyeria_telefono_digitos_nacionales($norm);
    if (strlen($nacionales) < 10) {
        throw new InvalidArgumentException('El telefono debe tener al menos 10 digitos.');
    }

    if (joyeria_existe_telefono_usuario(tienda_auth_service()->getDb(), $norm)) {
        throw new InvalidArgumentException('El telefono ya esta registrado. Inicia sesion o usa otro numero.');
    }

    return mb_substr($norm, 0, 15);
}

/**
 * @throws InvalidArgumentException
 */
function tienda_validar_contrasena_registro(string $contrasena, string $confirmacion): string
{
    $contrasena = trim($contrasena);
    $confirmacion = trim($confirmacion);
    if ($contrasena === '' || $confirmacion === '') {
        throw new InvalidArgumentException('La contrasena y su confirmacion son obligatorias.');
    }
    if ($contrasena !== $confirmacion) {
        throw new InvalidArgumentException('Las contrasenas no coinciden.');
    }
    if (mb_strlen($contrasena) < 8) {
        throw new InvalidArgumentException('La contrasena debe tener al menos 8 caracteres.');
    }

    return $contrasena;
}

function tienda_id_usuario_por_cliente(int $idCliente): int
{
    if ($idCliente <= 0) {
        return 0;
    }
    $stmt = tienda_auth_service()->getDb()->prepare(
        'SELECT id_usuario_FK FROM clientes WHERE id_cliente = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $idCliente, PDO::PARAM_INT);
    $stmt->execute();
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : 0;
}

const JOYERIA_TIENDA_VERIFICACION_PENDIENTE_KEY = 'tienda_verificacion_pendiente_correo';

function tienda_set_verificacion_pendiente(string $correo): void
{
    $correo = joyeria_cliente_correo_normalizado($correo);
    if ($correo === '') {
        return;
    }
    $_SESSION[JOYERIA_TIENDA_VERIFICACION_PENDIENTE_KEY] = $correo;
}

function tienda_get_verificacion_pendiente(): ?string
{
    $correo = isset($_SESSION[JOYERIA_TIENDA_VERIFICACION_PENDIENTE_KEY])
        ? joyeria_cliente_correo_normalizado((string) $_SESSION[JOYERIA_TIENDA_VERIFICACION_PENDIENTE_KEY])
        : '';
    return $correo !== '' ? $correo : null;
}

function tienda_clear_verificacion_pendiente(): void
{
    unset($_SESSION[JOYERIA_TIENDA_VERIFICACION_PENDIENTE_KEY]);
}

function tienda_enmascarar_correo(string $correo): string
{
    $correo = trim($correo);
    if ($correo === '' || !str_contains($correo, '@')) {
        return $correo;
    }
    [$local, $domain] = explode('@', $correo, 2);
    $local = trim($local);
    if ($local === '') {
        return '***@' . $domain;
    }
    $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
    return $visible . '***@' . $domain;
}
