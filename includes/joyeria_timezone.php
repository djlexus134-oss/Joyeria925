<?php
/**
 * Zona horaria de la joyeria (operacion en Mexico).
 * Definir JOYERIA_TIMEZONE en config.php, p. ej. America/Mexico_City
 */
declare(strict_types=1);

function joyeria_timezone_name(): string
{
    if (defined('JOYERIA_TIMEZONE')) {
        $tz = trim((string) JOYERIA_TIMEZONE);
        if ($tz !== '') {
            return $tz;
        }
    }

    return 'America/Mexico_City';
}

function joyeria_timezone_bootstrap(): void
{
    $tz = joyeria_timezone_name();
    try {
        new DateTimeZone($tz);
        date_default_timezone_set($tz);
    } catch (Throwable $e) {
        error_log('joyeria_timezone: zona invalida "' . $tz . '", usando America/Mexico_City. ' . $e->getMessage());
        date_default_timezone_set('America/Mexico_City');
    }
}

function joyeria_today_ymd(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
}

/**
 * Suma meses a una fecha Y-m-d (o a hoy si $fromYmd es null/vacio).
 */
function joyeria_add_months_ymd(int $months, ?string $fromYmd = null): string
{
    $base = trim((string) $fromYmd);
    if ($base === '') {
        $d = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    } else {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $base, new DateTimeZone(date_default_timezone_get()));
        if (!$d || $d->format('Y-m-d') !== $base) {
            $d = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        }
    }

    return $d->modify(($months >= 0 ? '+' : '') . $months . ' month')->format('Y-m-d');
}

function joyeria_now_datetime(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d H:i:s');
}

/**
 * Alinea la sesion MySQL con el dia civil local (DATE() en consultas de cierre, ventas, etc.).
 */
function joyeria_pdo_set_timezone(PDO $pdo): void
{
    try {
        $offset = (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('P');
        $stmt = $pdo->prepare('SET time_zone = ?');
        $stmt->bindValue(1, $offset, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('joyeria_pdo_set_timezone: ' . $e->getMessage());
    }
}
