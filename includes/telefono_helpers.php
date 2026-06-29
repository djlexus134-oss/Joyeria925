<?php

/**
 * Normalizacion de telefonos (E.164 sin signo +) para login y unicidad.
 */

function joyeria_telefono_codigo_pais_default(): string
{
    return '52';
}

/**
 * Normaliza un telefono a formato E.164 sin el signo +.
 * Si no trae lada, antepone el codigo de pais por defecto (52 = Mexico).
 */
function joyeria_telefono_normalizado(string $telefono, ?string $codigoPaisDefault = null): string
{
    $cp = $codigoPaisDefault !== null && $codigoPaisDefault !== ''
        ? preg_replace('/[^0-9]/', '', $codigoPaisDefault)
        : joyeria_telefono_codigo_pais_default();
    if ($cp === '') {
        $cp = '52';
    }

    $tieneMas = strpos(trim($telefono), '+') === 0;
    $digitos = preg_replace('/[^0-9]/', '', $telefono) ?? '';
    if ($digitos === '') {
        return '';
    }

    if ($tieneMas) {
        return $digitos;
    }

    if (strlen($digitos) <= 10) {
        return $cp . $digitos;
    }

    return $digitos;
}

function joyeria_identificador_es_correo(string $identificador): bool
{
    $identificador = trim($identificador);
    if ($identificador === '' || !str_contains($identificador, '@')) {
        return false;
    }

    if (!function_exists('joyeria_cliente_correo_normalizado')) {
        return (bool) filter_var($identificador, FILTER_VALIDATE_EMAIL);
    }

    $norm = joyeria_cliente_correo_normalizado($identificador);

    return $norm !== '' && (bool) filter_var($norm, FILTER_VALIDATE_EMAIL);
}

function joyeria_telefono_digitos_nacionales(string $telefonoNormalizado): string
{
    if ($telefonoNormalizado === '') {
        return '';
    }

    $cp = joyeria_telefono_codigo_pais_default();
    if (str_starts_with($telefonoNormalizado, $cp) && strlen($telefonoNormalizado) > strlen($cp)) {
        return substr($telefonoNormalizado, strlen($cp));
    }

    return $telefonoNormalizado;
}

function joyeria_existe_telefono_usuario(PDO $db, string $telefonoNormalizado, ?int $excluirIdUsuario = null): bool
{
    if ($telefonoNormalizado === '') {
        return false;
    }

    $sql = "SELECT id_usuario, telefono FROM usuarios
            WHERE telefono IS NOT NULL AND TRIM(telefono) != ''";
    if ($excluirIdUsuario !== null && $excluirIdUsuario > 0) {
        $sql .= ' AND id_usuario != :excluir';
    }

    $stmt = $db->prepare($sql);
    if ($excluirIdUsuario !== null && $excluirIdUsuario > 0) {
        $stmt->bindValue(':excluir', $excluirIdUsuario, PDO::PARAM_INT);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existente = joyeria_telefono_normalizado((string) ($row['telefono'] ?? ''));
        if ($existente !== '' && $existente === $telefonoNormalizado) {
            return true;
        }
    }

    return false;
}
