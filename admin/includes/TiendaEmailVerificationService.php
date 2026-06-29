<?php

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/auth.php';

class TiendaEmailVerificationService extends Sistema
{
    private const TOKEN_EXPIRATION_HOURS = 24;
    private const TOKEN_LENGTH = 32;

    /**
     * Genera token y lo guarda para un cliente de tienda pendiente de verificación.
     *
     * @return array{success: bool, token?: string, user_data?: array, message: string}
     */
    public function crearToken(int $idUsuario): array
    {
        try {
            $sql = "SELECT u.id_usuario, u.nombre, u.primer_apellido, u.correo, u.activo, u.correo_verificado_en,
                           c.id_cliente, c.activo AS cliente_activo
                    FROM usuarios u
                    INNER JOIN clientes c ON c.id_usuario_FK = u.id_usuario AND c.activo = 1
                    WHERE u.id_usuario = :id
                    LIMIT 1";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }
            if ((int) ($usuario['activo'] ?? 0) !== 1) {
                return ['success' => false, 'message' => 'La cuenta no está activa.'];
            }
            if ($this->correoVerificado($usuario)) {
                return ['success' => false, 'message' => 'El correo ya fue verificado.'];
            }

            $this->limpiarTokensAntiguos($idUsuario);

            $tokenRaw = bin2hex(random_bytes(self::TOKEN_LENGTH));
            $tokenHash = hash('sha256', $tokenRaw);
            $vencimiento = (new DateTime())->modify('+' . self::TOKEN_EXPIRATION_HOURS . ' hours');

            $sqlInsert = "INSERT INTO token_verificacion_correo_tienda
                (id_usuario_FK, token, correo_destino, fecha_vencimiento, ip_origen, user_agent)
                VALUES (:id_usuario, :token, :correo, :vencimiento, :ip, :user_agent)";
            $stmtInsert = $this->getDb()->prepare($sqlInsert);
            $stmtInsert->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtInsert->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmtInsert->bindValue(':correo', (string) $usuario['correo'], PDO::PARAM_STR);
            $stmtInsert->bindValue(':vencimiento', $vencimiento->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmtInsert->bindValue(':ip', $this->getClientIP(), PDO::PARAM_STR);
            $stmtInsert->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '', PDO::PARAM_STR);
            $stmtInsert->execute();

            return [
                'success' => true,
                'token' => $tokenRaw,
                'user_data' => [
                    'id_usuario' => (int) $usuario['id_usuario'],
                    'nombre' => (string) $usuario['nombre'],
                    'primer_apellido' => (string) $usuario['primer_apellido'],
                    'correo' => (string) $usuario['correo'],
                ],
                'message' => 'Token de verificación generado.',
            ];
        } catch (Throwable $e) {
            error_log('TiendaEmailVerificationService::crearToken - ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo generar el enlace de verificación.'];
        }
    }

    /**
     * @return array{success: bool, token?: string, user_data?: array, message: string}
     */
    public function solicitarPorCorreo(string $correo): array
    {
        $correo = mb_strtolower(trim($correo));
        $mensajeGenerico = 'Si el correo está registrado y pendiente de confirmación, recibirás un nuevo enlace. Revisa también spam.';

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Introduce un correo válido.'];
        }

        try {
            $sql = "SELECT u.id_usuario, u.correo_verificado_en
                    FROM usuarios u
                    INNER JOIN clientes c ON c.id_usuario_FK = u.id_usuario AND c.activo = 1
                    WHERE LOWER(TRIM(u.correo)) = :correo AND u.activo = 1
                    LIMIT 1";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || $this->correoVerificado($row)) {
                return ['success' => true, 'message' => $mensajeGenerico, 'generic' => true];
            }

            return $this->crearToken((int) $row['id_usuario']);
        } catch (Throwable $e) {
            error_log('TiendaEmailVerificationService::solicitarPorCorreo - ' . $e->getMessage());
            return ['success' => true, 'message' => $mensajeGenerico, 'generic' => true];
        }
    }

    /**
     * @return array{valid: bool, user_id?: int, message: string}
     */
    public function validarToken(string $tokenPlain): array
    {
        try {
            $tokenHash = hash('sha256', trim($tokenPlain));
            $sql = "SELECT id_usuario_FK, correo_destino, fecha_vencimiento, utilizado
                    FROM token_verificacion_correo_tienda
                    WHERE token = :token
                    LIMIT 1";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmt->execute();
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                return ['valid' => false, 'message' => 'Enlace inválido o expirado.'];
            }
            if ((int) ($token['utilizado'] ?? 0) === 1) {
                return ['valid' => false, 'message' => 'Este enlace ya fue utilizado.'];
            }

            $ahora = new DateTime();
            $vencimiento = new DateTime((string) $token['fecha_vencimiento']);
            if ($ahora > $vencimiento) {
                return ['valid' => false, 'message' => 'El enlace expiró. Solicita uno nuevo desde el inicio de sesión.'];
            }

            return [
                'valid' => true,
                'user_id' => (int) $token['id_usuario_FK'],
                'message' => 'Token válido.',
            ];
        } catch (Throwable $e) {
            error_log('TiendaEmailVerificationService::validarToken - ' . $e->getMessage());
            return ['valid' => false, 'message' => 'No se pudo validar el enlace.'];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function confirmarCorreo(string $tokenPlain): array
    {
        $validacion = $this->validarToken($tokenPlain);
        if (!$validacion['valid']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $usuarioId = (int) $validacion['user_id'];
        $db = $this->getDb();

        try {
            $db->beginTransaction();

            auth_mysql_set_audit_vars($db, $usuarioId);

            $stmt = $db->prepare(
                'UPDATE usuarios SET correo_verificado_en = NOW() WHERE id_usuario = :id AND correo_verificado_en IS NULL'
            );
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $tokenHash = hash('sha256', trim($tokenPlain));
            $stmtToken = $db->prepare(
                'UPDATE token_verificacion_correo_tienda SET utilizado = 1, fecha_utilizacion = NOW() WHERE token = :token'
            );
            $stmtToken->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmtToken->execute();

            $db->commit();

            return ['success' => true, 'message' => 'Correo confirmado. Ya puedes iniciar sesión.'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('TiendaEmailVerificationService::confirmarCorreo - ' . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo confirmar el correo. Intenta de nuevo.'];
        }
    }

    public function correoVerificado(array $usuario): bool
    {
        $val = $usuario['correo_verificado_en'] ?? null;
        return $val !== null && trim((string) $val) !== '';
    }

    private function limpiarTokensAntiguos(int $usuarioId): void
    {
        try {
            $sql = 'DELETE FROM token_verificacion_correo_tienda
                    WHERE id_usuario_FK = :id_usuario
                    AND (fecha_vencimiento < NOW() OR utilizado = 1)';
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id_usuario', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('TiendaEmailVerificationService limpiarTokens: ' . $e->getMessage());
        }
    }

    private function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return (string) $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
