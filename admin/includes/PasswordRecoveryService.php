<?php
/**
 * PasswordRecoveryService.php
 * 
 * Servicio centralizado para manejo de recuperación de contraseña
 * Genera tokens seguros, valida expiración, audita intentos
 */

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/auth.php';

class PasswordRecoveryService extends Sistema
{
    private $recoverySchemaCache = null;
    /**
     * Tiempo de validez de los tokens en minutos
     */
    private const TOKEN_EXPIRATION_MINUTES = 60;
    
    /**
     * Longitud del token en bytes (antes de hash)
     */
    private const TOKEN_LENGTH = 32;

    /**
     * Solicita recuperación de contraseña para un usuario por correo
     * 
     * @param string $correo Correo del usuario
     * @return array ['success' => bool, 'token' => string, 'user_data' => array, 'message' => string]
     */
    public function solicitarRecuperacion(string $correo): array
    {
        try {
            $correo = strtolower(trim($correo));

            // Buscar usuario por correo
            $sql = "SELECT id_usuario, nombre, primer_apellido, correo, activo 
                    FROM usuarios 
                    WHERE LOWER(TRIM(correo)) = :correo 
                    LIMIT 1";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Auditar intento
            $this->auditarEvento($correo, 'solicitud', 'Solicitud de recuperación de contraseña');

            if (!$usuario) {
                // No revelar si el correo existe (seguridad)
                return [
                    'success' => false,
                    'message' => 'Si el correo existe en nuestro sistema, recibirás una asistencia en breve',
                    'correo_requerimiento' => $correo
                ];
            }

            if ((int) ($usuario['activo'] ?? 0) !== 1) {
                return [
                    'success' => false,
                    'message' => 'Esta cuenta ha sido desactivada. Contacta con administración.'
                ];
            }

            // Limpiar tokens anteriores no utilizados
            $this->limpiarTokenosAntiguos($usuario['id_usuario']);

            // Generar token seguro
            $tokenRaw = bin2hex(random_bytes(self::TOKEN_LENGTH));
            $tokenHash = hash('sha256', $tokenRaw);
            
            // Calcular vencimiento
            $vencimiento = new DateTime();
            $vencimiento->modify('+' . self::TOKEN_EXPIRATION_MINUTES . ' minutes');

            // Guardar token en BD
            $schema = $this->resolveRecoverySchema();
            $insertCols = [$schema['userId'], $schema['tokenHash'], $schema['expiresAt']];
            $insertVals = [':id_usuario', ':token', ':vencimiento'];
            if ($schema['email'] !== null) {
                $insertCols[] = $schema['email'];
                $insertVals[] = ':correo';
            }
            if ($schema['ip'] !== null) {
                $insertCols[] = $schema['ip'];
                $insertVals[] = ':ip';
            }
            if ($schema['userAgent'] !== null) {
                $insertCols[] = $schema['userAgent'];
                $insertVals[] = ':user_agent';
            }
            if ($schema['tokenPlain'] !== null) {
                $insertCols[] = $schema['tokenPlain'];
                $insertVals[] = ':token_plain';
            }

            $sql = "INSERT INTO token_recuperacion_contrasena 
                    (" . implode(', ', $insertCols) . ")
                    VALUES (" . implode(', ', $insertVals) . ")";

            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id_usuario', $usuario['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmt->bindValue(':vencimiento', $vencimiento->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            if ($schema['email'] !== null) {
                $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
            }
            if ($schema['ip'] !== null) {
                $stmt->bindValue(':ip', $this->getClientIP(), PDO::PARAM_STR);
            }
            if ($schema['userAgent'] !== null) {
                $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '', PDO::PARAM_STR);
            }
            if ($schema['tokenPlain'] !== null) {
                $stmt->bindValue(':token_plain', $tokenRaw, PDO::PARAM_STR);
            }
            $stmt->execute();

            return [
                'success' => true,
                'token' => $tokenRaw, // Retornar token sin hash para enviarlo por correo
                'user_data' => [
                    'id_usuario' => $usuario['id_usuario'],
                    'nombre' => $usuario['nombre'],
                    'primer_apellido' => $usuario['primer_apellido'],
                    'correo' => $usuario['correo']
                ],
                'message' => 'Token de recuperación generado exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error en PasswordRecoveryService::solicitarRecuperacion - " . $e->getMessage());
            $this->auditarEvento($correo, 'solicitud', 'Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudo procesar la solicitud. Intenta de nuevo mas tarde.'
            ];
        }
    }

    /**
     * Valida un token de recuperación
     * 
     * @param string $tokenPlain Token sin hash (tal como se envió al usuario)
     * @return array ['valid' => bool, 'user_id' => int, 'message' => string]
     */
    public function validarToken(string $tokenPlain): array
    {
        try {
            // Hash del token para comparar
            $tokenHash = hash('sha256', $tokenPlain);
            $schema = $this->resolveRecoverySchema();

            $selectCorreo = $schema['email'] !== null ? $schema['email'] : 'NULL';
            $sql = "SELECT {$schema['userId']} AS id_usuario_FK,
                           {$selectCorreo} AS correo_destino,
                           {$schema['expiresAt']} AS fecha_vencimiento,
                           {$schema['usedFlag']} AS utilizado
                    FROM token_recuperacion_contrasena
                    WHERE {$schema['tokenHash']} = :token LIMIT 1";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmt->execute();
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                $this->auditarEvento('unknown', 'validacion_token', 'Token inválido o no encontrado');
                return ['valid' => false, 'message' => 'Token inválido'];
            }

            $correoDestino = isset($token['correo_destino']) && $token['correo_destino'] !== null
                ? (string) $token['correo_destino']
                : '';

            if ((int) $token['utilizado'] === 1) {
                $this->auditarEvento($correoDestino, 'validacion_token', 'Intento de reutilizar token');
                return ['valid' => false, 'message' => 'Token ya ha sido utilizado'];
            }

            $ahora = new DateTime();
            $vencimiento = new DateTime($token['fecha_vencimiento']);

            if ($ahora > $vencimiento) {
                $this->auditarEvento($correoDestino, 'validacion_token', 'Token expirado');
                return ['valid' => false, 'message' => 'Token expirado. Solicita uno nuevo'];
            }

            return [
                'valid' => true,
                'user_id' => $token['id_usuario_FK'],
                'correo' => $correoDestino,
                'message' => 'Token válido'
            ];

        } catch (Exception $e) {
            error_log("Error en PasswordRecoveryService::validarToken - " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'No se pudo validar el token. Solicita uno nuevo.'
            ];
        }
    }

    /**
     * Resetea la contraseña con un token válido
     * 
     * @param string $tokenPlain Token sin hash
     * @param string $nuevaContrasena Contraseña nueva
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetearContrasena(string $tokenPlain, string $nuevaContrasena): array
    {
        try {
            // Validar token primero
            $validacion = $this->validarToken($tokenPlain);
            if (!$validacion['valid']) {
                $this->auditarEvento('unknown', 'reset_fallido', $validacion['message']);
                return ['success' => false, 'message' => $validacion['message']];
            }

            $usuarioId = $validacion['user_id'];
            $correo = isset($validacion['correo']) && $validacion['correo'] !== null
                ? (string) $validacion['correo']
                : '';

            // Validar contraseña
            if (strlen($nuevaContrasena) < 8) {
                return ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
            }

            // Encriptar nueva contraseña
            $hashContraseña = password_hash($nuevaContrasena, PASSWORD_BCRYPT, ['cost' => 12]);

            // Actualizar contraseña en BD
            auth_mysql_set_audit_vars($this->getDb(), (int) $usuarioId);

            $sql = "UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':contrasena', $hashContraseña, PDO::PARAM_STR);
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            // Marcar token como utilizado
            $tokenHash = hash('sha256', $tokenPlain);
            $schema = $this->resolveRecoverySchema();
            $sql = "UPDATE token_recuperacion_contrasena 
                    SET {$schema['usedFlag']} = 1" . ($schema['usedAt'] !== null ? ", {$schema['usedAt']} = NOW()" : "") . "
                    WHERE {$schema['tokenHash']} = :token";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':token', $tokenHash, PDO::PARAM_STR);
            $stmt->execute();

            // Auditar éxito
            $this->auditarEvento($correo, 'reset_exitoso', 'Contraseña reseteada exitosamente');

            return [
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ];

        } catch (Exception $e) {
            error_log("Error en PasswordRecoveryService::resetearContrasena - " . $e->getMessage());
            $this->auditarEvento('unknown', 'reset_fallido', $e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudo actualizar la contraseña. Intenta de nuevo mas tarde.'
            ];
        }
    }

    /**
     * Limpia tokens antiguos o expirados de un usuario
     */
    private function limpiarTokenosAntiguos(int $usuarioId): void
    {
        try {
            $schema = $this->resolveRecoverySchema();
            $sql = "DELETE FROM token_recuperacion_contrasena 
                    WHERE {$schema['userId']} = :id_usuario 
                    AND ({$schema['expiresAt']} < NOW() OR {$schema['usedFlag']} = 1)";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id_usuario', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error limpiando tokens: " . $e->getMessage());
        }
    }

    /**
     * Audita eventos de recuperación de contraseña para seguridad
     *
     * Acepta correo nulo porque en algunos esquemas la tabla de tokens
     * no almacena el correo destino y validarToken puede no recuperarlo.
     */
    private function auditarEvento(?string $correo, string $tipoEvento, string $descripcion = ''): void
    {
        try {
            $correoSafe = (is_string($correo) && $correo !== '') ? $correo : 'unknown';

            $sql = "INSERT INTO auditoria_recuperacion_contrasena 
                    (correo_solicitante, tipo_evento, ip_origen, user_agent, descripcion)
                    VALUES (:correo, :tipo, :ip, :user_agent, :descripcion)";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':correo', $correoSafe, PDO::PARAM_STR);
            $stmt->bindValue(':tipo', $tipoEvento, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $this->getClientIP(), PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error auditando evento: " . $e->getMessage());
        }
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    private function resolveRecoverySchema(): array
    {
        if (is_array($this->recoverySchemaCache)) {
            return $this->recoverySchemaCache;
        }

        $stmt = $this->getDb()->query("SHOW COLUMNS FROM token_recuperacion_contrasena");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cols = [];
        foreach ($rows as $row) {
            $field = isset($row['Field']) ? trim((string) $row['Field']) : '';
            if ($field !== '') {
                $cols[] = $field;
            }
        }

        $pick = static function (array $candidates, array $available): ?string {
            foreach ($candidates as $c) {
                if (in_array($c, $available, true)) {
                    return $c;
                }
            }
            return null;
        };

        $schema = [
            'userId' => $pick(['id_usuario_FK', 'id_usuario_fk', 'id_usuario'], $cols),
            'tokenHash' => $pick(['token', 'token_hash', 'hash_token', 'token_recuperacion'], $cols),
            'tokenPlain' => $pick(['token_plain', 'token_plano'], $cols),
            'email' => $pick(['correo_destino', 'correo', 'email_destino'], $cols),
            'expiresAt' => $pick(['fecha_vencimiento', 'fecha_expiracion', 'vencimiento'], $cols),
            'usedFlag' => $pick(['utilizado', 'usado'], $cols),
            'usedAt' => $pick(['fecha_utilizacion', 'fecha_uso'], $cols),
            'ip' => $pick(['ip_origen', 'ip_generacion', 'ip'], $cols),
            'userAgent' => $pick(['user_agent', 'user_agent_generacion', 'user_agent_origen'], $cols),
        ];

        foreach (['userId', 'tokenHash', 'expiresAt', 'usedFlag'] as $required) {
            if ($schema[$required] === null) {
                throw new RuntimeException('Esquema de recuperacion incompatible: falta ' . $required);
            }
        }
        $this->recoverySchemaCache = $schema;
        return $this->recoverySchemaCache;
    }
}
