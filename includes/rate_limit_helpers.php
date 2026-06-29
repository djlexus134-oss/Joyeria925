<?php

declare(strict_types=1);

/**
 * Rate-limiting basico basado en BD (tabla rate_limit_intentos).
 *
 * Cuenta los intentos por (accion, clave) dentro de una ventana deslizante y,
 * si se supera el limite, rechaza hasta que la ventana se libere. Pensado para
 * frenar fuerza bruta de login y bombardeo de correos (registro, recuperacion,
 * reenvio de verificacion).
 *
 * Migracion: sql/2026_06_11_rate_limit.sql
 */

/**
 * IP del cliente para identificar la fuente. Usa REMOTE_ADDR (no cabeceras
 * spoofeables); detras de Nginx, PHP-FPM ya recibe la IP real del cliente.
 */
function joyeria_rate_limit_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

/**
 * Registra el intento y determina si se supero el limite en la ventana.
 *
 * Fail-open intencional: si la tabla no existe (migracion pendiente) o hay un
 * error de BD, se permite la peticion para no dejar fuera a los usuarios; el
 * detalle queda en error_log.
 *
 * @return array{permitido: bool, reintentar_en: int}
 */
function joyeria_rate_limit_check(
    PDO $db,
    string $accion,
    string $clave,
    int $maxIntentos,
    int $ventanaSegundos
): array {
    $accion = substr(trim($accion), 0, 40);
    $clave = substr(trim($clave), 0, 190);
    if ($accion === '' || $clave === '' || $maxIntentos < 1 || $ventanaSegundos < 1) {
        return ['permitido' => true, 'reintentar_en' => 0];
    }

    // $ventanaSegundos es un entero controlado por el servidor; se castea y se
    // interpola para evitar problemas de binding dentro de INTERVAL.
    $ventana = (int) $ventanaSegundos;

    try {
        // Limpieza oportunista de registros viejos (>1 dia) para que la tabla
        // no crezca sin control. Solo de vez en cuando.
        if (random_int(1, 25) === 1) {
            $db->exec('DELETE FROM rate_limit_intentos WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
        }

        $sql = 'SELECT COUNT(*) AS total, MIN(creado_en) AS primero
                FROM rate_limit_intentos
                WHERE accion = :a AND clave = :k
                  AND creado_en > (NOW() - INTERVAL ' . $ventana . ' SECOND)';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':a', $accion, PDO::PARAM_STR);
        $stmt->bindValue(':k', $clave, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($row['total'] ?? 0);

        if ($total >= $maxIntentos) {
            $reintentar = $ventana;
            if (!empty($row['primero'])) {
                $primero = strtotime((string) $row['primero']);
                if ($primero !== false) {
                    $reintentar = max(1, ($primero + $ventana) - time());
                }
            }
            return ['permitido' => false, 'reintentar_en' => (int) $reintentar];
        }

        $ins = $db->prepare(
            'INSERT INTO rate_limit_intentos (accion, clave, creado_en) VALUES (:a, :k, NOW())'
        );
        $ins->bindValue(':a', $accion, PDO::PARAM_STR);
        $ins->bindValue(':k', $clave, PDO::PARAM_STR);
        $ins->execute();

        return ['permitido' => true, 'reintentar_en' => 0];
    } catch (Throwable $e) {
        error_log('joyeria_rate_limit_check: ' . $e->getMessage());
        return ['permitido' => true, 'reintentar_en' => 0];
    }
}
